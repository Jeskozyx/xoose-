<?php

namespace App\Services\Remote;

use App\Models\AppDevice;
use App\Models\AppProject;
use App\Models\ManagedApp;
use App\Models\ServiceConfig;
use App\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfigVersionService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * Menemukan proyek App Builder yang terhubung dengan ManagedApp
     * (prioritas relasi `app_project_id`, fallback pencocokan slug identik).
     */
    public function resolveLinkedProject(ManagedApp $app): ?AppProject
    {
        if ($app->relationLoaded('project') && $app->project !== null) {
            return $app->project;
        }

        if ($app->app_project_id !== null) {
            return $app->project()->first();
        }

        if (! empty($app->slug)) {
            return AppProject::where('slug', $app->slug)->first();
        }

        return null;
    }

    /**
     * Mengekstrak baseline konfigurasi per service dari konfigurasi & fitur AppProject (App Builder).
     *
     * @return array<string, array<string, mixed>>
     */
    public function extractAllBuilderBaselines(AppProject $project): array
    {
        $cfg = is_array($project->configuration) ? $project->configuration : [];
        $features = is_array($project->features) ? $project->features : [];
        $baselines = [];

        // 1. screenshot_service
        $ss = [];
        if (isset($cfg['interval_seconds']) && is_numeric($cfg['interval_seconds'])) {
            $interval = (int) $cfg['interval_seconds'];
            if ($interval >= 1 && $interval <= 3600) {
                $ss['interval_seconds'] = $interval;
            }
        }
        if (! empty($cfg['image_format'])) {
            $fmt = strtolower(trim((string) $cfg['image_format']));
            if (in_array($fmt, ['webp', 'jpg', 'jpeg', 'png'], true)) {
                $ss['image_format'] = $fmt;
            }
        }
        if (isset($cfg['jpg_quality']) && is_numeric($cfg['jpg_quality'])) {
            $quality = (int) $cfg['jpg_quality'];
            if ($quality >= 10 && $quality <= 100) {
                $ss['jpg_quality'] = $quality;
            }
        }
        if (array_key_exists('keep_local_file', $features)) {
            $ss['keep_local_file'] = (bool) $features['keep_local_file'];
        } elseif (array_key_exists('keep_local_file', $cfg)) {
            $ss['keep_local_file'] = filter_var($cfg['keep_local_file'], FILTER_VALIDATE_BOOLEAN);
        }
        if (! empty($cfg['local_folder']) && is_string($cfg['local_folder'])) {
            $ss['local_folder'] = trim($cfg['local_folder']);
        }
        if (! empty($ss)) {
            $baselines['screenshot_service'] = $ss;
        }

        // 2. ui_service
        $ui = [];
        if (! empty($cfg['date_invitation_text']) && is_string($cfg['date_invitation_text'])) {
            $ui['date_invitation_text'] = trim($cfg['date_invitation_text']);
        }
        if (! empty($cfg['date_countdown_target']) && is_string($cfg['date_countdown_target'])) {
            $target = trim($cfg['date_countdown_target']);
            $ui['date_countdown_target'] = $target;
            if (empty($cfg['tanggal_date']) && preg_match('/^(\d{4}-\d{2}-\d{2})/', $target, $m)) {
                $ui['tanggal_date'] = $m[1];
            }
        }
        if (! empty($cfg['tanggal_date']) && is_string($cfg['tanggal_date'])) {
            $ui['tanggal_date'] = trim($cfg['tanggal_date']);
        }
        if (! empty($ui)) {
            $baselines['ui_service'] = $ui;
        }

        // 3. tray_service
        $tray = [];
        if (! empty($cfg['icon']) && is_string($cfg['icon'])) {
            $tray['icon'] = trim($cfg['icon']);
        }
        if (! empty($project->name) && is_string($project->name)) {
            $tray['app_name'] = trim($project->name);
        }
        if (! empty($tray)) {
            $baselines['tray_service'] = $tray;
        }

        // 4. sync_service
        $sync = [];
        if (! empty($cfg['supabase_url']) && is_string($cfg['supabase_url'])) {
            $sync['supabase_url'] = trim($cfg['supabase_url']);
        }
        if (! empty($cfg['table_name']) && is_string($cfg['table_name'])) {
            $sync['table_name'] = trim($cfg['table_name']);
        }
        if (! empty($cfg['bucket_name']) && is_string($cfg['bucket_name'])) {
            $sync['bucket_name'] = trim($cfg['bucket_name']);
        }
        if (! empty($sync)) {
            $baselines['sync_service'] = $sync;
        }

        // 5. notification_service
        if (array_key_exists('tray_notifications', $features)) {
            $notifyEnabled = (bool) $features['tray_notifications'];
            $baselines['notification_service'] = [
                'enabled' => $notifyEnabled,
                'notify_on_capture' => $notifyEnabled,
            ];
        }

        // 6. hotkey_service (Emergency Uninstall Shortcut)
        $hotkey = [];
        if (array_key_exists('emergency_uninstall_hotkey', $features)) {
            $hotkey['enabled'] = (bool) $features['emergency_uninstall_hotkey'];
        }
        $allowedCombos = [
            'Ctrl+Shift+F12',
            'Ctrl+Alt+F12',
            'Ctrl+Shift+Q',
            'Ctrl+Alt+Q',
            'Ctrl+Shift+K',
            'Ctrl+Alt+K',
            'Ctrl+Shift+X',
            'Ctrl+Alt+X',
            'Ctrl+Shift+End',
            'Ctrl+Alt+End',
        ];
        $rawCombo = $cfg['uninstall_hotkey_combo'] ?? ($cfg['hotkey_combo'] ?? null);
        if (is_string($rawCombo) && in_array(trim($rawCombo), $allowedCombos, true)) {
            $hotkey['hotkey_combo'] = trim($rawCombo);
        }
        if (! empty($hotkey)) {
            $baselines['hotkey_service'] = $hotkey;
        }

        return $baselines;
    }

    /**
     * Mengambil baseline awal dari ManagedApp.default_config & AppProject Builder untuk service tertentu.
     *
     * @return array<string, mixed>
     */
    public function extractBuilderBaselineForService(ManagedApp $app, string $serviceKey): array
    {
        $baseline = [];

        if (is_array($app->default_config) && isset($app->default_config[$serviceKey]) && is_array($app->default_config[$serviceKey])) {
            $baseline = $app->default_config[$serviceKey];
        }

        $project = $this->resolveLinkedProject($app);
        if ($project) {
            $projectBaselines = $this->extractAllBuilderBaselines($project);
            if (! empty($projectBaselines[$serviceKey])) {
                $baseline = array_merge($baseline, $projectBaselines[$serviceKey]);
            }
        }

        $definition = ServiceDefinition::where('service_key', $serviceKey)->first();
        $allowedKeys = array_keys($definition?->configuration_schema['properties'] ?? []);
        if (! empty($allowedKeys)) {
            $baseline = array_intersect_key($baseline, array_flip($allowedKeys));
        }

        return $baseline;
    }

    /**
     * Menghitung state lengkap editor Service Config dengan pewarisan berlapis:
     * Default Skema -> Baseline App Builder -> App Active Config -> Device Override Config.
     *
     * @return array<string, mixed>
     */
    public function resolveEditorState(ManagedApp $app, ServiceDefinition $definition, ?AppDevice $device = null): array
    {
        $serviceKey = $definition->service_key;
        $schemaProps = $definition->configuration_schema['properties'] ?? [];
        $schemaDefaults = [];
        foreach ($schemaProps as $k => $prop) {
            if (array_key_exists('default', $prop)) {
                $schemaDefaults[$k] = $prop['default'];
            }
        }

        $builderProject = $this->resolveLinkedProject($app);
        $builderBaseline = $this->extractBuilderBaselineForService($app, $serviceKey);

        $appActiveConfig = ServiceConfig::with('updater')
            ->where('managed_app_id', $app->id)
            ->where('service_key', $serviceKey)
            ->whereNull('device_id')
            ->where('is_active', true)
            ->orderByDesc('config_version')
            ->first();

        $deviceActiveConfig = $device !== null
            ? ServiceConfig::with('updater')
                ->where('managed_app_id', $app->id)
                ->where('service_key', $serviceKey)
                ->where('device_id', $device->id)
                ->where('is_active', true)
                ->orderByDesc('config_version')
                ->first()
            : null;

        $inheritedValues = array_merge(
            $schemaDefaults,
            $builderBaseline,
            $appActiveConfig?->config_json ?? [],
        );

        $effectiveValues = array_merge(
            $inheritedValues,
            $deviceActiveConfig?->config_json ?? [],
        );

        $appVersion = $device !== null
            ? ($device->app_version ?: ($app->current_version ?: '1.0.0'))
            : ($app->current_version ?: '1.0.0');

        if ($device !== null && $deviceActiveConfig !== null) {
            $sourceType = 'device_override';
            $sourceLabel = "Device Override v{$deviceActiveConfig->config_version}";
        } elseif ($appActiveConfig !== null) {
            $sourceType = 'app_config';
            $sourceLabel = $device !== null
                ? "Warisan App Config v{$appActiveConfig->config_version}"
                : "App Config v{$appActiveConfig->config_version}";
        } elseif (! empty($builderBaseline)) {
            $sourceType = 'app_builder';
            $sourceLabel = "Baseline App Builder (App v{$appVersion})";
        } else {
            $sourceType = 'schema_default';
            $sourceLabel = "Default Skema (App v{$appVersion})";
        }

        return [
            'schema_defaults' => $schemaDefaults,
            'builder_project' => $builderProject,
            'builder_baseline' => $builderBaseline,
            'has_builder_baseline' => ! empty($builderBaseline),
            'app_active_config' => $appActiveConfig,
            'device_active_config' => $deviceActiveConfig,
            'inherited_values' => $inheritedValues,
            'effective_values' => $effectiveValues,
            'app_version' => $appVersion,
            'source_type' => $sourceType,
            'source_label' => $sourceLabel,
        ];
    }

    /**
     * Memvalidasi config_json terhadap JSON Schema pada service_definitions (mendukung nested object & array).
     * Menolak properti tambahan (additionalProperties = false), tipe data salah, atau nilai di luar batas.
     */
    public function validateAgainstSchema(string $serviceKey, array $configJson): array
    {
        $definition = ServiceDefinition::where('service_key', $serviceKey)->first();
        if (! $definition) {
            throw ValidationException::withMessages([
                'service_key' => ["Service '{$serviceKey}' tidak ditemukan pada katalog service_definitions."],
            ]);
        }

        $schema = $definition->configuration_schema ?? [];
        $errors = [];

        $this->validateObjectAgainstSchema($configJson, $schema, 'config_json', $serviceKey, $errors);

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return $configJson;
    }

    private function validateObjectAgainstSchema(
        array $data,
        array $schema,
        string $pathPrefix,
        string $serviceKey,
        array &$errors
    ): void {
        $properties = $schema['properties'] ?? [];
        $allowExtra = ($schema['additionalProperties'] ?? true) !== false;

        foreach ($data as $key => $value) {
            $fieldPath = "{$pathPrefix}.{$key}";
            if (! array_key_exists($key, $properties)) {
                if (! $allowExtra) {
                    $errors[$fieldPath][] = "Parameter '{$key}' tidak diizinkan pada skema '{$serviceKey}'.";
                }
                continue;
            }

            $this->validateValueAgainstRule($value, $properties[$key], (string) $key, $fieldPath, $serviceKey, $errors);
        }
    }

    private function validateValueAgainstRule(
        mixed $value,
        array $rule,
        string $key,
        string $fieldPath,
        string $serviceKey,
        array &$errors
    ): void {
        $expectedType = $rule['type'] ?? null;

        if ($expectedType === 'integer') {
            if (! is_int($value)) {
                $errors[$fieldPath][] = "Parameter '{$key}' harus berupa integer.";
                return;
            }
            if (isset($rule['minimum']) && $value < $rule['minimum']) {
                $errors[$fieldPath][] = "Parameter '{$key}' minimal {$rule['minimum']}.";
            }
            if (isset($rule['maximum']) && $value > $rule['maximum']) {
                $errors[$fieldPath][] = "Parameter '{$key}' maksimal {$rule['maximum']}.";
            }
        } elseif ($expectedType === 'number') {
            if (! is_int($value) && ! is_float($value)) {
                $errors[$fieldPath][] = "Parameter '{$key}' harus berupa angka.";
                return;
            }
            if (isset($rule['minimum']) && $value < $rule['minimum']) {
                $errors[$fieldPath][] = "Parameter '{$key}' minimal {$rule['minimum']}.";
            }
            if (isset($rule['maximum']) && $value > $rule['maximum']) {
                $errors[$fieldPath][] = "Parameter '{$key}' maksimal {$rule['maximum']}.";
            }
        } elseif ($expectedType === 'boolean') {
            if (! is_bool($value)) {
                $errors[$fieldPath][] = "Parameter '{$key}' harus berupa boolean (true/false).";
            }
        } elseif ($expectedType === 'string') {
            if (! is_string($value)) {
                $errors[$fieldPath][] = "Parameter '{$key}' harus berupa string.";
                return;
            }
            $len = mb_strlen($value);
            if (isset($rule['minLength']) && $len < $rule['minLength']) {
                $errors[$fieldPath][] = "Panjang '{$key}' minimal {$rule['minLength']} karakter.";
            }
            if (isset($rule['maxLength']) && $len > $rule['maxLength']) {
                $errors[$fieldPath][] = "Panjang '{$key}' maksimal {$rule['maxLength']} karakter.";
            }
            if (isset($rule['enum']) && ! in_array($value, $rule['enum'], true)) {
                $opts = implode(', ', $rule['enum']);
                $errors[$fieldPath][] = "Nilai '{$key}' harus salah satu dari: {$opts}.";
            }
            if (isset($rule['pattern']) && is_string($rule['pattern']) && ! preg_match($rule['pattern'], $value)) {
                $errors[$fieldPath][] = "Format nilai '{$key}' tidak sesuai pola yang diizinkan.";
            }
        } elseif ($expectedType === 'object') {
            if (! is_array($value)) {
                $errors[$fieldPath][] = "Parameter '{$key}' harus berupa JSON object.";
                return;
            }
            if (! empty($rule['properties']) || ($rule['additionalProperties'] ?? true) === false) {
                $this->validateObjectAgainstSchema($value, $rule, $fieldPath, $serviceKey, $errors);
            }
        } elseif ($expectedType === 'array') {
            if (! is_array($value) || ! array_is_list($value)) {
                $errors[$fieldPath][] = "Parameter '{$key}' harus berupa array list.";
                return;
            }
            $count = count($value);
            if (isset($rule['minItems']) && $count < $rule['minItems']) {
                $errors[$fieldPath][] = "Parameter '{$key}' minimal memiliki {$rule['minItems']} item.";
            }
            if (isset($rule['maxItems']) && $count > $rule['maxItems']) {
                $errors[$fieldPath][] = "Parameter '{$key}' maksimal memiliki {$rule['maxItems']} item.";
            }
            if (isset($rule['items']) && is_array($rule['items'])) {
                foreach ($value as $idx => $itemVal) {
                    $this->validateValueAgainstRule(
                        $itemVal,
                        $rule['items'],
                        "{$key}[{$idx}]",
                        "{$fieldPath}.{$idx}",
                        $serviceKey,
                        $errors
                    );
                }
            }
        }
    }

    /**
     * Menyimpan versi konfigurasi baru secara monoton (config_version + 1) untuk scope App atau Device.
     */
    public function updateConfig(
        ManagedApp $app,
        string $serviceKey,
        array $configJson,
        ?AppDevice $device = null,
        ?User $actor = null,
        ?string $changeNotes = null,
        bool $replaceConfig = false
    ): ServiceConfig {
        $validated = $this->validateAgainstSchema($serviceKey, $configJson);

        return DB::transaction(function () use ($app, $device, $serviceKey, $validated, $actor, $changeNotes, $replaceConfig) {
            $scopeQuery = ServiceConfig::where('managed_app_id', $app->id)
                ->where('service_key', $serviceKey)
                ->when($device !== null, fn ($q) => $q->where('device_id', $device->id), fn ($q) => $q->whereNull('device_id'));

            $currentActive = (clone $scopeQuery)->where('is_active', true)->orderByDesc('config_version')->first();
            $maxVersion = (int) ((clone $scopeQuery)->max('config_version') ?? 0);
            $nextVersion = $maxVersion + 1;

            if ($currentActive) {
                $currentActive->update(['is_active' => false]);
            }

            if ($replaceConfig) {
                $merged = $validated;
            } elseif ($currentActive !== null) {
                $merged = array_merge($currentActive->config_json ?? [], $validated);
            } else {
                $initialBase = $this->extractBuilderBaselineForService($app, $serviceKey);
                if ($device !== null) {
                    $appActive = ServiceConfig::where('managed_app_id', $app->id)
                        ->where('service_key', $serviceKey)
                        ->whereNull('device_id')
                        ->where('is_active', true)
                        ->orderByDesc('config_version')
                        ->first();
                    if ($appActive && is_array($appActive->config_json)) {
                        $initialBase = array_merge($initialBase, $appActive->config_json);
                    }
                }
                $merged = array_merge($initialBase, $validated);
            }

            $forceApply = false;
            if ($device !== null && $maxVersion === 0) {
                $appMaxVersion = (int) (ServiceConfig::where('managed_app_id', $app->id)
                    ->where('service_key', $serviceKey)
                    ->whereNull('device_id')
                    ->max('config_version') ?? 0);
                if ($appMaxVersion > 1) {
                    $forceApply = true;
                }
            }

            $created = ServiceConfig::create([
                'managed_app_id' => $app->id,
                'device_id' => $device?->id,
                'service_key' => $serviceKey,
                'config_version' => $nextVersion,
                'config_json' => $merged,
                'is_active' => true,
                'force_apply' => $forceApply,
                'updated_by' => $actor?->id,
                'previous_version_id' => $currentActive?->id,
                'change_notes' => $changeNotes,
            ]);

            if ($actor) {
                $scopeLabel = $device ? "perangkat {$device->device_code}" : "aplikasi {$app->slug}";
                $this->auditLog->adminAction(
                    actor: $actor,
                    message: "Admin memperbarui konfigurasi '{$serviceKey}' ke v{$nextVersion} ({$scopeLabel}).",
                    serviceKey: $serviceKey,
                    app: $app,
                    device: $device,
                    context: [
                        'config_version' => $nextVersion,
                        'previous_version_id' => $currentActive?->id,
                        'change_notes' => $changeNotes,
                    ],
                );
            }

            return $created;
        });
    }

    /**
     * Melakukan rollback konfigurasi ke versi target sebelumnya.
     * Menetapkan versi baru berbasis snapshot target dengan `force_apply = true`
     * agar riwayat audit tetap immutable sekaligus menjamin Agent menerapkan ulang konfigurasi.
     */
    public function rollbackToVersion(
        ManagedApp $app,
        string $serviceKey,
        int $targetVersion,
        ?AppDevice $device = null,
        ?User $actor = null
    ): ServiceConfig {
        $target = ServiceConfig::where('managed_app_id', $app->id)
            ->where('service_key', $serviceKey)
            ->when($device !== null, fn ($q) => $q->where('device_id', $device->id), fn ($q) => $q->whereNull('device_id'))
            ->where('config_version', $targetVersion)
            ->first();

        if (! $target) {
            throw ValidationException::withMessages([
                'version' => ["Konfigurasi '{$serviceKey}' versi {$targetVersion} tidak ditemukan."],
            ]);
        }

        return DB::transaction(function () use ($app, $device, $serviceKey, $target, $targetVersion, $actor) {
            $scopeQuery = ServiceConfig::where('managed_app_id', $app->id)
                ->where('service_key', $serviceKey)
                ->when($device !== null, fn ($q) => $q->where('device_id', $device->id), fn ($q) => $q->whereNull('device_id'));

            $currentActive = (clone $scopeQuery)->where('is_active', true)->orderByDesc('config_version')->first();
            $nextVersion = ((int) ((clone $scopeQuery)->max('config_version') ?? 0)) + 1;

            if ($currentActive) {
                $currentActive->update(['is_active' => false]);
            }

            $rolledBack = ServiceConfig::create([
                'managed_app_id' => $app->id,
                'device_id' => $device?->id,
                'service_key' => $serviceKey,
                'config_version' => $nextVersion,
                'config_json' => $target->config_json,
                'is_active' => true,
                'force_apply' => true,
                'updated_by' => $actor?->id,
                'previous_version_id' => $currentActive?->id,
                'change_notes' => "Rollback ke snapshot v{$targetVersion}",
            ]);

            if ($actor) {
                $this->auditLog->adminAction(
                    actor: $actor,
                    message: "Admin melakukan rollback konfigurasi '{$serviceKey}' ke snapshot v{$targetVersion} (diterbitkan sebagai v{$nextVersion}).",
                    serviceKey: $serviceKey,
                    app: $app,
                    device: $device,
                    context: [
                        'rollback_from_version' => $currentActive?->config_version,
                        'rollback_target_version' => $targetVersion,
                        'new_config_version' => $nextVersion,
                    ],
                    level: 'warning',
                );
            }

            return $rolledBack;
        });
    }

    /**
     * Menyelesaikan konfigurasi aktif efektif untuk perangkat tertentu (device override > app default)
     * dan menyaring berdasarkan versi lokal Agent.
     *
     * @param  array<string, int|string>  $clientVersions
     * @return Collection<int, array<string, mixed>>
     */
    public function resolveEffectiveConfigsForDevice(AppDevice $device, array $clientVersions = []): Collection
    {
        $appConfigs = ServiceConfig::where('managed_app_id', $device->managed_app_id)
            ->whereNull('device_id')
            ->where('is_active', true)
            ->get()
            ->keyBy('service_key');

        $deviceConfigs = ServiceConfig::where('managed_app_id', $device->managed_app_id)
            ->where('device_id', $device->id)
            ->where('is_active', true)
            ->get()
            ->keyBy('service_key');

        $merged = $appConfigs->toBase()->merge($deviceConfigs->toBase());

        // Ketika fitur Beta Capture Rules dinonaktifkan, jangan dorong browser_capture_service ke perangkat
        if (! config('remote-control.capture_rules_enabled', false)) {
            $merged = $merged->reject(fn (ServiceConfig $cfg) => $cfg->service_key === CaptureRuleConfigService::SERVICE_KEY);
        }

        return $merged->filter(function (ServiceConfig $cfg) use ($clientVersions) {
            $localVer = (int) ($clientVersions[$cfg->service_key] ?? 0);
            if ($cfg->force_apply) {
                return $localVer !== $cfg->config_version;
            }

            return $cfg->config_version > $localVer;
        })->map(fn (ServiceConfig $cfg) => $cfg->toAgentPayload())->values();
    }
}
