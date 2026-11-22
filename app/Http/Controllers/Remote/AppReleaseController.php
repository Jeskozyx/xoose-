<?php

namespace App\Http\Controllers\Remote;

use App\Http\Controllers\Controller;
use App\Models\AppRelease;
use App\Models\ManagedApp;
use App\Services\Remote\AuditLogService;
use App\Services\Remote\ReleaseIntegrityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppReleaseController extends Controller
{
    public function __construct(
        private readonly ReleaseIntegrityService $releaseIntegrityService,
        private readonly AuditLogService $auditLog,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $appFilter = $request->query('managed_app_id');

        $apps = ManagedApp::where(fn ($q) => $q->where('owner_id', $user->id)->orWhereNull('owner_id'))
            ->orderBy('name')
            ->get();

        $releases = AppRelease::with(['managedApp:id,name,slug', 'publisher:id,name,email'])
            ->whereHas('managedApp', fn ($q) => $q->where('owner_id', $user->id)->orWhereNull('owner_id'))
            ->when($appFilter, fn ($q) => $q->where('managed_app_id', $appFilter))
            ->orderByDesc('id')
            ->get();

        return view('remote.releases.index', [
            'apps' => $apps,
            'releases' => $releases,
            'selectedAppId' => $appFilter,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'managed_app_id' => ['required', 'integer', 'exists:managed_apps,id'],
            'version' => ['required', 'string', 'max:32'],
            'artifact_path' => ['required', 'string', 'max:500'],
            'artifact_url' => ['nullable', 'string', 'max:1000'],
            'sha256_checksum' => ['required', 'string', 'size:64'],
            'release_notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $app = ManagedApp::findOrFail($validated['managed_app_id']);
        Gate::authorize('update', $app);

        try {
            $release = $this->releaseIntegrityService->createRelease(
                app: $app,
                data: [
                    'version' => $validated['version'],
                    'artifact_path' => $validated['artifact_path'],
                    'artifact_url' => $validated['artifact_url'] ?? null,
                    'sha256_checksum' => $validated['sha256_checksum'],
                    'release_notes' => $validated['release_notes'] ?? null,
                    'is_active' => $request->boolean('is_active', true),
                ],
                actor: $request->user(),
            );
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()
            ->route('remote.releases.index')
            ->with('success', "Release v{$release->version} untuk '{$app->name}' berhasil diterbitkan dan ditandatangani (HMAC-SHA256).");
    }

    public function activate(Request $request, AppRelease $release): RedirectResponse
    {
        $app = $release->managedApp;
        Gate::authorize('update', $app);

        DB::transaction(function () use ($app, $release) {
            AppRelease::where('managed_app_id', $app->id)->update(['is_active' => false]);
            $release->update(['is_active' => true, 'published_at' => $release->published_at ?? now()]);
            $app->update(['current_version' => $release->version]);
        });

        $this->auditLog->adminAction(
            actor: $request->user(),
            message: "Admin mengaktifkan release v{$release->version} sebagai versi aktif '{$app->name}'.",
            serviceKey: 'release',
            app: $app,
            context: ['release_id' => $release->id, 'version' => $release->version],
        );

        return back()->with('success', "Release v{$release->version} kini menjadi versi aktif untuk '{$app->name}'.");
    }
}
