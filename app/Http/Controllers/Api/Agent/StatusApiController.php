<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\AppDevice;
use App\Models\ServiceStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatusApiController extends Controller
{
    /**
     * POST /api/v1/agent/status
     * Pelaporan perubahan status granular untuk satu service pada perangkat.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var AppDevice $device */
        $device = $request->attributes->get('agent_device');

        $validStatuses = config('remote-control.service_statuses', ['Running', 'Stopped', 'Paused', 'Failed', 'Unknown']);

        $validated = $request->validate([
            'service_key' => ['required', 'string', 'max:64'],
            'current_status' => ['required', 'string', 'in:' . implode(',', $validStatuses)],
            'active_config_version' => ['nullable', 'integer', 'min:0'],
            'details' => ['nullable', 'array'],
        ]);

        $record = ServiceStatus::updateOrCreate(
            [
                'device_id' => $device->id,
                'service_key' => (string) $validated['service_key'],
            ],
            [
                'current_status' => (string) $validated['current_status'],
                'active_config_version' => (int) ($validated['active_config_version'] ?? 0),
                'details' => is_array($validated['details'] ?? null) ? $validated['details'] : [],
                'reported_at' => now(),
            ]
        );

        $device->update([
            'status' => AppDevice::STATUS_ONLINE,
            'last_seen_at' => now(),
            'last_ip' => $request->ip(),
        ]);

        return response()->json([
            'ok' => true,
            'service_status' => $record,
        ]);
    }
}
