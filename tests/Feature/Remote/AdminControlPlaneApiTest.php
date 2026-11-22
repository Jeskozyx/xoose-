<?php

namespace Tests\Feature\Remote;

use App\Jobs\DetectOfflineDevicesJob;
use App\Jobs\ExpireStaleCommandsJob;
use App\Models\AppDevice;
use App\Models\AppProject;
use App\Models\AppTemplate;
use App\Models\ManagedApp;
use App\Models\ServiceCommand;
use App\Models\ServiceLog;
use App\Models\User;
use App\Services\Remote\DeviceEnrollmentService;
use Database\Seeders\ServiceDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminControlPlaneApiTest extends TestCase
{
    use RefreshDatabase;

    private User $adminA;
    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ServiceDefinitionSeeder::class);
        $this->adminA = User::factory()->create();
        $this->adminB = User::factory()->create();
    }

    public function test_admin_endpoints_reject_guests_and_enforce_owner_isolation(): void
    {
        // Guest ditolak 401
        $this->getJson('/api/v1/admin/apps')->assertStatus(401);

        $appA = ManagedApp::create([
            'owner_id' => $this->adminA->id,
            'name' => 'App Milik A',
            'slug' => 'app-milik-a',
            'is_active' => true,
        ]);

        // Admin B dilarang membuat enrollment atau mengubah config aplikasi milik Admin A (403)
        $this->actingAs($this->adminB)
            ->postJson("/api/v1/admin/apps/{$appA->id}/enrollments")
            ->assertStatus(403);

        $this->actingAs($this->adminB)
            ->putJson("/api/v1/admin/configs/{$appA->id}/screenshot_service", [
                'config_json' => ['interval_seconds' => 10],
            ])
            ->assertStatus(403);
    }

    public function test_admin_app_project_link_command_allowlist_cancel_and_audit_logs(): void
    {
        $template = AppTemplate::create([
            'name' => 'LoveYou Template',
            'slug' => 'loveyou-tpl',
            'version' => '1.0.0',
            'source_path' => 'D:/KULIAH/KODINGAN/screenshotusingpy',
        ]);
        $project = AppProject::create([
            'app_template_id' => $template->id,
            'name' => 'LoveYou Project 1',
            'slug' => 'loveyou-proj-1',
            'status' => 'ready',
            'created_by' => $this->adminA->id,
        ]);

        // Buat ManagedApp terhubung dengan AppProject
        $createResp = $this->actingAs($this->adminA)->postJson('/api/v1/admin/apps', [
            'name' => 'LoveYou Production',
            'slug' => 'loveyou-prod',
            'app_project_id' => $project->id,
        ]);
        $createResp->assertCreated();
        $appId = $createResp->json('app.id');
        $this->assertSame($appId, $project->fresh()->managedApp->id);

        // Buat enrollment code dan daftarkan perangkat
        $enrResp = $this->actingAs($this->adminA)->postJson("/api/v1/admin/apps/{$appId}/enrollments", [
            'label' => 'Windows Target',
            'max_uses' => 1,
        ]);
        $enrResp->assertCreated();
        $code = $enrResp->json('enrollment.enrollment_code');

        $agentResp = $this->postJson('/api/v1/agent/enroll', [
            'enrollment_code' => $code,
            'device_uuid' => (string) Str::uuid(),
            'hostname' => 'WIN11-TARGET',
            'app_slug' => 'loveyou-prod',
        ])->assertCreated();
        $deviceToken = $agentResp->json('device_token');
        $device = AppDevice::where('device_uuid', $agentResp->json('device_id'))->firstOrFail();

        // Kirim perintah valid: stop screenshot_service
        $cmdResp = $this->actingAs($this->adminA)->postJson("/api/v1/admin/devices/{$device->id}/commands", [
            'service_key' => 'screenshot_service',
            'action' => 'stop',
            'ttl_seconds' => 120,
        ]);
        $cmdResp->assertCreated()->assertJsonPath('command.action', 'stop');
        $commandId = $cmdResp->json('command.command_id');

        // Tolak perintah shell / exec arbitrer (422)
        foreach (['shell', 'exec', 'powershell', 'cmd'] as $badAction) {
            $this->actingAs($this->adminA)->postJson("/api/v1/admin/devices/{$device->id}/commands", [
                'service_key' => 'screenshot_service',
                'action' => $badAction,
            ])->assertStatus(422);
        }

        // Tolak aksi di luar supported_actions service (tray_service tidak mendukung pause)
        $this->actingAs($this->adminA)->postJson("/api/v1/admin/devices/{$device->id}/commands", [
            'service_key' => 'tray_service',
            'action' => 'pause',
        ])->assertStatus(422);

        // Cancel command pending
        $this->actingAs($this->adminA)
            ->postJson("/api/v1/admin/commands/{$commandId}/cancel")
            ->assertOk()
            ->assertJsonPath('command.status', ServiceCommand::STATUS_CANCELLED);

        // Revoke device dari admin -> token perangkat langsung mati (401)
        $this->actingAs($this->adminA)
            ->postJson("/api/v1/admin/devices/{$device->id}/revoke", ['reason' => 'suspicious_activity'])
            ->assertOk()
            ->assertJsonPath('device.status', AppDevice::STATUS_REVOKED);

        $this->withToken($deviceToken)->getJson('/api/v1/agent/commands/pending')->assertStatus(401);

        // Pastikan seluruh tindakan admin terekam sebagai admin_audit di service_logs
        $auditCount = ServiceLog::where('event_type', 'admin_audit')
            ->where('actor_user_id', $this->adminA->id)
            ->count();
        $this->assertGreaterThanOrEqual(5, $auditCount);
    }

    public function test_config_versioning_schema_validation_and_rollback(): void
    {
        $app = ManagedApp::create([
            'owner_id' => $this->adminA->id,
            'name' => 'LoveYou Config App',
            'slug' => 'loveyou-cfg',
            'is_active' => true,
        ]);

        // v1 valid
        $v1 = $this->actingAs($this->adminA)->putJson("/api/v1/admin/configs/{$app->id}/screenshot_service", [
            'config_json' => ['interval_seconds' => 5, 'image_format' => 'webp', 'jpg_quality' => 75],
            'change_notes' => 'Set interval 5s',
        ]);
        $v1->assertCreated()->assertJsonPath('config.config_version', 1);

        // Invalid schema: nilai di luar batas & field terlarang ditolak 422
        $this->actingAs($this->adminA)->putJson("/api/v1/admin/configs/{$app->id}/screenshot_service", [
            'config_json' => ['interval_seconds' => 0],
        ])->assertStatus(422);

        $this->actingAs($this->adminA)->putJson("/api/v1/admin/configs/{$app->id}/screenshot_service", [
            'config_json' => ['shell_cmd' => 'whoami'],
        ])->assertStatus(422);

        // v2 valid (merge partial patch terhadap v1)
        $v2 = $this->actingAs($this->adminA)->putJson("/api/v1/admin/configs/{$app->id}/screenshot_service", [
            'config_json' => ['interval_seconds' => 20],
            'change_notes' => 'Ubah ke 20s',
        ]);
        $v2->assertCreated()
            ->assertJsonPath('config.config_version', 2)
            ->assertJsonPath('config.config_json.interval_seconds', 20)
            ->assertJsonPath('config.config_json.image_format', 'webp');

        // Rollback ke snapshot v1 -> terbit sebagai v3 dengan force_apply=true & interval kembali 5
        $rb = $this->actingAs($this->adminA)->postJson("/api/v1/admin/configs/{$app->id}/screenshot_service/rollback/1");
        $rb->assertOk()
            ->assertJsonPath('config.config_version', 3)
            ->assertJsonPath('config.force_apply', true)
            ->assertJsonPath('config.config_json.interval_seconds', 5);
    }

    public function test_background_jobs_expire_stale_commands_and_detect_offline_devices(): void
    {
        $app = ManagedApp::create([
            'owner_id' => $this->adminA->id,
            'name' => 'LoveYou Jobs App',
            'slug' => 'loveyou-jobs',
            'is_active' => true,
        ]);

        $staleDevice = AppDevice::create([
            'device_uuid' => (string) Str::uuid(),
            'managed_app_id' => $app->id,
            'device_code' => 'DEV-STALE01',
            'hostname' => 'PC-STALE',
            'status' => AppDevice::STATUS_ONLINE,
            'last_seen_at' => now()->subMinutes(5),
        ]);

        $freshDevice = AppDevice::create([
            'device_uuid' => (string) Str::uuid(),
            'managed_app_id' => $app->id,
            'device_code' => 'DEV-FRESH01',
            'hostname' => 'PC-FRESH',
            'status' => AppDevice::STATUS_ONLINE,
            'last_seen_at' => now()->subSeconds(10),
        ]);

        $expiredCmd = ServiceCommand::create([
            'command_id' => (string) Str::uuid(),
            'device_id' => $freshDevice->id,
            'service_key' => 'screenshot_service',
            'action' => 'pause',
            'payload' => [],
            'status' => ServiceCommand::STATUS_PENDING,
            'expires_at' => now()->subSeconds(30),
        ]);

        $activeCmd = ServiceCommand::create([
            'command_id' => (string) Str::uuid(),
            'device_id' => $freshDevice->id,
            'service_key' => 'screenshot_service',
            'action' => 'resume',
            'payload' => [],
            'status' => ServiceCommand::STATUS_PENDING,
            'expires_at' => now()->addMinutes(5),
        ]);

        // Jalankan kedua Queue Job
        ExpireStaleCommandsJob::dispatchSync();
        DetectOfflineDevicesJob::dispatchSync();

        $this->assertSame(ServiceCommand::STATUS_EXPIRED, $expiredCmd->fresh()->status);
        $this->assertSame(ServiceCommand::STATUS_PENDING, $activeCmd->fresh()->status);
        $this->assertSame(AppDevice::STATUS_OFFLINE, $staleDevice->fresh()->status);
        $this->assertSame(AppDevice::STATUS_ONLINE, $freshDevice->fresh()->status);
    }
}
