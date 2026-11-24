<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Remote\DispatchServiceCommandRequest;
use App\Http\Requests\Remote\StoreAppReleaseRequest;
use App\Http\Requests\Remote\UpdateServiceConfigRequest;
use App\Models\AppDevice;
use App\Models\ManagedApp;
use App\Models\ServiceCommand;
use App\Models\ServiceConfig;
use App\Models\ServiceLog;
use App\Services\Remote\AuditLogService;
use App\Services\Remote\CommandDispatcherService;
use App\Services\Remote\ConfigVersionService;
use App\Services\Remote\DeviceEnrollmentService;
use App\Services\Remote\ReleaseIntegrityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ControlPlaneApiController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly DeviceEnrollmentService $enrollmentService,
        private readonly CommandDispatcherService $commandDispatcher,
        private readonly ConfigVersionService $configVersionService,
        private readonly ReleaseIntegrityService $releaseIntegrityService,
    ) {}

    public function listApps(Request $request): JsonResponse
    {
        $user = $request->user();
        $apps = ManagedApp::withCount('devices')
            ->where(fn ($q) => $q->where('owner_id', $user->id)->orWhereNull('owner_id'))
            ->orderByDesc('id')
            ->get();

        return response()->json(['ok' => true, 'apps' => $apps]);
    }

    public function storeApp(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:managed_apps,slug'],
            'app_project_id' => ['nullable', 'integer', 'exists:app_projects,id'],
            'description' => ['nullable', 'string'],
            'current_version' => ['nullable', 'string', 'max:32'],
            'default_config' => ['nullable', 'array'],
        ]);

        $this->enrollmentService->ensureDefaultServiceDefinitions();

        $slug = ! empty($validated['slug'])
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']) . '-' . strtolower(Str::random(4));

        $linkedProject = ! empty($validated['app_project_id'])
            ? \App\Models\AppProject::find($validated['app_project_id'])
            : \App\Models\AppProject::where('slug', $slug)->first();

        $defaultConfig = ! empty($validated['default_config'])
            ? $validated['default_config']
            : ($linkedProject ? $this->configVersionService->extractAllBuilderBaselines($linkedProject) : []);

        $app = ManagedApp::create([
            'app_project_id' => $linkedProject?->id,
            'owner_id' => $user->id,
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'current_version' => $validated['current_version'] ?? '1.0.0',
            'build_status' => 'idle',
            'default_config' => $defaultConfig,
            'is_active' => true,
        ]);

        $this->auditLog->adminAction(
            actor: $user,
            message: "Admin mendaftarkan ManagedApp baru '{$app->name}' ({$app->slug}).",
            serviceKey: 'app_manager',
            app: $app,
            context: ['app_project_id' => $app->app_project_id],
        );

        return response()->json(['ok' => true, 'app' => $app], 201);
    }

    public function createEnrollment(Request $request, ManagedApp $managedApp): JsonResponse
    {
        Gate::authorize('update', $managedApp);

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:500'],
            'expires_in_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
        ]);

        $enrollment = $this->enrollmentService->createEnrollmentCode(
            app: $managedApp,
            actor: $request->user(),
            label: $validated['label'] ?? null,
            maxUses: (int) ($validated['max_uses'] ?? 1),
            expiresInHours: (int) ($validated['expires_in_hours'] ?? 24),
        );

        return response()->json(['ok' => true, 'enrollment' => $enrollment], 201);
    }

    public function listDevices(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = AppDevice::with(['managedApp:id,name,slug', 'serviceStatuses'])
            ->whereHas('managedApp', fn ($q) => $q->where('owner_id', $user->id)->orWhereNull('owner_id'))
            ->orderByDesc('last_seen_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($appId = $request->query('managed_app_id')) {
            $query->where('managed_app_id', $appId);
        }

        return response()->json(['ok' => true, 'devices' => $query->get()]);
    }

    public function showDevice(Request $request, AppDevice $device): JsonResponse
    {
        Gate::authorize('view', $device);

        $device->load([
            'managedApp',
            'serviceStatuses',
            'heartbeats' => fn ($q) => $q->limit(20),
            'commands' => fn ($q) => $q->limit(20),
        ]);

        return response()->json(['ok' => true, 'device' => $device]);
    }

    public function revokeDevice(Request $request, AppDevice $device): JsonResponse
    {
        Gate::authorize('revoke', $device);

        $reason = (string) $request->input('reason', 'admin_revoked');
        $revoked = $this->enrollmentService->revokeDevice($device, actor: $request->user(), reason: $reason);

        return response()->json(['ok' => true, 'device' => $revoked]);
    }

    public function dispatchCommand(DispatchServiceCommandRequest $request, AppDevice $device): JsonResponse
    {
        Gate::authorize('control', $device);

        $validated = $request->validated();
        $command = $this->commandDispatcher->dispatch(
            device: $device,
            serviceKey: (string) $validated['service_key'],
            action: (string) $validated['action'],
            payload: $validated['payload'] ?? [],
            actor: $request->user(),
            ttlSeconds: isset($validated['ttl_seconds']) ? (int) $validated['ttl_seconds'] : null,
        );

        return response()->json([
            'ok' => true,
            'command' => $command->toAgentPayload(),
        ], 201);
    }

    public function listCommands(Request $request): JsonResponse
    {
        $user = $request->user();
        $commands = ServiceCommand::with('device:id,device_uuid,device_code,hostname,managed_app_id')
            ->whereHas('device.managedApp', fn ($q) => $q->where('owner_id', $user->id)->orWhereNull('owner_id'))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('device_id'), fn ($q, $d) => $q->where('device_id', $d))
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(['ok' => true, 'commands' => $commands]);
    }

    public function cancelCommand(Request $request, ServiceCommand $command): JsonResponse
    {
        Gate::authorize('control', $command->device);

        $cancelled = $this->commandDispatcher->cancel($command, actor: $request->user());

        return response()->json(['ok' => true, 'command' => $cancelled]);
    }

    public function listConfigs(Request $request, ManagedApp $managedApp, string $serviceKey): JsonResponse
    {
        Gate::authorize('view', $managedApp);

        $deviceId = $request->query('device_id');
        $versions = ServiceConfig::where('managed_app_id', $managedApp->id)
            ->where('service_key', $serviceKey)
            ->when($deviceId !== null, fn ($q) => $q->where('device_id', $deviceId), fn ($q) => $q->whereNull('device_id'))
            ->orderByDesc('config_version')
            ->get();

        return response()->json(['ok' => true, 'configs' => $versions]);
    }

    public function updateConfig(UpdateServiceConfigRequest $request, ManagedApp $managedApp, string $serviceKey): JsonResponse
    {
        Gate::authorize('update', $managedApp);

        $validated = $request->validated();
        $device = null;
        if (! empty($validated['device_id'])) {
            $device = AppDevice::where('managed_app_id', $managedApp->id)->findOrFail($validated['device_id']);
        }

        $config = $this->configVersionService->updateConfig(
            app: $managedApp,
            serviceKey: $serviceKey,
            configJson: $validated['config_json'],
            device: $device,
            actor: $request->user(),
            changeNotes: $validated['change_notes'] ?? null,
        );

        return response()->json(['ok' => true, 'config' => $config], 201);
    }

    public function rollbackConfig(Request $request, ManagedApp $managedApp, string $serviceKey, int $version): JsonResponse
    {
        Gate::authorize('update', $managedApp);

        $device = null;
        if ($request->filled('device_id')) {
            $device = AppDevice::where('managed_app_id', $managedApp->id)->findOrFail($request->input('device_id'));
        }

        $rolledBack = $this->configVersionService->rollbackToVersion(
            app: $managedApp,
            serviceKey: $serviceKey,
            targetVersion: $version,
            device: $device,
            actor: $request->user(),
        );

        return response()->json(['ok' => true, 'config' => $rolledBack]);
    }

    public function listLogs(Request $request): JsonResponse
    {
        $user = $request->user();
        $logs = ServiceLog::where(function ($q) use ($user) {
            $q->whereHas('managedApp', fn ($aq) => $aq->where('owner_id', $user->id)->orWhereNull('owner_id'))
                ->orWhere('actor_user_id', $user->id);
        })
            ->when($request->query('event_type'), fn ($q, $t) => $q->where('event_type', $t))
            ->when($request->query('level'), fn ($q, $l) => $q->where('level', $l))
            ->when($request->query('service_key'), fn ($q, $s) => $q->where('service_key', $s))
            ->when($request->query('device_id'), fn ($q, $d) => $q->where('device_id', $d))
            ->orderByDesc('id')
            ->limit(150)
            ->get();

        return response()->json(['ok' => true, 'logs' => $logs]);
    }

    public function listReleases(Request $request, ManagedApp $managedApp): JsonResponse
    {
        Gate::authorize('view', $managedApp);

        return response()->json([
            'ok' => true,
            'releases' => $managedApp->releases()->get(),
        ]);
    }

    public function storeRelease(StoreAppReleaseRequest $request, ManagedApp $managedApp): JsonResponse
    {
        Gate::authorize('update', $managedApp);

        $release = $this->releaseIntegrityService->createRelease(
            app: $managedApp,
            data: $request->validated(),
            actor: $request->user(),
        );

        return response()->json(['ok' => true, 'release' => $release], 201);
    }
}
