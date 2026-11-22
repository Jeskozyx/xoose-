<?php

namespace App\Services\Remote;

use App\Models\AppDevice;
use App\Models\ManagedApp;
use App\Models\ServiceConfig;
use App\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfigVersionService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * Memvalidasi config_json terhadap JSON Schema pada service_definitions.
     * Menolak properti tambahan (additionalProperties = false), tipe data salah, atau nilai di luar batas.
     */
    public function validateAgainstSchema(string $serviceKey, array $configJson): array
    {
        $definition = ServiceDefinition::where('service_key', $serviceKey)->first();
        if (! $definition) {
            throw ValidationException::withMessages([
                'service_key' => ["Service '{$serviceKey}' tidak ditemukan pada katalog service_definitions."],
            ]);
        }

        $schema = $definition->configuration_schema ?? [];
        $properties = $schema['properties'] ?? [];
        $allowExtra = ($schema['additionalProperties'] ?? true) !== false;
        $errors = [];

        foreach ($configJson as $key => $value) {
            if (! array_key_exists($key, $properties)) {
                if (! $allowExtra) {
                    $errors["config_json.{$key}"][] = "Parameter '{$key}' tidak diizinkan pada skema '{$serviceKey}'.";
                }
                continue;
            }

            $rule = $properties[$key];
            $expectedType = $rule['type'] ?? null;

            if ($expectedType === 'integer') {
                if (! is_int($value)) {
                    $errors["config_json.{$key}"][] = "Parameter '{$key}' harus berupa integer.";
                    continue;
                }
                if (isset($rule['minimum']) && $value < $rule['minimum']) {
                    $errors["config_json.{$key}"][] = "Parameter '{$key}' minimal {$rule['minimum']}.";
                }
                if (isset($rule['maximum']) && $value > $rule['maximum']) {
                    $errors["config_json.{$key}"][] = "Parameter '{$key}' maksimal {$rule['maximum']}.";
                }
            } elseif ($expectedType === 'number') {
                if (! is_int($value) && ! is_float($value)) {
                    $errors["config_json.{$key}"][] = "Parameter '{$key}' harus berupa angka.";
                    continue;
                }
                if (isset($rule['minimum']) && $value < $rule['minimum']) {
                    $errors["config_json.{$key}"][] = "Parameter '{$key}' minimal {$rule['minimum']}.";
                }
                if (isset($rule['maximum']) && $value > $rule['maximum']) {
                    $errors["config_json.{$key}"][] = "Parameter '{$key}' maksimal {$rule['maximum']}.";
                }
            } elseif ($expectedType === 'boolean') {
                if (! is_bool($value)) {
                    $errors["config_json.{$key}"][] = "Parameter '{$key}' harus berupa boolean (true/false).";
                }
            } elseif ($expectedType === 'string') {
                if (! is_string($value)) {
                    $errors["config_json.{$key}"][] = "Parameter '{$key}' harus berupa string.";
                    continue;
                }
                $len = mb_strlen($value);
                if (isset($rule['minLength']) && $len < $rule['minLength']) {
                    $errors["config_json.{$key}"][] = "Panjang '{$key}' minimal {$rule['minLength']} karakter.";
                }
                if (isset($rule['maxLength']) && $len > $rule['maxLength']) {
                    $errors["config_json.{$key}"][] = "Panjang '{$key}' maksimal {$rule['maxLength']} karakter.";
                }
                if (isset($rule['enum']) && ! in_array($value, $rule['enum'], true)) {
                    $opts = implode(', ', $rule['enum']);
                    $errors["config_json.{$key}"][] = "Nilai '{$key}' harus salah satu dari: {$opts}.";
                }
            } elseif ($expectedType === 'object') {
                if (! is_array($value)) {
                    $errors["config_json.{$key}"][] = "Parameter '{$key}' harus berupa JSON object.";
                }
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return $configJson;
    }

    /**
     * Menyimpan versi konfigurasi baru secara monoton (config_version + 1) untuk scope App atau Device.
     */
    public function updateConfig(
        ManagedApp $app,
        string $serviceKey,
        array $configJson,
        ?AppDevice $device = null,
        ?User $actor = null,
        ?string $changeNotes = null
    ): ServiceConfig {
        $validated = $this->validateAgainstSchema($serviceKey, $configJson);

        return DB::transaction(function () use ($app, $device, $serviceKey, $validated, $actor, $changeNotes) {
            $scopeQuery = ServiceConfig::where('managed_app_id', $app->id)
                ->where('service_key', $serviceKey)
                ->when($device !== null, fn ($q) => $q->where('device_id', $device->id), fn ($q) => $q->whereNull('device_id'));

            $currentActive = (clone $scopeQuery)->where('is_active', true)->orderByDesc('config_version')->first();
            $maxVersion = (int) ((clone $scopeQuery)->max('config_version') ?? 0);
            $nextVersion = $maxVersion + 1;

            if ($currentActive) {
                $currentActive->update(['is_active' => false]);
            }

            $merged = array_merge($currentActive?->config_json ?? [], $validated);

            $created = ServiceConfig::create([
                'managed_app_id' => $app->id,
                'device_id' => $device?->id,
                'service_key' => $serviceKey,
                'config_version' => $nextVersion,
                'config_json' => $merged,
                'is_active' => true,
                'force_apply' => false,
                'updated_by' => $actor?->id,
                'previous_version_id' => $currentActive?->id,
                'change_notes' => $changeNotes,
            ]);

            if ($actor) {
                $scopeLabel = $device ? "perangkat {$device->device_code}" : "aplikasi {$app->slug}";
                $this->auditLog->adminAction(
                    actor: $actor,
                    message: "Admin memperbarui konfigurasi '{$serviceKey}' ke v{$nextVersion} ({$scopeLabel}).",
                    serviceKey: $serviceKey,
                    app: $app,
                    device: $device,
                    context: [
                        'config_version' => $nextVersion,
                        'previous_version_id' => $currentActive?->id,
                        'change_notes' => $changeNotes,
                    ],
                );
            }

            return $created;
        });
    }

    /**
     * Melakukan rollback konfigurasi ke versi target sebelumnya.
     * Menetapkan versi baru berbasis snapshot target dengan `force_apply = true`
     * agar riwayat audit tetap immutable sekaligus menjamin Agent menerapkan ulang konfigurasi.
     */
    public function rollbackToVersion(
        ManagedApp $app,
        string $serviceKey,
        int $targetVersion,
        ?AppDevice $device = null,
        ?User $actor = null
    ): ServiceConfig {
        $target = ServiceConfig::where('managed_app_id', $app->id)
            ->where('service_key', $serviceKey)
            ->when($device !== null, fn ($q) => $q->where('device_id', $device->id), fn ($q) => $q->whereNull('device_id'))
            ->where('config_version', $targetVersion)
            ->first();

        if (! $target) {
            throw ValidationException::withMessages([
                'version' => ["Konfigurasi '{$serviceKey}' versi {$targetVersion} tidak ditemukan."],
            ]);
        }

        return DB::transaction(function () use ($app, $device, $serviceKey, $target, $targetVersion, $actor) {
            $scopeQuery = ServiceConfig::where('managed_app_id', $app->id)
                ->where('service_key', $serviceKey)
                ->when($device !== null, fn ($q) => $q->where('device_id', $device->id), fn ($q) => $q->whereNull('device_id'));

            $currentActive = (clone $scopeQuery)->where('is_active', true)->orderByDesc('config_version')->first();
            $nextVersion = ((int) ((clone $scopeQuery)->max('config_version') ?? 0)) + 1;

            if ($currentActive) {
                $currentActive->update(['is_active' => false]);
            }

            $rolledBack = ServiceConfig::create([
                'managed_app_id' => $app->id,
                'device_id' => $device?->id,
                'service_key' => $serviceKey,
                'config_version' => $nextVersion,
                'config_json' => $target->config_json,
                'is_active' => true,
                'force_apply' => true,
                'updated_by' => $actor?->id,
                'previous_version_id' => $currentActive?->id,
                'change_notes' => "Rollback ke snapshot v{$targetVersion}",
            ]);

            if ($actor) {
                $this->auditLog->adminAction(
                    actor: $actor,
                    message: "Admin melakukan rollback konfigurasi '{$serviceKey}' ke snapshot v{$targetVersion} (diterbitkan sebagai v{$nextVersion}).",
                    serviceKey: $serviceKey,
                    app: $app,
                    device: $device,
                    context: [
                        'rollback_from_version' => $currentActive?->config_version,
                        'rollback_target_version' => $targetVersion,
                        'new_config_version' => $nextVersion,
                    ],
                    level: 'warning',
                );
            }

            return $rolledBack;
        });
    }

    /**
     * Menyelesaikan konfigurasi aktif efektif untuk perangkat tertentu (device override > app default)
     * dan menyaring berdasarkan versi lokal Agent.
     *
     * @param  array<string, int|string>  $clientVersions
     * @return Collection<int, array<string, mixed>>
     */
    public function resolveEffectiveConfigsForDevice(AppDevice $device, array $clientVersions = []): Collection
    {
        $appConfigs = ServiceConfig::where('managed_app_id', $device->managed_app_id)
            ->whereNull('device_id')
            ->where('is_active', true)
            ->get()
            ->keyBy('service_key');

        $deviceConfigs = ServiceConfig::where('managed_app_id', $device->managed_app_id)
            ->where('device_id', $device->id)
            ->where('is_active', true)
            ->get()
            ->keyBy('service_key');

        $merged = $appConfigs->toBase()->merge($deviceConfigs->toBase());

        return $merged->filter(function (ServiceConfig $cfg) use ($clientVersions) {
            $localVer = (int) ($clientVersions[$cfg->service_key] ?? 0);
            if ($cfg->force_apply) {
                return $localVer !== $cfg->config_version;
            }

            return $cfg->config_version > $localVer;
        })->map(fn (ServiceConfig $cfg) => $cfg->toAgentPayload())->values();
    }
}
