<?php

namespace App\Services\Remote;

use App\Models\AppDevice;
use App\Models\ServiceCommand;
use App\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommandDispatcherService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * Mengirim perintah remote tervalidasi (allowlist ketat + TTL + audit log).
     */
    public function dispatch(
        AppDevice $device,
        string $serviceKey,
        string $action,
        array $payload = [],
        ?User $actor = null,
        ?int $ttlSeconds = null
    ): ServiceCommand {
        if ($device->isRevoked()) {
            throw ValidationException::withMessages([
                'device_id' => ['Tidak dapat mengirim perintah ke perangkat yang aksesnya telah dicabut.'],
            ]);
        }

        $actionClean = strtolower(trim($action));
        $allowedActions = config('remote-control.allowed_actions', ['start', 'stop', 'pause', 'resume', 'restart', 'apply_config']);

        if (! in_array($actionClean, $allowedActions, true)) {
            throw ValidationException::withMessages([
                'action' => ["Aksi '{$action}' ditolak: tidak termasuk daftar aksi allowlist yang diizinkan."],
            ]);
        }

        $definition = ServiceDefinition::where('service_key', $serviceKey)->first();
        if (! $definition) {
            throw ValidationException::withMessages([
                'service_key' => ["Service '{$serviceKey}' tidak terdaftar pada katalog service_definitions."],
            ]);
        }

        if (! $definition->supportsAction($actionClean)) {
            $supported = implode(', ', $definition->supported_actions ?? []);
            throw ValidationException::withMessages([
                'action' => ["Service '{$serviceKey}' tidak mendukung aksi '{$actionClean}'. Didukung: {$supported}."],
            ]);
        }

        $defaultTtl = (int) config('remote-control.command_default_ttl_seconds', 300);
        $maxTtl = (int) config('remote-control.command_max_ttl_seconds', 3600);
        $ttl = $ttlSeconds !== null ? max(10, min($ttlSeconds, $maxTtl)) : $defaultTtl;

        $command = ServiceCommand::create([
            'command_id' => (string) Str::uuid(),
            'device_id' => $device->id,
            'service_key' => $serviceKey,
            'action' => $actionClean,
            'payload' => $payload,
            'status' => ServiceCommand::STATUS_PENDING,
            'issued_by' => $actor?->id,
            'expires_at' => now()->addSeconds($ttl),
        ]);

        if ($actor) {
            $this->auditLog->adminAction(
                actor: $actor,
                message: "Admin mengirim command '{$actionClean}' ke service '{$serviceKey}' pada perangkat '{$device->device_code}'.",
                serviceKey: $serviceKey,
                app: $device->managedApp,
                device: $device,
                context: [
                    'command_id' => $command->command_id,
                    'action' => $actionClean,
                    'expires_at' => $command->expires_at?->toIso8601String(),
                ],
            );
        }

        return $command->load('device');
    }

    /**
     * Mengambil command aktif (belum kedaluwarsa) untuk perangkat tertentu via HTTPS polling,
     * menandai command kadaluwarsa sebagai 'expired' dan command baru sebagai 'delivered'.
     *
     * @return Collection<int, ServiceCommand>
     */
    public function fetchPendingForDevice(AppDevice $device): Collection
    {
        // Tandai terlebih dahulu command yang sudah lewat expires_at milik perangkat ini
        ServiceCommand::where('device_id', $device->id)
            ->whereIn('status', [ServiceCommand::STATUS_PENDING, ServiceCommand::STATUS_DELIVERED])
            ->where('expires_at', '<=', now())
            ->update([
                'status' => ServiceCommand::STATUS_EXPIRED,
                'completed_at' => now(),
                'result_message' => 'Command kedaluwarsa sebelum dieksekusi oleh Agent.',
            ]);

        $commands = ServiceCommand::with('device')
            ->where('device_id', $device->id)
            ->whereIn('status', [ServiceCommand::STATUS_PENDING, ServiceCommand::STATUS_DELIVERED])
            ->where('expires_at', '>', now())
            ->orderBy('id')
            ->limit(25)
            ->get();

        foreach ($commands as $cmd) {
            if ($cmd->status === ServiceCommand::STATUS_PENDING) {
                $cmd->update([
                    'status' => ServiceCommand::STATUS_DELIVERED,
                    'delivered_at' => now(),
                ]);
            }
        }

        return $commands;
    }

    /**
     * Menerima acknowledgment & status eksekusi akhir dari LoveYou Agent.
     * Menjaga idempotency: command terminal tidak akan ditimpa ulang.
     */
    public function acknowledge(
        AppDevice $device,
        ServiceCommand $command,
        string $status,
        string $resultMessage = '',
        array $resultPayload = []
    ): ServiceCommand {
        if ($command->device_id !== $device->id) {
            abort(403, 'Command bukan milik perangkat ini.');
        }

        if ($command->isTerminal()) {
            return $command;
        }

        $statusClean = strtolower(trim($status));
        $allowedAckStatuses = [
            ServiceCommand::STATUS_ACKNOWLEDGED,
            ServiceCommand::STATUS_SUCCEEDED,
            ServiceCommand::STATUS_FAILED,
            ServiceCommand::STATUS_EXPIRED,
        ];

        if (! in_array($statusClean, $allowedAckStatuses, true)) {
            throw ValidationException::withMessages([
                'status' => ["Status acknowledgment '{$status}' tidak valid."],
            ]);
        }

        $updates = [
            'status' => $statusClean,
            'result_message' => $resultMessage !== '' ? $resultMessage : $command->result_message,
            'result_payload' => ! empty($resultPayload) ? $resultPayload : $command->result_payload,
        ];

        if ($statusClean === ServiceCommand::STATUS_ACKNOWLEDGED) {
            $updates['acknowledged_at'] = $command->acknowledged_at ?? now();
        } else {
            $updates['acknowledged_at'] = $command->acknowledged_at ?? now();
            $updates['completed_at'] = now();
        }

        $command->update($updates);

        $this->auditLog->record(
            eventType: 'command_event',
            message: "Command {$command->command_id} ({$command->service_key}:{$command->action}) -> {$statusClean}" . ($resultMessage ? " ({$resultMessage})" : ''),
            level: $statusClean === ServiceCommand::STATUS_FAILED ? 'error' : 'info',
            serviceKey: $command->service_key,
            app: $device->managedApp,
            device: $device,
            context: [
                'command_id' => $command->command_id,
                'status' => $statusClean,
            ],
        );

        return $command->fresh();
    }

    /**
     * Membatalkan command yang masih pending/delivered oleh admin.
     */
    public function cancel(ServiceCommand $command, ?User $actor = null): ServiceCommand
    {
        if ($command->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => ["Command dengan status '{$command->status}' tidak dapat dibatalkan."],
            ]);
        }

        $command->update([
            'status' => ServiceCommand::STATUS_CANCELLED,
            'completed_at' => now(),
            'result_message' => 'Dibatalkan oleh admin melalui Xoose Control Plane.',
        ]);

        if ($actor) {
            $this->auditLog->adminAction(
                actor: $actor,
                message: "Admin membatalkan command {$command->command_id} ({$command->service_key}:{$command->action}).",
                serviceKey: $command->service_key,
                app: $command->device?->managedApp,
                device: $command->device,
                context: ['command_id' => $command->command_id],
            );
        }

        return $command->fresh();
    }

    /**
     * Menandai seluruh command pending/delivered yang sudah melewati expires_at sebagai 'expired'.
     */
    public function expireStaleCommands(): int
    {
        return ServiceCommand::whereIn('status', [ServiceCommand::STATUS_PENDING, ServiceCommand::STATUS_DELIVERED])
            ->where('expires_at', '<=', now())
            ->update([
                'status' => ServiceCommand::STATUS_EXPIRED,
                'completed_at' => now(),
                'result_message' => 'Command kedaluwarsa (TTL habis).',
            ]);
    }
}
