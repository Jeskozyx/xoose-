<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\AppDevice;
use App\Models\DeviceHeartbeat;
use App\Models\ServiceStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeartbeatApiController extends Controller
{
    /**
     * POST /api/v1/agent/heartbeat
     * Menerima detak jantung periodik & ringkasan status service dari LoveYou Agent.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var AppDevice $device */
        $device = $request->attributes->get('agent_device');

        $validated = $request->validate([
            'app_version' => ['nullable', 'string', 'max:32'],
            'agent_uptime' => ['nullable', 'numeric', 'min:0'],
            'services' => ['nullable', 'array'],
        ]);

        $now = now();
        $servicesSummary = is_array($validated['services'] ?? null) ? $validated['services'] : [];

        $device->update([
            'status' => AppDevice::STATUS_ONLINE,
            'app_version' => (string) ($validated['app_version'] ?? $device->app_version),
            'last_ip' => $request->ip(),
            'last_seen_at' => $now,
        ]);

        $heartbeat = DeviceHeartbeat::create([
            'device_id' => $device->id,
            'app_version' => $device->app_version,
            'agent_uptime' => (float) ($validated['agent_uptime'] ?? 0),
            'ip_address' => $request->ip(),
            'services_summary' => $servicesSummary,
            'received_at' => $now,
        ]);

        $validStatuses = config('remote-control.service_statuses', ['Running', 'Stopped', 'Paused', 'Failed', 'Unknown']);

        foreach ($servicesSummary as $serviceKey => $info) {
            if (! is_string($serviceKey) || ! is_array($info)) {
                continue;
            }
            $status = (string) ($info['status'] ?? $info['current_status'] ?? 'Unknown');
            if (! in_array($status, $validStatuses, true)) {
                $status = 'Unknown';
            }

            ServiceStatus::updateOrCreate(
                ['device_id' => $device->id, 'service_key' => substr($serviceKey, 0, 64)],
                [
                    'current_status' => $status,
                    'active_config_version' => (int) ($info['active_config_version'] ?? 0),
                    'details' => $info,
                    'reported_at' => $now,
                ]
            );
        }

        return response()->json([
            'ok' => true,
            'heartbeat_id' => $heartbeat->id,
            'status' => $device->status,
            'server_time' => $now->toIso8601String(),
        ]);
    }
}
