<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\AppDevice;
use App\Models\ServiceCommand;
use App\Services\Remote\CommandDispatcherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommandApiController extends Controller
{
    public function __construct(
        private readonly CommandDispatcherService $dispatcher,
    ) {}

    /**
     * GET /api/v1/agent/commands/pending
     * Mengambil daftar command aktif untuk perangkat ini dan menandainya 'delivered'.
     */
    public function pending(Request $request): JsonResponse
    {
        /** @var AppDevice $device */
        $device = $request->attributes->get('agent_device');

        $commands = $this->dispatcher->fetchPendingForDevice($device);

        return response()->json([
            'ok' => true,
            'commands' => $commands->map(fn (ServiceCommand $cmd) => $cmd->toAgentPayload())->values(),
        ]);
    }

    /**
     * POST /api/v1/agent/commands/{command}/ack
     * Menerima acknowledgment (acknowledged/succeeded/failed/expired) dari LoveYou Agent.
     */
    public function acknowledge(Request $request, ServiceCommand $command): JsonResponse
    {
        /** @var AppDevice $device */
        $device = $request->attributes->get('agent_device');

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:acknowledged,succeeded,failed,expired'],
            'result_message' => ['nullable', 'string', 'max:2000'],
            'result_payload' => ['nullable', 'array'],
        ]);

        $updated = $this->dispatcher->acknowledge(
            device: $device,
            command: $command,
            status: (string) $validated['status'],
            resultMessage: (string) ($validated['result_message'] ?? ''),
            resultPayload: is_array($validated['result_payload'] ?? null) ? $validated['result_payload'] : [],
        );

        return response()->json([
            'ok' => true,
            'command_id' => $updated->command_id,
            'status' => $updated->status,
            'acknowledged_at' => $updated->acknowledged_at?->toIso8601String(),
            'completed_at' => $updated->completed_at?->toIso8601String(),
        ]);
    }
}
