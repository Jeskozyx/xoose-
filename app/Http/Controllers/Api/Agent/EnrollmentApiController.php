<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Remote\AgentEnrollRequest;
use App\Models\AppDevice;
use App\Services\Remote\DeviceEnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnrollmentApiController extends Controller
{
    public function __construct(
        private readonly DeviceEnrollmentService $enrollmentService,
    ) {}

    /**
     * POST /api/v1/agent/enroll
     * Mendaftarkan perangkat baru berdasarkan enrollment_code dan menerbitkan token unik revocable.
     */
    public function store(AgentEnrollRequest $request): JsonResponse
    {
        $result = $this->enrollmentService->enrollDevice(
            data: $request->validated(),
            ipAddress: $request->ip(),
        );

        /** @var AppDevice $device */
        $device = $result['device'];

        return response()->json([
            'ok' => true,
            'device_id' => $device->device_uuid,
            'device_code' => $device->device_code,
            'device_token' => $result['device_token'],
            'managed_app' => [
                'id' => $device->managedApp?->id,
                'slug' => $device->managedApp?->slug,
                'name' => $device->managedApp?->name,
            ],
            'heartbeat_timeout_seconds' => (int) config('remote-control.heartbeat_timeout_seconds', 90),
        ], 201);
    }

    /**
     * POST /api/v1/agent/unregister
     * Pencabutan akses perangkat secara lokal oleh user Windows (misal saat uninstall).
     */
    public function revokeSelf(Request $request): JsonResponse
    {
        /** @var AppDevice $device */
        $device = $request->attributes->get('agent_device');
        $reason = (string) $request->input('reason', 'local_user_unregister');

        $this->enrollmentService->revokeDevice($device, actor: null, reason: $reason);

        return response()->json([
            'ok' => true,
            'message' => 'Akses perangkat telah dicabut secara permanen.',
            'device_id' => $device->device_uuid,
        ]);
    }
}
