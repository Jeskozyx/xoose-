<?php

namespace App\Http\Controllers\Remote;

use App\Http\Controllers\Controller;
use App\Models\AppDevice;
use App\Models\ManagedApp;
use App\Models\ServiceConfig;
use App\Models\ServiceDefinition;
use App\Services\Remote\ConfigVersionService;
use App\Services\Remote\DeviceEnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServiceConfigController extends Controller
{
    public function __construct(
        private readonly ConfigVersionService $configVersionService,
        private readonly DeviceEnrollmentService $enrollmentService,
    ) {}

    /**
     * Halaman entry `/remote/configs` — otomatis mengarahkan ke scope aplikasi pertama & screenshot_service.
     */
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $this->enrollmentService->ensureDefaultServiceDefinitions();

        $firstApp = ManagedApp::where(fn ($q) => $q->where('owner_id', $user->id)->orWhereNull('owner_id'))
            ->orderByDesc('id')
            ->first();

        if (! $firstApp) {
            return view('remote.configs.edit', [
                'scope' => null,
                'managedApp' => null,
                'device' => null,
                'serviceDefinition' => null,
                'definitions' => ServiceDefinition::orderBy('id')->get(),
                'apps' => collect(),
                'devices' => collect(),
                'activeConfig' => null,
                'history' => collect(),
                'currentValues' => [],
            ]);
        }

        return redirect()->route('remote.configs.edit', [
            'scope' => "app-{$firstApp->id}",
            'service' => 'screenshot_service',
        ]);
    }

    /**
     * Memecah parameter {scope}: 'app-{id}' atau 'device-{id}'.
     *
     * @return array{app: ManagedApp, device: ?AppDevice}
     */
    private function resolveScope(string $scope): array
    {
        if (str_starts_with($scope, 'device-')) {
            $deviceId = (int) substr($scope, 7);
            $device = AppDevice::with('managedApp')->findOrFail($deviceId);
            Gate::authorize('view', $device);

            return ['app' => $device->managedApp, 'device' => $device];
        }

        $appId = str_starts_with($scope, 'app-') ? (int) substr($scope, 4) : (int) $scope;
        $app = ManagedApp::findOrFail($appId);
        Gate::authorize('view', $app);

        return ['app' => $app, 'device' => null];
    }

    public function edit(Request $request, string $scope, string $service): View
    {
        $user = $request->user();
        $this->enrollmentService->ensureDefaultServiceDefinitions();

        ['app' => $managedApp, 'device' => $device] = $this->resolveScope($scope);
        $definition = ServiceDefinition::where('service_key', $service)->firstOrFail();

        $history = ServiceConfig::with('updater')
            ->where('managed_app_id', $managedApp->id)
            ->where('service_key', $service)
            ->when($device !== null, fn ($q) => $q->where('device_id', $device->id), fn ($q) => $q->whereNull('device_id'))
            ->orderByDesc('config_version')
            ->get();

        $activeConfig = $history->firstWhere('is_active', true) ?? $history->first();

        // Gabungkan default skema dengan nilai aktif terbaru agar form mudah diisi
        $schemaProps = $definition->configuration_schema['properties'] ?? [];
        $defaults = [];
        foreach ($schemaProps as $k => $prop) {
            if (array_key_exists('default', $prop)) {
                $defaults[$k] = $prop['default'];
            }
        }
        $currentValues = array_merge($defaults, $activeConfig?->config_json ?? []);

        $apps = ManagedApp::where(fn ($q) => $q->where('owner_id', $user->id)->orWhereNull('owner_id'))
            ->orderBy('name')
            ->get();

        $devices = AppDevice::where('managed_app_id', $managedApp->id)
            ->whereNull('revoked_at')
            ->orderBy('hostname')
            ->get();

        return view('remote.configs.edit', [
            'scope' => $device ? "device-{$device->id}" : "app-{$managedApp->id}",
            'managedApp' => $managedApp,
            'device' => $device,
            'serviceDefinition' => $definition,
            'definitions' => ServiceDefinition::orderBy('id')->get(),
            'apps' => $apps,
            'devices' => $devices,
            'activeConfig' => $activeConfig,
            'history' => $history,
            'currentValues' => $currentValues,
        ]);
    }

    public function update(Request $request, string $scope, string $service): RedirectResponse
    {
        ['app' => $managedApp, 'device' => $device] = $this->resolveScope($scope);
        Gate::authorize('update', $managedApp);

        $definition = ServiceDefinition::where('service_key', $service)->firstOrFail();

        // Terima input dari raw JSON atau dari field form terstruktur sesuai skema
        $payload = [];
        if ($request->filled('raw_json')) {
            $decoded = json_decode((string) $request->input('raw_json'), true);
            if (! is_array($decoded)) {
                return back()->withInput()->with('error', 'Format JSON konfigurasi tidak valid.');
            }
            $payload = $decoded;
        } else {
            $schemaProps = $definition->configuration_schema['properties'] ?? [];
            $formConfig = $request->input('config', []);
            foreach ($schemaProps as $key => $rule) {
                $type = $rule['type'] ?? 'string';
                if ($type === 'boolean') {
                    $payload[$key] = filter_var($formConfig[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
                } elseif ($type === 'integer') {
                    if (isset($formConfig[$key]) && $formConfig[$key] !== '') {
                        $payload[$key] = (int) $formConfig[$key];
                    }
                } elseif ($type === 'number') {
                    if (isset($formConfig[$key]) && $formConfig[$key] !== '') {
                        $payload[$key] = (float) $formConfig[$key];
                    }
                } elseif ($type === 'object') {
                    $rawObj = $formConfig[$key] ?? '{}';
                    if (is_string($rawObj)) {
                        $decodedObj = json_decode($rawObj ?: '{}', true);
                        if (! is_array($decodedObj)) {
                            return back()->withInput()->with('error', "Field '{$key}' harus berupa JSON object valid.");
                        }
                        $payload[$key] = $decodedObj;
                    } elseif (is_array($rawObj)) {
                        $payload[$key] = $rawObj;
                    }
                } else {
                    if (array_key_exists($key, $formConfig)) {
                        $payload[$key] = (string) $formConfig[$key];
                    }
                }
            }
        }

        try {
            $saved = $this->configVersionService->updateConfig(
                app: $managedApp,
                serviceKey: $service,
                configJson: $payload,
                device: $device,
                actor: $request->user(),
                changeNotes: $request->input('change_notes'),
            );
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()
            ->route('remote.configs.edit', ['scope' => $scope, 'service' => $service])
            ->with('success', "Konfigurasi '{$service}' berhasil disimpan sebagai v{$saved->config_version}.");
    }

    public function rollback(Request $request, string $scope, string $service, int $version): RedirectResponse
    {
        ['app' => $managedApp, 'device' => $device] = $this->resolveScope($scope);
        Gate::authorize('update', $managedApp);

        try {
            $rolled = $this->configVersionService->rollbackToVersion(
                app: $managedApp,
                serviceKey: $service,
                targetVersion: $version,
                device: $device,
                actor: $request->user(),
            );
        } catch (ValidationException $e) {
            $firstErr = collect($e->errors())->flatten()->first() ?? 'Rollback gagal.';

            return back()->with('error', $firstErr);
        }

        return redirect()
            ->route('remote.configs.edit', ['scope' => $scope, 'service' => $service])
            ->with('success', "Rollback ke snapshot v{$version} berhasil diterbitkan sebagai v{$rolled->config_version}.");
    }
}
