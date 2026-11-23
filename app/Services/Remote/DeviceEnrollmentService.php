<?php

namespace App\Services\Remote;

use App\Models\AppDevice;
use App\Models\DeviceEnrollment;
use App\Models\ManagedApp;
use App\Models\ServiceDefinition;
use App\Models\ServiceStatus;
use App\Models\User;
use Database\Seeders\ServiceDefinitionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeviceEnrollmentService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * Membuat kode enrollment baru untuk aplikasi tertentu (hanya oleh admin Xoose).
     */
    public function createEnrollmentCode(
        ManagedApp $app,
        ?User $actor = null,
        ?string $label = null,
        int $maxUses = 1,
        int $expiresInHours = 24
    ): DeviceEnrollment {
        $code = 'XLY-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));

        $enrollment = DeviceEnrollment::create([
            'managed_app_id' => $app->id,
            'enrollment_code' => $code,
            'label' => $label,
            'status' => DeviceEnrollment::STATUS_PENDING,
            'max_uses' => max(1, $maxUses),
            'uses_count' => 0,
            'expires_at' => now()->addHours(max(1, $expiresInHours)),
            'created_by' => $actor?->id,
        ]);

        if ($actor) {
            $this->auditLog->adminAction(
                actor: $actor,
                message: "Membuat enrollment code baru ({$enrollment->enrollment_code}) untuk aplikasi '{$app->name}'.",
                serviceKey: 'enrollment',
                app: $app,
                context: [
                    'enrollment_id' => $enrollment->id,
                    'max_uses' => $enrollment->max_uses,
                    'expires_at' => $enrollment->expires_at?->toIso8601String(),
                ],
            );
        }

        return $enrollment;
    }

    /**
     * Memproses pendaftaran perangkat baru dari LoveYou Agent (tanpa login admin di LoveYou).
     * Mengembalikan pasangan [AppDevice, rawToken] dimana rawToken hanya dikirim sekali.
     *
     * @return array{device: AppDevice, device_token: string}
     */
    public function enrollDevice(array $data, ?string $ipAddress = null): array
    {
        $code = trim((string) ($data['enrollment_code'] ?? ''));

        /** @var DeviceEnrollment|null $enrollment */
        $enrollment = DeviceEnrollment::with('managedApp')
            ->where('enrollment_code', $code)
            ->first();

        if (! $enrollment) {
            throw ValidationException::withMessages([
                'enrollment_code' => ['Enrollment code tidak ditemukan atau tidak valid.'],
            ]);
        }

        if ($enrollment->expires_at !== null && $enrollment->expires_at->isPast()) {
            if ($enrollment->status === DeviceEnrollment::STATUS_PENDING) {
                $enrollment->update(['status' => DeviceEnrollment::STATUS_EXPIRED]);
            }
            throw ValidationException::withMessages([
                'enrollment_code' => ['Enrollment code sudah kedaluwarsa.'],
            ]);
        }

        if (! $enrollment->isUsable()) {
            throw ValidationException::withMessages([
                'enrollment_code' => ['Enrollment code sudah terpakai atau dicabut.'],
            ]);
        }

        $app = $enrollment->managedApp;
        if (! $app || ! $app->is_active) {
            throw ValidationException::withMessages([
                'app_slug' => ['Aplikasi target tidak aktif.'],
            ]);
        }

        if (! empty($data['app_slug']) && $data['app_slug'] !== $app->slug) {
            throw ValidationException::withMessages([
                'app_slug' => ["Enrollment code tidak cocok dengan aplikasi '{$data['app_slug']}'."],
            ]);
        }

        // Pastikan 7 definisi layanan dasar tersedia sebelum registrasi perangkat
        $this->ensureDefaultServiceDefinitions();

        $rawToken = 'xly_dev_' . bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $deviceUuid = (string) ($data['device_uuid'] ?? Str::uuid());

        $device = DB::transaction(function () use ($enrollment, $app, $data, $deviceUuid, $tokenHash, $ipAddress) {
            $existing = AppDevice::where('device_uuid', $deviceUuid)->first();

            if ($existing) {
                $existing->update([
                    'managed_app_id' => $app->id,
                    'hostname' => (string) ($data['hostname'] ?? $existing->hostname),
                    'os_info' => $data['os_info'] ?? $existing->os_info,
                    'app_version' => (string) ($data['app_version'] ?? $existing->app_version),
                    'status' => AppDevice::STATUS_ONLINE,
                    'token_hash' => $tokenHash,
                    'last_ip' => $ipAddress,
                    'revoked_at' => null,
                    'last_seen_at' => now(),
                ]);
                $device = $existing;
            } else {
                $device = AppDevice::create([
                    'device_uuid' => $deviceUuid,
                    'managed_app_id' => $app->id,
                    'device_code' => 'DEV-' . strtoupper(Str::random(8)),
                    'hostname' => (string) ($data['hostname'] ?? 'Unknown'),
                    'os_info' => $data['os_info'] ?? null,
                    'app_version' => (string) ($data['app_version'] ?? '1.0.0'),
                    'status' => AppDevice::STATUS_ONLINE,
                    'token_hash' => $tokenHash,
                    'last_ip' => $ipAddress,
                    'last_seen_at' => now(),
                ]);
            }

            // Sinkronisasi daftar service yang dilaporkan perangkat
            foreach (($data['services'] ?? []) as $svcDef) {
                if (! is_array($svcDef) || empty($svcDef['service_key'])) {
                    continue;
                }
                $svcKey = (string) $svcDef['service_key'];

                ServiceDefinition::firstOrCreate(
                    ['service_key' => $svcKey],
                    [
                        'display_name' => (string) ($svcDef['display_name'] ?? $svcKey),
                        'supported_actions' => $svcDef['supported_actions'] ?? ['start', 'stop'],
                        'configuration_schema' => $svcDef['configuration_schema'] ?? ['type' => 'object', 'properties' => []],
                        'permission_requirements' => $svcDef['permission_requirements'] ?? [],
                        'is_system' => false,
                    ]
                );

                ServiceStatus::updateOrCreate(
                    ['device_id' => $device->id, 'service_key' => $svcKey],
                    [
                        'current_status' => (string) ($svcDef['current_status'] ?? 'Stopped'),
                        'active_config_version' => (int) ($svcDef['active_config_version'] ?? 0),
                        'reported_at' => now(),
                    ]
                );
            }

            $newUses = $enrollment->uses_count + 1;
            $enrollment->update([
                'uses_count' => $newUses,
                'enrolled_device_id' => $device->id,
                'status' => $newUses >= $enrollment->max_uses
                    ? DeviceEnrollment::STATUS_USED
                    : DeviceEnrollment::STATUS_PENDING,
            ]);

            $this->auditLog->record(
                eventType: 'enrollment_event',
                message: "Perangkat '{$device->hostname}' ({$device->device_code}) berhasil terdaftar ke '{$app->name}'.",
                level: 'info',
                serviceKey: 'enrollment',
                app: $app,
                device: $device,
                context: [
                    'device_uuid' => $device->device_uuid,
                    'enrollment_code' => $enrollment->enrollment_code,
                    'ip_address' => $ipAddress,
                ],
            );

            return $device;
        });

        return [
            'device' => $device,
            'device_token' => $rawToken,
        ];
    }

    /**
     * Mencabut akses perangkat (oleh admin di Xoose atau self-unregister dari LoveYou).
     */
    public function revokeDevice(AppDevice $device, ?User $actor = null, string $reason = 'admin_revoked'): AppDevice
    {
        $device->update([
            'status' => AppDevice::STATUS_REVOKED,
            'token_hash' => null,
            'revoked_at' => now(),
        ]);

        if ($actor) {
            $this->auditLog->adminAction(
                actor: $actor,
                message: "Admin mencabut akses perangkat '{$device->hostname}' ({$device->device_code}). Alasan: {$reason}.",
                serviceKey: 'security',
                app: $device->managedApp,
                device: $device,
                context: ['reason' => $reason],
                level: 'warning',
            );
        } else {
            $this->auditLog->record(
                eventType: 'security_event',
                message: "Perangkat '{$device->hostname}' ({$device->device_code}) mencabut pendaftarannya secara lokal. Alasan: {$reason}.",
                level: 'warning',
                serviceKey: 'security',
                app: $device->managedApp,
                device: $device,
                context: ['reason' => $reason],
            );
        }

        return $device->fresh();
    }

    public function ensureDefaultServiceDefinitions(): void
    {
        $defaults = ServiceDefinitionSeeder::defaultDefinitions();
        $existingKeys = ServiceDefinition::pluck('service_key')->all();

        foreach ($defaults as $def) {
            if (! in_array($def['service_key'], $existingKeys, true)) {
                ServiceDefinition::updateOrCreate(['service_key' => $def['service_key']], $def);
            }
        }
    }
}
