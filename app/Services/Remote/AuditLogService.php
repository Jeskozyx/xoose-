<?php

namespace App\Services\Remote;

use App\Models\AppDevice;
use App\Models\ManagedApp;
use App\Models\ServiceLog;
use App\Models\User;

class AuditLogService
{
    public function record(
        string $eventType,
        string $message,
        string $level = 'info',
        string $serviceKey = 'system',
        ?ManagedApp $app = null,
        ?AppDevice $device = null,
        ?User $actor = null,
        array $context = []
    ): ServiceLog {
        return ServiceLog::create([
            'managed_app_id' => $app?->id ?? $device?->managed_app_id,
            'device_id' => $device?->id,
            'actor_user_id' => $actor?->id,
            'service_key' => $serviceKey,
            'level' => $level,
            'event_type' => $eventType,
            'message' => $message,
            'context' => $context,
            'created_at' => now(),
        ]);
    }

    public function adminAction(
        User $actor,
        string $message,
        string $serviceKey = 'system',
        ?ManagedApp $app = null,
        ?AppDevice $device = null,
        array $context = [],
        string $level = 'info'
    ): ServiceLog {
        return $this->record(
            eventType: 'admin_audit',
            message: $message,
            level: $level,
            serviceKey: $serviceKey,
            app: $app,
            device: $device,
            actor: $actor,
            context: $context,
        );
    }
}
