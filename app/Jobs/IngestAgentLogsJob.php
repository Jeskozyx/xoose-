<?php

namespace App\Jobs;

use App\Models\AppDevice;
use App\Models\ServiceLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class IngestAgentLogsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    public function __construct(
        public readonly int $deviceId,
        public readonly int $managedAppId,
        public readonly array $entries,
    ) {}

    public function handle(): void
    {
        if (empty($this->entries) || ! AppDevice::whereKey($this->deviceId)->exists()) {
            return;
        }

        $allowedLevels = ['debug', 'info', 'warning', 'error', 'critical'];
        $rows = [];

        foreach ($this->entries as $entry) {
            if (! is_array($entry) || empty($entry['message'])) {
                continue;
            }
            $level = strtolower((string) ($entry['level'] ?? 'info'));
            if (! in_array($level, $allowedLevels, true)) {
                $level = 'info';
            }

            $createdAt = now();
            if (! empty($entry['created_at'])) {
                try {
                    $createdAt = Carbon::parse((string) $entry['created_at']);
                } catch (\Throwable) {
                    $createdAt = now();
                }
            }

            $rows[] = [
                'managed_app_id' => $this->managedAppId,
                'device_id' => $this->deviceId,
                'actor_user_id' => null,
                'service_key' => substr((string) ($entry['service_key'] ?? 'agent'), 0, 64),
                'level' => $level,
                'event_type' => substr((string) ($entry['event_type'] ?? 'agent_event'), 0, 48),
                'message' => (string) $entry['message'],
                'context' => json_encode(is_array($entry['context'] ?? null) ? $entry['context'] : []),
                'created_at' => $createdAt,
            ];
        }

        if (! empty($rows)) {
            ServiceLog::insert($rows);
        }
    }
}
