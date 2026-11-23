<?php

namespace App\Services\Remote;

use App\Models\AppDevice;
use App\Models\ManagedApp;
use App\Models\ServiceConfig;
use App\Models\ServiceStatus;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CaptureRuleConfigService
{
    public const SERVICE_KEY = 'browser_capture_service';

    public function __construct(
        private readonly ConfigVersionService $configVersionService,
        private readonly DeviceEnrollmentService $enrollmentService,
    ) {}

    /**
     * Default konfigurasi aman (browser_capture.enabled = false).
     *
     * @return array<string, mixed>
     */
    public static function defaultConfig(): array
    {
        return [
            'capture_policy' => [
                'active_source' => 'desktop_mss',
                'allow_live_switch' => true,
            ],
            'browser_capture' => [
                'enabled' => false,
                'capture_mode' => 'active_tab',
                'permission_mode' => 'local_start',
                'local_session_required' => true,
                'pause_when_browser_unfocused' => true,
                'pause_when_tab_inactive' => true,
                'image_format' => 'webp',
                'fallback_format' => 'png',
                'capture_interval_seconds' => 10,
                'rules' => [],
            ],
            'window_capture' => [
                'enabled' => false,
                'capture_mode' => 'active_window',
                'capture_interval_seconds' => 10,
                'rules' => [],
            ],
        ];
    }

    public function getHistory(ManagedApp $app, ?AppDevice $device = null): Collection
    {
        return ServiceConfig::with('updater:id,name,email')
            ->where('managed_app_id', $app->id)
            ->where('service_key', self::SERVICE_KEY)
            ->when($device !== null, fn ($q) => $q->where('device_id', $device->id), fn ($q) => $q->whereNull('device_id'))
            ->orderByDesc('config_version')
            ->get();
    }

    public function getActiveRecord(ManagedApp $app, ?AppDevice $device = null): ?ServiceConfig
    {
        return ServiceConfig::where('managed_app_id', $app->id)
            ->where('service_key', self::SERVICE_KEY)
            ->when($device !== null, fn ($q) => $q->where('device_id', $device->id), fn ($q) => $q->whereNull('device_id'))
            ->where('is_active', true)
            ->orderByDesc('config_version')
            ->first();
    }

    /**
     * Mengembalikan konfigurasi capture efektif untuk scope App atau Device.
     *
     * @return array<string, mixed>
     */
    public function getEffectiveConfig(ManagedApp $app, ?AppDevice $device = null): array
    {
        $record = $this->getActiveRecord($app, $device);
        if (! $record && $device !== null) {
            // Jika device belum memiliki override, warisi konfigurasi default aplikasi untuk tampilan form
            $record = $this->getActiveRecord($app, null);
        }

        $defaults = self::defaultConfig();
        if (! $record || ! is_array($record->config_json)) {
            return $defaults;
        }

        $stored = $record->config_json;

        return [
            'capture_policy' => array_merge($defaults['capture_policy'], is_array($stored['capture_policy'] ?? null) ? $stored['capture_policy'] : []),
            'browser_capture' => array_merge($defaults['browser_capture'], is_array($stored['browser_capture'] ?? null) ? $stored['browser_capture'] : []),
            'window_capture' => array_merge($defaults['window_capture'], is_array($stored['window_capture'] ?? null) ? $stored['window_capture'] : []),
        ];
    }

    /**
     * Mengganti sumber capture & parameter runtime secara live (menerbitkan config_version baru).
     */
    public function updatePolicy(
        ManagedApp $app,
        array $input,
        ?AppDevice $device = null,
        ?User $actor = null
    ): ServiceConfig {
        $this->enrollmentService->ensureDefaultServiceDefinitions();
        $cfg = $this->getEffectiveConfig($app, $device);

        $activeSource = (string) ($input['active_source'] ?? $cfg['capture_policy']['active_source']);
        $cfg['capture_policy']['active_source'] = $activeSource;
        $cfg['capture_policy']['allow_live_switch'] = (bool) ($input['allow_live_switch'] ?? true);

        // Selaraskan flag enabled secara konsisten dengan pilihan mode live admin
        if (array_key_exists('browser_enabled', $input)) {
            $cfg['browser_capture']['enabled'] = (bool) $input['browser_enabled'];
        } else {
            $cfg['browser_capture']['enabled'] = $activeSource === 'browser_domain';
        }

        if (array_key_exists('window_enabled', $input)) {
            $cfg['window_capture']['enabled'] = (bool) $input['window_enabled'];
        } else {
            $cfg['window_capture']['enabled'] = $activeSource === 'application_window';
        }

        if (isset($input['permission_mode'])) {
            $cfg['browser_capture']['permission_mode'] = (string) $input['permission_mode'];
        }
        if (isset($input['image_format'])) {
            $cfg['browser_capture']['image_format'] = (string) $input['image_format'];
        }
        if (isset($input['fallback_format'])) {
            $cfg['browser_capture']['fallback_format'] = (string) $input['fallback_format'];
        }
        if (isset($input['capture_interval_seconds'])) {
            $cfg['browser_capture']['capture_interval_seconds'] = (int) $input['capture_interval_seconds'];
        }
        if (isset($input['window_interval_seconds'])) {
            $cfg['window_capture']['capture_interval_seconds'] = (int) $input['window_interval_seconds'];
        }
        if (array_key_exists('local_session_required', $input)) {
            $cfg['browser_capture']['local_session_required'] = (bool) $input['local_session_required'];
        }
        if (array_key_exists('pause_when_browser_unfocused', $input)) {
            $cfg['browser_capture']['pause_when_browser_unfocused'] = (bool) $input['pause_when_browser_unfocused'];
        }
        if (array_key_exists('pause_when_tab_inactive', $input)) {
            $cfg['browser_capture']['pause_when_tab_inactive'] = (bool) $input['pause_when_tab_inactive'];
        }

        $notes = trim((string) ($input['change_notes'] ?? ''));
        if ($notes === '') {
            $notes = "Live switch capture policy -> {$activeSource}";
        }

        return $this->configVersionService->updateConfig(
            app: $app,
            serviceKey: self::SERVICE_KEY,
            configJson: $cfg,
            device: $device,
            actor: $actor,
            changeNotes: $notes,
            replaceConfig: true,
        );
    }

    /**
     * Menambahkan aturan domain browser atau aturan jendela aplikasi baru.
     */
    public function storeRule(
        ManagedApp $app,
        array $input,
        ?AppDevice $device = null,
        ?User $actor = null
    ): ServiceConfig {
        $this->enrollmentService->ensureDefaultServiceDefinitions();
        $cfg = $this->getEffectiveConfig($app, $device);
        $ruleType = (string) ($input['rule_type'] ?? 'browser');

        if ($ruleType === 'window') {
            $rules = array_values($cfg['window_capture']['rules'] ?? []);
            $ruleId = $this->resolveRuleId($input['id'] ?? null, (string) ($input['process'] ?? 'window-rule'));
            $this->assertUniqueRuleId($rules, $ruleId);

            if (count($rules) >= 50) {
                throw ValidationException::withMessages(['rules' => ['Maksimum 50 aturan jendela aplikasi tercapai.']]);
            }

            $rules[] = [
                'id' => $ruleId,
                'enabled' => (bool) ($input['enabled'] ?? true),
                'process' => trim((string) $input['process']),
                'window_title_contains' => trim((string) ($input['window_title_contains'] ?? '')),
            ];
            $cfg['window_capture']['rules'] = $rules;
            $notes = "Tambah window rule '{$ruleId}' ({$input['process']})";
        } else {
            $rules = array_values($cfg['browser_capture']['rules'] ?? []);
            $domain = strtolower(rtrim(trim((string) $input['domain']), '.'));
            $ruleId = $this->resolveRuleId($input['id'] ?? null, $domain);
            $this->assertUniqueRuleId($rules, $ruleId);

            if (count($rules) >= 50) {
                throw ValidationException::withMessages(['rules' => ['Maksimum 50 aturan domain browser tercapai.']]);
            }

            $schemes = array_values(array_unique(array_map('strtolower', (array) ($input['schemes'] ?? ['https']))));
            $browsers = array_values(array_unique(array_map('strtolower', (array) ($input['browsers'] ?? ['chrome', 'edge', 'firefox']))));

            $rules[] = [
                'id' => $ruleId,
                'enabled' => (bool) ($input['enabled'] ?? true),
                'domain' => $domain,
                'include_subdomains' => (bool) ($input['include_subdomains'] ?? true),
                'schemes' => $schemes ?: ['https'],
                'browsers' => $browsers ?: ['chrome', 'edge', 'firefox'],
            ];
            $cfg['browser_capture']['rules'] = $rules;
            $notes = "Tambah browser domain rule '{$ruleId}' ({$domain})";
        }

        return $this->configVersionService->updateConfig(
            app: $app,
            serviceKey: self::SERVICE_KEY,
            configJson: $cfg,
            device: $device,
            actor: $actor,
            changeNotes: $notes,
            replaceConfig: true,
        );
    }

    /**
     * Memperbarui aturan domain browser atau aturan jendela aplikasi secara live.
     */
    public function updateRule(
        ManagedApp $app,
        string $ruleId,
        array $input,
        ?AppDevice $device = null,
        ?User $actor = null
    ): ServiceConfig {
        $this->enrollmentService->ensureDefaultServiceDefinitions();
        $cfg = $this->getEffectiveConfig($app, $device);
        $ruleType = (string) ($input['rule_type'] ?? 'browser');
        $found = false;

        if ($ruleType === 'window') {
            $rules = array_values($cfg['window_capture']['rules'] ?? []);
            foreach ($rules as &$rule) {
                if (($rule['id'] ?? '') === $ruleId) {
                    if (array_key_exists('enabled', $input)) {
                        $rule['enabled'] = (bool) $input['enabled'];
                    }
                    if (isset($input['process'])) {
                        $rule['process'] = trim((string) $input['process']);
                    }
                    if (array_key_exists('window_title_contains', $input)) {
                        $rule['window_title_contains'] = trim((string) ($input['window_title_contains'] ?? ''));
                    }
                    $found = true;
                    break;
                }
            }
            unset($rule);

            if (! $found) {
                throw ValidationException::withMessages(['rule_id' => ["Window rule '{$ruleId}' tidak ditemukan."]]);
            }
            $cfg['window_capture']['rules'] = $rules;
            $notes = "Perbarui window rule '{$ruleId}'";
        } else {
            $rules = array_values($cfg['browser_capture']['rules'] ?? []);
            foreach ($rules as &$rule) {
                if (($rule['id'] ?? '') === $ruleId) {
                    if (array_key_exists('enabled', $input)) {
                        $rule['enabled'] = (bool) $input['enabled'];
                    }
                    if (isset($input['domain'])) {
                        $rule['domain'] = strtolower(rtrim(trim((string) $input['domain']), '.'));
                    }
                    if (array_key_exists('include_subdomains', $input)) {
                        $rule['include_subdomains'] = (bool) $input['include_subdomains'];
                    }
                    if (! empty($input['schemes'])) {
                        $rule['schemes'] = array_values(array_unique(array_map('strtolower', (array) $input['schemes'])));
                    }
                    if (! empty($input['browsers'])) {
                        $rule['browsers'] = array_values(array_unique(array_map('strtolower', (array) $input['browsers'])));
                    }
                    $found = true;
                    break;
                }
            }
            unset($rule);

            if (! $found) {
                throw ValidationException::withMessages(['rule_id' => ["Browser rule '{$ruleId}' tidak ditemukan."]]);
            }
            $cfg['browser_capture']['rules'] = $rules;
            $notes = "Perbarui browser domain rule '{$ruleId}'";
        }

        if (isset($input['capture_interval_seconds'])) {
            $cfg['browser_capture']['capture_interval_seconds'] = (int) $input['capture_interval_seconds'];
        }

        return $this->configVersionService->updateConfig(
            app: $app,
            serviceKey: self::SERVICE_KEY,
            configJson: $cfg,
            device: $device,
            actor: $actor,
            changeNotes: $notes,
            replaceConfig: true,
        );
    }

    /**
     * Menghapus aturan domain/window dan menerbitkan config_version baru.
     */
    public function destroyRule(
        ManagedApp $app,
        string $ruleId,
        string $ruleType = 'browser',
        ?AppDevice $device = null,
        ?User $actor = null
    ): ServiceConfig {
        $this->enrollmentService->ensureDefaultServiceDefinitions();
        $cfg = $this->getEffectiveConfig($app, $device);

        $sectionKey = $ruleType === 'window' ? 'window_capture' : 'browser_capture';
        $original = array_values($cfg[$sectionKey]['rules'] ?? []);
        $filtered = array_values(array_filter($original, fn ($r) => ($r['id'] ?? '') !== $ruleId));

        if (count($filtered) === count($original)) {
            throw ValidationException::withMessages(['rule_id' => ["Aturan '{$ruleId}' tidak ditemukan."]]);
        }

        $cfg[$sectionKey]['rules'] = $filtered;

        return $this->configVersionService->updateConfig(
            app: $app,
            serviceKey: self::SERVICE_KEY,
            configJson: $cfg,
            device: $device,
            actor: $actor,
            changeNotes: "Hapus {$ruleType} rule '{$ruleId}'",
            replaceConfig: true,
        );
    }

    public function rollback(
        ManagedApp $app,
        int $version,
        ?AppDevice $device = null,
        ?User $actor = null
    ): ServiceConfig {
        return $this->configVersionService->rollbackToVersion(
            app: $app,
            serviceKey: self::SERVICE_KEY,
            targetVersion: $version,
            device: $device,
            actor: $actor,
        );
    }

    /**
     * Mengambil status live extension, izin browser, dan agent untuk perangkat milik aplikasi.
     */
    public function getLiveDeviceStatuses(ManagedApp $app, ?AppDevice $device = null): Collection
    {
        $devices = AppDevice::where('managed_app_id', $app->id)
            ->whereNull('revoked_at')
            ->when($device !== null, fn ($q) => $q->where('id', $device->id))
            ->orderBy('hostname')
            ->get();

        $statuses = ServiceStatus::whereIn('device_id', $devices->pluck('id'))
            ->where('service_key', self::SERVICE_KEY)
            ->get()
            ->keyBy('device_id');

        return $devices->map(function (AppDevice $dev) use ($statuses) {
            $svcStatus = $statuses->get($dev->id);
            $details = is_array($svcStatus?->details) ? $svcStatus->details : [];

            return [
                'device' => $dev,
                'service_status' => $svcStatus?->current_status ?? 'Unknown',
                'active_config_version' => (int) ($svcStatus?->active_config_version ?? 0),
                'reported_at' => $svcStatus?->reported_at,
                'extension_connected' => (bool) ($details['extension_connected'] ?? false),
                'connected_browsers' => is_array($details['connected_browsers'] ?? null) ? $details['connected_browsers'] : [],
                'active_source' => (string) ($details['active_source'] ?? 'desktop_mss'),
                'permission_mode' => (string) ($details['permission_mode'] ?? 'local_start'),
                'has_host_permission' => (bool) ($details['has_host_permission'] ?? false),
                'local_session_state' => (string) ($details['local_session_state'] ?? 'stopped'),
                'capture_state' => (string) ($details['capture_state'] ?? 'idle'),
                'active_rule_id' => $details['active_rule_id'] ?? null,
                'matched_domain' => $details['matched_domain'] ?? null,
                'total_captured' => (int) ($details['total_captured'] ?? 0),
                'last_error' => $details['last_error'] ?? null,
            ];
        });
    }

    private function resolveRuleId(?string $explicitId, string $seed): string
    {
        $raw = trim((string) ($explicitId ?? ''));
        if ($raw === '') {
            $slug = Str::slug(str_replace('.', '-', $seed));
            $raw = $slug !== '' ? $slug : ('rule-' . strtolower(Str::random(6)));
        }

        return strtolower(substr($raw, 0, 64));
    }

    private function assertUniqueRuleId(array $rules, string $ruleId): void
    {
        foreach ($rules as $r) {
            if (($r['id'] ?? '') === $ruleId) {
                throw ValidationException::withMessages([
                    'id' => ["ID aturan '{$ruleId}' sudah digunakan. Gunakan ID unik lain."],
                ]);
            }
        }
    }
}
