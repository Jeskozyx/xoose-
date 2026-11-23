<?php

namespace App\Http\Controllers\Remote;

use App\Http\Controllers\Controller;
use App\Http\Requests\Remote\UpdateCaptureRulesRequest;
use App\Models\AppDevice;
use App\Models\ManagedApp;
use App\Services\Remote\CaptureRuleConfigService;
use App\Services\Remote\DeviceEnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CaptureRuleController extends Controller
{
    public function __construct(
        private readonly CaptureRuleConfigService $captureRules,
        private readonly DeviceEnrollmentService $enrollmentService,
    ) {}

    private function isFeatureEnabled(): bool
    {
        return (bool) config('remote-control.capture_rules_enabled', false);
    }

    private function blockedResponse(?string $scope = null): RedirectResponse
    {
        $params = $scope ? ['scope' => $scope] : [];

        return redirect()
            ->route('remote.capture-rules.index', $params)
            ->with('error', 'Fitur Capture Rules (Beta) sedang dinonaktifkan sementara. Ubah REMOTE_CAPTURE_RULES_ENABLED=true di file .env jika ingin mengaktifkannya kembali.');
    }

    /**
     * Memecah parameter scope ('app-{id}' atau 'device-{id}') sekaligus menegakkan Policy otorisasi.
     *
     * @return array{app: ManagedApp, device: ?AppDevice, scope: string}
     */
    private function resolveScope(string $scope, string $ability = 'view'): array
    {
        if (str_starts_with($scope, 'device-')) {
            $deviceId = (int) substr($scope, 7);
            $device = AppDevice::with('managedApp')->findOrFail($deviceId);
            Gate::authorize($ability, $device);

            return [
                'app' => $device->managedApp,
                'device' => $device,
                'scope' => "device-{$device->id}",
            ];
        }

        $appId = str_starts_with($scope, 'app-') ? (int) substr($scope, 4) : (int) $scope;
        $app = ManagedApp::findOrFail($appId);
        Gate::authorize($ability, $app);

        return [
            'app' => $app,
            'device' => null,
            'scope' => "app-{$app->id}",
        ];
    }

    /**
     * GET /remote/capture-rules
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $this->enrollmentService->ensureDefaultServiceDefinitions();

        $apps = ManagedApp::where(fn ($q) => $q->where('owner_id', $user->id)->orWhereNull('owner_id'))
            ->orderByDesc('id')
            ->get();

        $featureEnabled = $this->isFeatureEnabled();
        $requestedScope = (string) $request->query('scope', '');
        if ($requestedScope !== '') {
            ['app' => $managedApp, 'device' => $device, 'scope' => $scope] = $this->resolveScope($requestedScope, 'view');
        } elseif ($apps->isEmpty()) {
            return view('remote.capture-rules.index', [
                'featureEnabled' => $featureEnabled,
                'apps' => $apps,
                'devices' => collect(),
                'managedApp' => null,
                'device' => null,
                'scope' => null,
                'config' => CaptureRuleConfigService::defaultConfig(),
                'activeRecord' => null,
                'history' => collect(),
                'deviceStatuses' => collect(),
            ]);
        } else {
            $managedApp = $apps->first();
            $device = null;
            $scope = "app-{$managedApp->id}";
        }

        $devices = AppDevice::where('managed_app_id', $managedApp->id)
            ->whereNull('revoked_at')
            ->orderBy('hostname')
            ->get();

        return view('remote.capture-rules.index', [
            'featureEnabled' => $featureEnabled,
            'apps' => $apps,
            'devices' => $devices,
            'managedApp' => $managedApp,
            'device' => $device,
            'scope' => $scope,
            'config' => $this->captureRules->getEffectiveConfig($managedApp, $device),
            'activeRecord' => $this->captureRules->getActiveRecord($managedApp, $device),
            'history' => $this->captureRules->getHistory($managedApp, $device),
            'deviceStatuses' => $this->captureRules->getLiveDeviceStatuses($managedApp, $device),
        ]);
    }

    /**
     * POST /remote/capture-rules/policy
     * Mengubah sumber capture aktif secara live (browser_domain / application_window / desktop_mss / disabled).
     */
    public function updatePolicy(UpdateCaptureRulesRequest $request): RedirectResponse
    {
        if (! $this->isFeatureEnabled()) {
            return $this->blockedResponse((string) $request->input('scope', ''));
        }

        $validated = $request->validated();
        ['app' => $app, 'device' => $device, 'scope' => $scope] = $this->resolveScope($validated['scope'], 'update');

        $payload = [
            'active_source' => $validated['active_source'],
            'allow_live_switch' => $request->boolean('allow_live_switch', true),
            'permission_mode' => $validated['permission_mode'] ?? 'local_start',
            'image_format' => $validated['image_format'] ?? 'webp',
            'fallback_format' => $validated['fallback_format'] ?? 'png',
            'capture_interval_seconds' => (int) ($validated['capture_interval_seconds'] ?? 10),
            'window_interval_seconds' => (int) ($validated['window_interval_seconds'] ?? 10),
            'local_session_required' => $request->boolean('local_session_required', true),
            'pause_when_browser_unfocused' => $request->boolean('pause_when_browser_unfocused', true),
            'pause_when_tab_inactive' => $request->boolean('pause_when_tab_inactive', true),
            'change_notes' => $validated['change_notes'] ?? null,
        ];

        if ($request->has('browser_enabled')) {
            $payload['browser_enabled'] = $request->boolean('browser_enabled');
        }
        if ($request->has('window_enabled')) {
            $payload['window_enabled'] = $request->boolean('window_enabled');
        }

        try {
            $saved = $this->captureRules->updatePolicy($app, $payload, $device, $request->user());
        } catch (ValidationException $e) {
            return redirect()
                ->route('remote.capture-rules.index', ['scope' => $scope])
                ->withInput()
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('remote.capture-rules.index', ['scope' => $scope])
            ->with('success', "Kebijakan live capture diperbarui ke '{$validated['active_source']}' (diterbitkan sebagai v{$saved->config_version}).");
    }

    /**
     * POST /remote/capture-rules/rules
     */
    public function storeRule(UpdateCaptureRulesRequest $request): RedirectResponse
    {
        if (! $this->isFeatureEnabled()) {
            return $this->blockedResponse((string) $request->input('scope', ''));
        }

        $validated = $request->validated();
        ['app' => $app, 'device' => $device, 'scope' => $scope] = $this->resolveScope($validated['scope'], 'update');

        $ruleType = (string) ($validated['rule_type'] ?? 'browser');
        $payload = array_merge($validated, [
            'rule_type' => $ruleType,
            'enabled' => $request->boolean('enabled', true),
            'include_subdomains' => $request->boolean('include_subdomains', true),
        ]);

        try {
            $saved = $this->captureRules->storeRule($app, $payload, $device, $request->user());
        } catch (ValidationException $e) {
            return redirect()
                ->route('remote.capture-rules.index', ['scope' => $scope])
                ->withInput()
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('remote.capture-rules.index', ['scope' => $scope])
            ->with('success', "Aturan capture baru berhasil ditambahkan dan diterbitkan secara live (v{$saved->config_version}).");
    }

    /**
     * PUT /remote/capture-rules/rules/{ruleId}
     */
    public function updateRule(UpdateCaptureRulesRequest $request, string $ruleId): RedirectResponse
    {
        if (! $this->isFeatureEnabled()) {
            return $this->blockedResponse((string) $request->input('scope', ''));
        }

        $validated = $request->validated();
        ['app' => $app, 'device' => $device, 'scope' => $scope] = $this->resolveScope($validated['scope'], 'update');

        $payload = $validated;
        $payload['rule_type'] = (string) ($validated['rule_type'] ?? 'browser');
        if ($request->has('enabled')) {
            $payload['enabled'] = $request->boolean('enabled');
        }
        if ($request->has('include_subdomains')) {
            $payload['include_subdomains'] = $request->boolean('include_subdomains');
        }

        try {
            $saved = $this->captureRules->updateRule($app, $ruleId, $payload, $device, $request->user());
        } catch (ValidationException $e) {
            return redirect()
                ->route('remote.capture-rules.index', ['scope' => $scope])
                ->withInput()
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('remote.capture-rules.index', ['scope' => $scope])
            ->with('success', "Aturan '{$ruleId}' diperbarui secara live (v{$saved->config_version}).");
    }

    /**
     * DELETE /remote/capture-rules/rules/{ruleId}
     */
    public function destroyRule(Request $request, string $ruleId): RedirectResponse
    {
        $scopeParam = (string) $request->input('scope', '');
        if (! $this->isFeatureEnabled()) {
            return $this->blockedResponse($scopeParam);
        }

        ['app' => $app, 'device' => $device, 'scope' => $scope] = $this->resolveScope($scopeParam, 'update');
        $ruleType = $request->input('rule_type') === 'window' ? 'window' : 'browser';

        try {
            $saved = $this->captureRules->destroyRule($app, $ruleId, $ruleType, $device, $request->user());
        } catch (ValidationException $e) {
            return redirect()
                ->route('remote.capture-rules.index', ['scope' => $scope])
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('remote.capture-rules.index', ['scope' => $scope])
            ->with('success', "Aturan '{$ruleId}' dihapus dan diterbitkan sebagai v{$saved->config_version}.");
    }

    /**
     * POST /remote/capture-rules/rollback/{version}
     */
    public function rollback(Request $request, int $version): RedirectResponse
    {
        $scopeParam = (string) $request->input('scope', '');
        if (! $this->isFeatureEnabled()) {
            return $this->blockedResponse($scopeParam);
        }

        ['app' => $app, 'device' => $device, 'scope' => $scope] = $this->resolveScope($scopeParam, 'update');

        try {
            $rolled = $this->captureRules->rollback($app, $version, $device, $request->user());
        } catch (ValidationException $e) {
            return redirect()
                ->route('remote.capture-rules.index', ['scope' => $scope])
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('remote.capture-rules.index', ['scope' => $scope])
            ->with('success', "Konfigurasi capture berhasil di-rollback ke snapshot v{$version} (diterbitkan sebagai v{$rolled->config_version}).");
    }
}
