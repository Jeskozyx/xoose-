<?php

namespace App\Jobs;

use App\Models\AppDevice;
use App\Services\Remote\AuditLogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DetectOfflineDevicesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function handle(AuditLogService $auditLog): void
    {
        $timeout = (int) config('remote-control.heartbeat_timeout_seconds', 90);
        $cutoff = now()->subSeconds(max(10, $timeout));

        $staleDevices = AppDevice::where('status', AppDevice::STATUS_ONLINE)
            ->whereNull('revoked_at')
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', $cutoff);
            })
            ->get();

        foreach ($staleDevices as $device) {
            $device->update(['status' => AppDevice::STATUS_OFFLINE]);

            $auditLog->record(
                eventType: 'heartbeat_timeout',
                message: "Perangkat '{$device->hostname}' ({$device->device_code}) ditandai Offline karena melewati batas heartbeat ({$timeout}s).",
                level: 'warning',
                serviceKey: 'agent',
                device: $device,
                context: [
                    'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                    'timeout_seconds' => $timeout,
                ],
            );
        }
    }
}
