<?php

namespace App\Http\Controllers\Remote;

use App\Http\Controllers\Controller;
use App\Models\AppDevice;
use App\Models\ManagedApp;
use App\Models\ServiceDefinition;
use App\Services\Remote\DeviceEnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AppDeviceController extends Controller
{
    public function __construct(
        private readonly DeviceEnrollmentService $enrollmentService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $statusFilter = $request->query('status');
        $appFilter = $request->query('managed_app_id');
        $search = trim((string) $request->query('q', ''));

        $apps = ManagedApp::where(fn ($q) => $q->where('owner_id', $user->id)->orWhereNull('owner_id'))
            ->orderBy('name')
            ->get();

        $devices = AppDevice::with(['managedApp', 'serviceStatuses', 'latestHeartbeat'])
            ->whereHas('managedApp', fn ($q) => $q->where('owner_id', $user->id)->orWhereNull('owner_id'))
            ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
            ->when($appFilter, fn ($q) => $q->where('managed_app_id', $appFilter))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('hostname', 'like', "%{$search}%")
                        ->orWhere('device_code', 'like', "%{$search}%")
                        ->orWhere('device_uuid', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->get();

        $stats = [
            'total' => $devices->count(),
            'online' => $devices->where('status', AppDevice::STATUS_ONLINE)->count(),
            'offline' => $devices->where('status', AppDevice::STATUS_OFFLINE)->count(),
            'revoked' => $devices->where('status', AppDevice::STATUS_REVOKED)->count(),
        ];

        return view('remote.devices.index', [
            'devices' => $devices,
            'apps' => $apps,
            'stats' => $stats,
            'filters' => [
                'status' => $statusFilter,
                'managed_app_id' => $appFilter,
                'q' => $search,
            ],
        ]);
    }

    public function show(Request $request, AppDevice $device): View
    {
        Gate::authorize('view', $device);
        $this->enrollmentService->ensureDefaultServiceDefinitions();

        $device->load([
            'managedApp',
            'serviceStatuses',
            'heartbeats' => fn ($q) => $q->limit(15),
            'commands' => fn ($q) => $q->with('issuer')->limit(15),
            'logs' => fn ($q) => $q->limit(15),
        ]);

        $definitions = ServiceDefinition::orderBy('id')->get();
        $statusMap = $device->serviceStatuses->keyBy('service_key');

        return view('remote.devices.show', [
            'device' => $device,
            'definitions' => $definitions,
            'statusMap' => $statusMap,
        ]);
    }

    public function revoke(Request $request, AppDevice $device): RedirectResponse
    {
        Gate::authorize('revoke', $device);

        $reason = trim((string) $request->input('reason', 'admin_revoked_from_dashboard')) ?: 'admin_revoked_from_dashboard';
        $this->enrollmentService->revokeDevice($device, actor: $request->user(), reason: $reason);

        return back()->with('success', "Akses perangkat '{$device->hostname}' ({$device->device_code}) telah dicabut.");
    }
}
