<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\AppDevice;
use App\Services\Remote\ConfigVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigApiController extends Controller
{
    public function __construct(
        private readonly ConfigVersionService $configVersionService,
    ) {}

    /**
     * GET /api/v1/agent/configs
     * Mengembalikan daftar konfigurasi runtime terbaru yang lebih baru dari versi lokal Agent.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var AppDevice $device */
        $device = $request->attributes->get('agent_device');
        $versions = $request->query('versions', []);
        if (! is_array($versions)) {
            $versions = [];
        }

        $configs = $this->configVersionService->resolveEffectiveConfigsForDevice($device, $versions);

        return response()->json([
            'ok' => true,
            'configs' => $configs,
        ]);
    }
}
