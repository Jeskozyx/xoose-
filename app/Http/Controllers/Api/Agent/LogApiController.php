<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Jobs\IngestAgentLogsJob;
use App\Models\AppDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogApiController extends Controller
{
    /**
     * POST /api/v1/agent/logs
     * Menerima batch log aktivitas & error lokal dari LoveYou Agent (diproses via Queue).
     */
    public function store(Request $request): JsonResponse
    {
        /** @var AppDevice $device */
        $device = $request->attributes->get('agent_device');

        $validated = $request->validate([
            'logs' => ['required', 'array', 'min:1', 'max:200'],
            'logs.*.service_key' => ['nullable', 'string', 'max:64'],
            'logs.*.level' => ['nullable', 'string', 'max:16'],
            'logs.*.event_type' => ['nullable', 'string', 'max:48'],
            'logs.*.message' => ['required', 'string', 'max:2000'],
            'logs.*.context' => ['nullable', 'array'],
            'logs.*.created_at' => ['nullable', 'string'],
        ]);

        IngestAgentLogsJob::dispatch(
            deviceId: $device->id,
            managedAppId: $device->managed_app_id,
            entries: $validated['logs'],
        );

        return response()->json([
            'ok' => true,
            'accepted' => count($validated['logs']),
        ], 202);
    }
}
