<?php

namespace App\Http\Controllers\Remote;

use App\Http\Controllers\Controller;
use App\Jobs\BuildApplicationJob;
use App\Models\AppDevice;
use App\Models\AppProject;
use App\Models\AppRelease;
use App\Models\DeviceEnrollment;
use App\Models\DeviceHeartbeat;
use App\Models\ManagedApp;
use App\Models\ServiceCommand;
use App\Models\ServiceConfig;
use App\Models\ServiceLog;
use App\Models\ServiceStatus;
use App\Services\BuildService;
use App\Services\Remote\AuditLogService;
use App\Services\Remote\DeviceEnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ManagedAppController extends Controller
{
    public function __construct(
        private readonly DeviceEnrollmentService $enrollmentService,
        private readonly AuditLogService $auditLog,
        private readonly BuildService $buildService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->enrollmentService->ensureDefaultServiceDefinitions();

        $apps = ManagedApp::with(['project.latestBuild', 'activeRelease', 'enrollments' => fn ($q) => $q->latest()->limit(5)])
            ->withCount([
                'devices',
                'devices as online_devices_count' => fn ($q) => $q->where('status', AppDevice::STATUS_ONLINE)->whereNull('revoked_at'),
                'releases',
            ])
            ->where(fn ($q) => $q->where('owner_id', $user->id)->orWhereNull('owner_id'))
            ->orderByDesc('id')
            ->get();

        $availableProjects = AppProject::orderByDesc('id')->get();

        return view('remote.apps.index', [
            'apps' => $apps,
            'availableProjects' => $availableProjects,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:managed_apps,slug'],
            'app_project_id' => ['nullable', 'integer', 'exists:app_projects,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'current_version' => ['nullable', 'string', 'max:32'],
        ]);

        $this->enrollmentService->ensureDefaultServiceDefinitions();

        $slug = ! empty($validated['slug'])
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']) . '-' . strtolower(Str::random(4));

        $app = ManagedApp::create([
            'app_project_id' => ! empty($validated['app_project_id']) ? $validated['app_project_id'] : null,
            'owner_id' => $user->id,
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'current_version' => ! empty($validated['current_version']) ? $validated['current_version'] : '1.0.0',
            'build_status' => 'idle',
            'default_config' => [],
            'is_active' => true,
        ]);

        $this->auditLog->adminAction(
            actor: $user,
            message: "Admin membuat ManagedApp '{$app->name}' ({$app->slug}) melalui Dashboard.",
            serviceKey: 'app_manager',
            app: $app,
        );

        return redirect()
            ->route('remote.apps.index')
            ->with('success', "Aplikasi '{$app->name}' berhasil didaftarkan ke Remote Service Manager.");
    }

    public function createEnrollment(Request $request, ManagedApp $managedApp): RedirectResponse
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
            label: $validated['label'] ?? 'Enrollment Dashboard',
            maxUses: (int) ($validated['max_uses'] ?? 1),
            expiresInHours: (int) ($validated['expires_in_hours'] ?? 24),
        );

        return redirect()
            ->route('remote.apps.index')
            ->with('success', "Kode enrollment baru untuk '{$managedApp->name}': {$enrollment->enrollment_code}")
            ->with('generated_enrollment_code', $enrollment->enrollment_code);
    }

    public function triggerBuild(Request $request, ManagedApp $managedApp): RedirectResponse
    {
        Gate::authorize('update', $managedApp);

        if (! $managedApp->project) {
            return redirect()
                ->route('remote.apps.index')
                ->with('error', "Aplikasi '{$managedApp->name}' belum terhubung ke AppProject Builder.");
        }

        $format = in_array($request->input('format'), ['onedir', 'onefile'], true)
            ? (string) $request->input('format')
            : 'onedir';

        $build = $this->buildService->createBuild($managedApp->project, $format);
        $managedApp->update(['build_status' => 'queued']);

        BuildApplicationJob::dispatch($build);

        $this->auditLog->adminAction(
            actor: $request->user(),
            message: "Admin memicu build EXE (#{$build->id}, format {$format}) untuk '{$managedApp->name}'.",
            serviceKey: 'build_engine',
            app: $managedApp,
            context: ['build_id' => $build->id, 'format' => $format],
        );

        return redirect()
            ->route('remote.apps.index')
            ->with('success', "Build EXE #{$build->id} ({$format}) untuk '{$managedApp->name}' telah dijadwalkan ke queue.");
    }

    public function destroy(Request $request, ManagedApp $managedApp): RedirectResponse
    {
        Gate::authorize('delete', $managedApp);

        $appName = $managedApp->name;
        $appSlug = $managedApp->slug;

        $stats = DB::transaction(function () use ($managedApp): array {
            $deviceIds = $managedApp->devices()->pluck('id');

            if ($deviceIds->isNotEmpty()) {
                DeviceHeartbeat::whereIn('device_id', $deviceIds)->delete();
                ServiceStatus::whereIn('device_id', $deviceIds)->delete();
                ServiceCommand::whereIn('device_id', $deviceIds)->delete();
            }

            $logsDeleted = ServiceLog::where('managed_app_id', $managedApp->id)
                ->when($deviceIds->isNotEmpty(), fn ($q) => $q->orWhereIn('device_id', $deviceIds))
                ->delete();

            ServiceConfig::where('managed_app_id', $managedApp->id)
                ->update(['previous_version_id' => null]);
            ServiceConfig::where('managed_app_id', $managedApp->id)->delete();

            $enrollmentsDeleted = DeviceEnrollment::where('managed_app_id', $managedApp->id)->delete();
            AppRelease::where('managed_app_id', $managedApp->id)->delete();
            $devicesDeleted = AppDevice::where('managed_app_id', $managedApp->id)->delete();

            $managedApp->delete();

            return [
                'devices' => $devicesDeleted,
                'enrollments' => $enrollmentsDeleted,
                'logs' => $logsDeleted,
            ];
        });

        return redirect()
            ->route('remote.apps.index')
            ->with(
                'success',
                "Aplikasi '{$appName}' ({$appSlug}) berhasil dihapus beserta {$stats['devices']} perangkat, {$stats['enrollments']} kode enrollment, dan {$stats['logs']} log terkait."
            );
    }

    public function resetAll(Request $request): RedirectResponse
    {
        $user = $request->user();

        $stats = DB::transaction(function () use ($user): array {
            $appIds = ManagedApp::where(fn ($q) => $q->where('owner_id', $user->id)->orWhereNull('owner_id'))
                ->pluck('id');

            $deviceIds = $appIds->isNotEmpty()
                ? AppDevice::whereIn('managed_app_id', $appIds)->pluck('id')
                : collect();

            $commandsDeleted = 0;
            if ($deviceIds->isNotEmpty()) {
                DeviceHeartbeat::whereIn('device_id', $deviceIds)->delete();
                ServiceStatus::whereIn('device_id', $deviceIds)->delete();
                $commandsDeleted = ServiceCommand::whereIn('device_id', $deviceIds)->delete();
            }

            $logsDeleted = ServiceLog::where(function ($q) use ($appIds, $deviceIds, $user) {
                $q->where(function ($sub) use ($user) {
                    $sub->whereNull('managed_app_id')
                        ->where('actor_user_id', $user->id);
                });

                if ($appIds->isNotEmpty()) {
                    $q->orWhereIn('managed_app_id', $appIds);
                }

                if ($deviceIds->isNotEmpty()) {
                    $q->orWhereIn('device_id', $deviceIds);
                }
            })->delete();

            $enrollmentsDeleted = 0;
            $devicesDeleted = 0;
            $appsDeleted = 0;

            if ($appIds->isNotEmpty()) {
                ServiceConfig::whereIn('managed_app_id', $appIds)
                    ->update(['previous_version_id' => null]);
                ServiceConfig::whereIn('managed_app_id', $appIds)->delete();
                $enrollmentsDeleted = DeviceEnrollment::whereIn('managed_app_id', $appIds)->delete();
                AppRelease::whereIn('managed_app_id', $appIds)->delete();
                $devicesDeleted = AppDevice::whereIn('managed_app_id', $appIds)->delete();
                $appsDeleted = ManagedApp::whereIn('id', $appIds)->delete();
            }

            return [
                'apps' => $appsDeleted,
                'devices' => $devicesDeleted,
                'enrollments' => $enrollmentsDeleted,
                'commands' => $commandsDeleted,
                'logs' => $logsDeleted,
            ];
        });

        $totalRemoved = array_sum($stats);
        if ($totalRemoved === 0) {
            return redirect()
                ->route('remote.apps.index')
                ->with('success', 'Data Remote Service Manager sudah bersih (tidak ada data uji coba yang perlu dihapus).');
        }

        return redirect()
            ->route('remote.apps.index')
            ->with(
                'success',
                "Reset Fresh selesai! Dihapus: {$stats['apps']} aplikasi, {$stats['devices']} perangkat, {$stats['enrollments']} kode enrollment, {$stats['commands']} riwayat command, dan {$stats['logs']} log. Akun Admin & proyek App Builder tetap aman."
            );
    }
}
