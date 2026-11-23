<?php

namespace Tests\Feature\Remote;

use App\Models\AppDevice;
use App\Models\ManagedApp;
use App\Models\ServiceConfig;
use App\Models\ServiceStatus;
use App\Models\User;
use App\Services\Remote\CaptureRuleConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CaptureRuleManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminAndApp(?User $owner = null): array
    {
        $admin = $owner ?? User::factory()->create();
        $app = ManagedApp::create([
            'owner_id' => $admin->id,
            'name' => 'LoveYou Ujian',
            'slug' => 'loveyou-ujian-' . strtolower(Str::random(4)),
            'current_version' => '1.0.0',
            'build_status' => 'built',
            'is_active' => true,
        ]);

        return [$admin, $app];
    }

    public function test_capture_rules_requires_admin_authentication(): void
    {
        $this->get('/remote/capture-rules')->assertRedirect('/login');
        $this->post('/remote/capture-rules/policy', [])->assertRedirect('/login');
    }

    public function test_beta_restriction_blocks_capture_rules_without_affecting_legacy_services(): void
    {
        config(['remote-control.capture_rules_enabled' => false]);
        [$admin, $app] = $this->createAdminAndApp();
        $scope = "app-{$app->id}";

        $rawToken = 'xly_dev_' . Str::random(32);
        $device = AppDevice::create([
            'managed_app_id' => $app->id,
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'DEV-BETA01',
            'hostname' => 'LAPTOP-LEGACY',
            'os_info' => 'Windows 11',
            'app_version' => '1.0.0',
            'status' => AppDevice::STATUS_ONLINE,
            'token_hash' => hash('sha256', $rawToken),
        ]);

        // 1. Halaman Capture Rules menampilkan status Beta terkunci
        $this->actingAs($admin)
            ->get('/remote/capture-rules')
            ->assertOk()
            ->assertSee('Specific Screen &amp; Capture Rules Dinonaktifkan Sementara', false);

        // 2. Semua mutasi Capture Rules diblokir & tidak membuat versi baru
        $this->actingAs($admin)
            ->post('/remote/capture-rules/policy', [
                'scope' => $scope,
                'active_source' => 'browser_domain',
            ])
            ->assertRedirect(route('remote.capture-rules.index', ['scope' => $scope]))
            ->assertSessionHas('error');

        $this->assertSame(0, ServiceConfig::where('service_key', CaptureRuleConfigService::SERVICE_KEY)->count());

        // 3. Command ke browser_capture_service diblokir, tetapi screenshot_service lama tetap 100% berjalan!
        $this->actingAs($admin)
            ->post("/remote/devices/{$device->id}/services/browser_capture_service/command", ['action' => 'start'])
            ->assertSessionHas('error');

        $this->actingAs($admin)
            ->post("/remote/devices/{$device->id}/services/screenshot_service/command", ['action' => 'pause'])
            ->assertSessionHas('success');

        // 4. ServiceConfig lama (screenshot_service) tetap tersimpan & dikirim ke Agent API,
        //    sementara browser_capture_service disembunyikan dari Agent API saat Beta terkunci
        $this->actingAs($admin)
            ->put("/remote/configs/{$scope}/screenshot_service", [
                'config' => ['interval_seconds' => 5],
            ])
            ->assertSessionHas('success');

        ServiceConfig::create([
            'managed_app_id' => $app->id,
            'service_key' => CaptureRuleConfigService::SERVICE_KEY,
            'config_version' => 1,
            'config_json' => CaptureRuleConfigService::defaultConfig(),
            'is_active' => true,
        ]);

        $agentResp = $this->withHeader('Authorization', "Bearer {$rawToken}")
            ->getJson('/api/v1/agent/configs');
        $agentResp->assertOk();
        $keys = collect($agentResp->json('configs'))->pluck('service_key')->all();
        $this->assertContains('screenshot_service', $keys);
        $this->assertNotContains(CaptureRuleConfigService::SERVICE_KEY, $keys);
    }

    public function test_admin_can_view_capture_rules_page_and_live_extension_status(): void
    {
        config(['remote-control.capture_rules_enabled' => true]);
        [$admin, $app] = $this->createAdminAndApp();

        $device = AppDevice::create([
            'managed_app_id' => $app->id,
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'DEV-CAP001',
            'hostname' => 'LAPTOP-UJIAN',
            'os_info' => 'Windows 11',
            'app_version' => '1.0.0',
            'status' => AppDevice::STATUS_ONLINE,
            'token_hash' => hash('sha256', 'secret-token-123'),
        ]);

        ServiceStatus::create([
            'device_id' => $device->id,
            'service_key' => CaptureRuleConfigService::SERVICE_KEY,
            'current_status' => 'Running',
            'active_config_version' => 2,
            'details' => [
                'extension_connected' => true,
                'connected_browsers' => ['chrome'],
                'permission_mode' => 'local_start',
                'has_host_permission' => false,
                'local_session_state' => 'running',
                'matched_domain' => 'www.konstantaedu.com',
                'total_captured' => 7,
            ],
            'reported_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/remote/capture-rules');
        $response->assertOk();
        $response->assertSee('Specific Screen & Live Capture Rules', false);
        $response->assertSee('LAPTOP-UJIAN');
        $response->assertSee('Extension Terhubung');
        $response->assertSee('www.konstantaedu.com');
    }

    public function test_live_policy_switch_and_crud_domain_window_rules_with_rollback_and_agent_api_sync(): void
    {
        config(['remote-control.capture_rules_enabled' => true]);
        [$admin, $app] = $this->createAdminAndApp();
        $scope = "app-{$app->id}";

        $rawToken = 'xly_dev_' . Str::random(32);
        AppDevice::create([
            'managed_app_id' => $app->id,
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'DEV-SYNC01',
            'hostname' => 'CLIENT-LIVE',
            'os_info' => 'Windows 11',
            'app_version' => '1.0.0',
            'status' => AppDevice::STATUS_ONLINE,
            'token_hash' => hash('sha256', $rawToken),
        ]);

        // 1. Live switch ke browser_domain (v1)
        $this->actingAs($admin)
            ->post('/remote/capture-rules/policy', [
                'scope' => $scope,
                'active_source' => 'browser_domain',
                'permission_mode' => 'local_start',
                'capture_interval_seconds' => 12,
                'image_format' => 'webp',
                'pause_when_browser_unfocused' => true,
                'pause_when_tab_inactive' => true,
            ])
            ->assertRedirect(route('remote.capture-rules.index', ['scope' => $scope]));

        // 2. Tambah domain rule konstantaedu.com (v2)
        $this->actingAs($admin)
            ->post('/remote/capture-rules/rules', [
                'scope' => $scope,
                'rule_type' => 'browser',
                'id' => 'konstanta-domain',
                'domain' => 'KONSTANTAEDU.COM.',
                'include_subdomains' => true,
                'schemes' => ['https'],
                'browsers' => ['chrome', 'edge', 'firefox'],
                'enabled' => true,
            ])
            ->assertRedirect(route('remote.capture-rules.index', ['scope' => $scope]));

        // 3. Edit rule secara live -> nonaktifkan subdomain (v3)
        $this->actingAs($admin)
            ->put('/remote/capture-rules/rules/konstanta-domain', [
                'scope' => $scope,
                'rule_type' => 'browser',
                'include_subdomains' => false,
                'enabled' => true,
            ])
            ->assertRedirect(route('remote.capture-rules.index', ['scope' => $scope]));

        // 4. Tambah window rule (v4)
        $this->actingAs($admin)
            ->post('/remote/capture-rules/rules', [
                'scope' => $scope,
                'rule_type' => 'window',
                'id' => 'chrome-window',
                'process' => 'chrome.exe',
                'window_title_contains' => 'Tryout',
                'enabled' => true,
            ])
            ->assertRedirect(route('remote.capture-rules.index', ['scope' => $scope]));

        // Verifikasi Agent API /api/v1/agent/configs otomatis mengembalikan v4 live!
        $agentResp = $this->withHeader('Authorization', "Bearer {$rawToken}")
            ->getJson('/api/v1/agent/configs?versions[browser_capture_service]=0');

        $agentResp->assertOk();
        $configs = collect($agentResp->json('configs'));
        $bcCfg = $configs->firstWhere('service_key', CaptureRuleConfigService::SERVICE_KEY);
        $this->assertNotNull($bcCfg);
        $this->assertSame(4, $bcCfg['config_version']);
        $this->assertSame('browser_domain', $bcCfg['config_json']['capture_policy']['active_source']);
        $this->assertTrue($bcCfg['config_json']['browser_capture']['enabled']);
        $this->assertSame('konstantaedu.com', $bcCfg['config_json']['browser_capture']['rules'][0]['domain']);
        $this->assertFalse($bcCfg['config_json']['browser_capture']['rules'][0]['include_subdomains']);
        $this->assertSame('chrome.exe', $bcCfg['config_json']['window_capture']['rules'][0]['process']);

        // 5. Hapus rule (v5)
        $this->actingAs($admin)
            ->delete('/remote/capture-rules/rules/konstanta-domain', [
                'scope' => $scope,
                'rule_type' => 'browser',
            ])
            ->assertRedirect(route('remote.capture-rules.index', ['scope' => $scope]));

        $latestV5 = ServiceConfig::where('managed_app_id', $app->id)
            ->where('service_key', CaptureRuleConfigService::SERVICE_KEY)
            ->where('is_active', true)
            ->firstOrFail();
        $this->assertSame(5, $latestV5->config_version);
        $this->assertCount(0, $latestV5->config_json['browser_capture']['rules']);

        // 6. Rollback ke v2 (memulihkan konstantaedu.com + include_subdomains=true sebagai v6 dengan force_apply)
        $this->actingAs($admin)
            ->post('/remote/capture-rules/rollback/2', ['scope' => $scope])
            ->assertRedirect(route('remote.capture-rules.index', ['scope' => $scope]));

        $rolledV6 = ServiceConfig::where('managed_app_id', $app->id)
            ->where('service_key', CaptureRuleConfigService::SERVICE_KEY)
            ->where('is_active', true)
            ->firstOrFail();
        $this->assertSame(6, $rolledV6->config_version);
        $this->assertTrue($rolledV6->force_apply);
        $this->assertSame('konstantaedu.com', $rolledV6->config_json['browser_capture']['rules'][0]['domain']);
        $this->assertTrue($rolledV6->config_json['browser_capture']['rules'][0]['include_subdomains']);
    }

    public function test_validation_rejects_wildcard_domain_scheme_port_bad_interval_and_unsafe_process(): void
    {
        config(['remote-control.capture_rules_enabled' => true]);
        [$admin, $app] = $this->createAdminAndApp();
        $scope = "app-{$app->id}";

        // Wildcard domain ditolak
        $this->actingAs($admin)
            ->post('/remote/capture-rules/rules', [
                'scope' => $scope,
                'rule_type' => 'browser',
                'domain' => '*.konstantaedu.com',
            ])
            ->assertSessionHasErrors('domain');

        // Domain dengan scheme/path ditolak
        $this->actingAs($admin)
            ->post('/remote/capture-rules/rules', [
                'scope' => $scope,
                'rule_type' => 'browser',
                'domain' => 'https://konstantaedu.com/tryout',
            ])
            ->assertSessionHasErrors('domain');

        // Mode capture tidak valid ditolak
        $this->actingAs($admin)
            ->post('/remote/capture-rules/policy', [
                'scope' => $scope,
                'active_source' => 'shell_exec',
                'capture_interval_seconds' => 1,
            ])
            ->assertSessionHasErrors(['active_source', 'capture_interval_seconds']);

        // Nama proses berbahaya (path traversal) ditolak
        $this->actingAs($admin)
            ->post('/remote/capture-rules/rules', [
                'scope' => $scope,
                'rule_type' => 'window',
                'process' => 'C:\\Windows\\System32\\cmd.exe',
            ])
            ->assertSessionHasErrors('process');
    }

    public function test_admin_cannot_modify_another_owners_app_capture_rules(): void
    {
        config(['remote-control.capture_rules_enabled' => true]);
        [$owner, $app] = $this->createAdminAndApp();
        $otherAdmin = User::factory()->create();

        $this->actingAs($otherAdmin)
            ->post('/remote/capture-rules/policy', [
                'scope' => "app-{$app->id}",
                'active_source' => 'browser_domain',
            ])
            ->assertForbidden();

        $this->actingAs($otherAdmin)
            ->get("/remote/capture-rules?scope=app-{$app->id}")
            ->assertForbidden();
    }
}
