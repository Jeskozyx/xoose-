<?php

namespace Tests\Feature\Remote;

use App\Models\AppDevice;
use App\Models\AppRelease;
use App\Models\DeviceEnrollment;
use App\Models\ManagedApp;
use App\Models\ServiceCommand;
use App\Models\ServiceLog;
use App\Models\User;
use App\Services\Remote\CommandDispatcherService;
use App\Services\Remote\ConfigVersionService;
use App\Services\Remote\DeviceEnrollmentService;
use App\Services\Remote\ReleaseIntegrityService;
use Database\Seeders\ServiceDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgentControlPlaneApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private ManagedApp $managedApp;
    private DeviceEnrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ServiceDefinitionSeeder::class);

        $this->admin = User::factory()->create();
        $this->managedApp = ManagedApp::create([
            'owner_id' => $this->admin->id,
            'name' => 'LoveYou Primary',
            'slug' => 'loveyou',
            'current_version' => '1.0.0',
            'is_active' => true,
        ]);

        $this->enrollment = app(DeviceEnrollmentService::class)->createEnrollmentCode(
            app: $this->managedApp,
            actor: $this->admin,
            label: 'Laptop Target 1',
            maxUses: 2,
            expiresInHours: 24,
        );
    }

    private function enrollTestDevice(?string $uuid = null): array
    {
        $deviceUuid = $uuid ?? (string) Str::uuid();

        $resp = $this->postJson('/api/v1/agent/enroll', [
            'enrollment_code' => $this->enrollment->enrollment_code,
            'device_uuid' => $deviceUuid,
            'hostname' => 'DESKTOP-LOVEYOU-01',
            'os_info' => 'Windows 11',
            'app_slug' => 'loveyou',
            'app_version' => '1.0.0',
            'services' => [
                [
                    'service_key' => 'screenshot_service',
                    'display_name' => 'Screenshot Service',
                    'supported_actions' => ['start', 'stop', 'pause', 'resume', 'restart'],
                    'current_status' => 'Running',
                    'active_config_version' => 0,
                ],
            ],
        ]);

        $resp->assertCreated()
            ->assertJsonStructure(['ok', 'device_id', 'device_code', 'device_token']);

        return [
            'device' => AppDevice::where('device_uuid', $deviceUuid)->firstOrFail(),
            'token' => $resp->json('device_token'),
        ];
    }

    public function test_device_enrollment_issues_unique_hashed_token_and_rejects_invalid_codes(): void
    {
        // Kode invalid ditolak 422
        $this->postJson('/api/v1/agent/enroll', [
            'enrollment_code' => 'INVALID-CODE-999',
            'device_uuid' => (string) Str::uuid(),
            'hostname' => 'PC-BAD',
        ])->assertStatus(422);

        // Kode expired ditolak 422
        $expired = DeviceEnrollment::create([
            'managed_app_id' => $this->managedApp->id,
            'enrollment_code' => 'XLY-EXPIRED-0001',
            'status' => DeviceEnrollment::STATUS_PENDING,
            'max_uses' => 1,
            'uses_count' => 0,
            'expires_at' => now()->subMinutes(5),
        ]);
        $this->postJson('/api/v1/agent/enroll', [
            'enrollment_code' => $expired->enrollment_code,
            'device_uuid' => (string) Str::uuid(),
            'hostname' => 'PC-EXP',
        ])->assertStatus(422);

        // Enrollment valid menerbitkan token unik & hanya menyimpan hash SHA-256 di DB
        $enrolled = $this->enrollTestDevice();
        /** @var AppDevice $device */
        $device = $enrolled['device'];
        $rawToken = $enrolled['token'];

        $this->assertNotEmpty($rawToken);
        $this->assertSame(hash('sha256', $rawToken), $device->token_hash);
        $this->assertNotSame($rawToken, $device->token_hash);
        $this->assertSame(AppDevice::STATUS_ONLINE, $device->status);
        $this->assertDatabaseHas('service_status', [
            'device_id' => $device->id,
            'service_key' => 'screenshot_service',
            'current_status' => 'Running',
        ]);
    }

    public function test_agent_endpoints_require_valid_revocable_bearer_token(): void
    {
        $this->postJson('/api/v1/agent/heartbeat', [])->assertStatus(401);
        $this->getJson('/api/v1/agent/commands/pending', [
            'Authorization' => 'Bearer invalid_token_123',
        ])->assertStatus(401);
    }

    public function test_heartbeat_updates_device_online_and_service_statuses(): void
    {
        ['device' => $device, 'token' => $token] = $this->enrollTestDevice();

        $resp = $this->withToken($token)->postJson('/api/v1/agent/heartbeat', [
            'app_version' => '1.0.1',
            'agent_uptime' => 142.5,
            'services' => [
                'screenshot_service' => ['status' => 'Running', 'active_config_version' => 2],
                'tray_service' => ['status' => 'Running', 'active_config_version' => 1],
            ],
        ]);

        $resp->assertOk()->assertJson(['ok' => true, 'status' => 'Online']);

        $device->refresh();
        $this->assertSame('1.0.1', $device->app_version);
        $this->assertSame(AppDevice::STATUS_ONLINE, $device->status);
        $this->assertDatabaseCount('device_heartbeats', 1);
        $this->assertDatabaseHas('service_status', [
            'device_id' => $device->id,
            'service_key' => 'screenshot_service',
            'current_status' => 'Running',
            'active_config_version' => 2,
        ]);
    }

    public function test_command_polling_delivery_expiration_and_idempotent_acknowledgment(): void
    {
        ['device' => $device1, 'token' => $token1] = $this->enrollTestDevice();
        ['token' => $token2] = $this->enrollTestDevice();

        $dispatcher = app(CommandDispatcherService::class);
        $activeCmd = $dispatcher->dispatch($device1, 'screenshot_service', 'stop', [], $this->admin, 300);

        // Buat command yang sudah kedaluwarsa untuk memastikan polling menandainya expired & tidak mengirimkannya
        $expiredCmd = ServiceCommand::create([
            'command_id' => (string) Str::uuid(),
            'device_id' => $device1->id,
            'service_key' => 'screenshot_service',
            'action' => 'pause',
            'payload' => [],
            'status' => ServiceCommand::STATUS_PENDING,
            'expires_at' => now()->subMinutes(2),
        ]);

        // Polling mengembalikan hanya activeCmd & mengubah status pending -> delivered
        $poll = $this->withToken($token1)->getJson('/api/v1/agent/commands/pending');
        $poll->assertOk();
        $commands = $poll->json('commands');
        $this->assertCount(1, $commands);
        $this->assertSame($activeCmd->command_id, $commands[0]['command_id']);
        $this->assertSame($device1->device_uuid, $commands[0]['device_id']);
        $this->assertSame('stop', $commands[0]['action']);

        $this->assertSame(ServiceCommand::STATUS_DELIVERED, $activeCmd->fresh()->status);
        $this->assertSame(ServiceCommand::STATUS_EXPIRED, $expiredCmd->fresh()->status);

        // Perangkat lain tidak boleh mengirim ack atas command milik device1 (403)
        $this->withToken($token2)->postJson("/api/v1/agent/commands/{$activeCmd->command_id}/ack", [
            'status' => 'acknowledged',
        ])->assertStatus(403);

        // Device1 mengirim acknowledged -> succeeded
        $this->withToken($token1)->postJson("/api/v1/agent/commands/{$activeCmd->command_id}/ack", [
            'status' => 'acknowledged',
            'result_message' => 'Menjalankan stop...',
        ])->assertOk()->assertJson(['status' => 'acknowledged']);

        $this->withToken($token1)->postJson("/api/v1/agent/commands/{$activeCmd->command_id}/ack", [
            'status' => 'succeeded',
            'result_message' => 'ScreenshotService dihentikan secara normal.',
            'result_payload' => ['status' => 'Stopped'],
        ])->assertOk()->assertJson(['status' => 'succeeded']);

        // Idempotency: ack susulan tidak mengubah status terminal succeeded
        $this->withToken($token1)->postJson("/api/v1/agent/commands/{$activeCmd->command_id}/ack", [
            'status' => 'failed',
            'result_message' => 'Should be ignored',
        ])->assertOk()->assertJson(['status' => 'succeeded']);
        $this->assertSame(ServiceCommand::STATUS_SUCCEEDED, $activeCmd->fresh()->status);
    }

    public function test_config_sync_status_logs_release_and_self_unregister(): void
    {
        ['device' => $device, 'token' => $token] = $this->enrollTestDevice();

        $configSvc = app(ConfigVersionService::class);
        $configSvc->updateConfig($this->managedApp, 'screenshot_service', ['interval_seconds' => 10, 'image_format' => 'webp'], null, $this->admin);
        // Device-specific override menang atas app default
        $configSvc->updateConfig($this->managedApp, 'screenshot_service', ['interval_seconds' => 15], $device, $this->admin);

        // Poll config dengan versi lokal 0 -> menerima v1 (interval 15)
        $cfgResp = $this->withToken($token)->getJson('/api/v1/agent/configs?versions[screenshot_service]=0');
        $cfgResp->assertOk();
        $this->assertCount(1, $cfgResp->json('configs'));
        $this->assertSame(15, $cfgResp->json('configs.0.config_json.interval_seconds'));

        // Poll config dengan versi lokal 1 -> tidak ada perubahan baru
        $cfgSame = $this->withToken($token)->getJson('/api/v1/agent/configs?versions[screenshot_service]=1');
        $cfgSame->assertOk();
        $this->assertCount(0, $cfgSame->json('configs'));

        // Report granular status
        $this->withToken($token)->postJson('/api/v1/agent/status', [
            'service_key' => 'screenshot_service',
            'current_status' => 'Stopped',
            'active_config_version' => 1,
            'details' => ['reason' => 'remote_stop'],
        ])->assertOk();
        $this->assertDatabaseHas('service_status', [
            'device_id' => $device->id,
            'service_key' => 'screenshot_service',
            'current_status' => 'Stopped',
            'active_config_version' => 1,
        ]);

        // Batch log ingestion
        $this->withToken($token)->postJson('/api/v1/agent/logs', [
            'logs' => [
                ['service_key' => 'screenshot_service', 'level' => 'info', 'message' => 'Graceful stop completed'],
                ['service_key' => 'agent', 'level' => 'warning', 'message' => 'Reconnected after brief offline'],
            ],
        ])->assertStatus(202)->assertJson(['accepted' => 2]);
        $this->assertGreaterThanOrEqual(2, ServiceLog::where('device_id', $device->id)->where('event_type', 'agent_event')->count());

        // Check latest release
        $checksum = hash('sha256', 'dummy-release-artifact');
        app(ReleaseIntegrityService::class)->createRelease($this->managedApp, [
            'version' => '1.2.0',
            'artifact_path' => 'builds/loveyou/1.2.0/LoveYou.exe',
            'sha256_checksum' => $checksum,
            'release_notes' => 'Perbaikan performa',
        ], $this->admin);

        $relResp = $this->withToken($token)->getJson('/api/v1/agent/releases/latest?current_version=1.0.0');
        $relResp->assertOk()
            ->assertJson([
                'ok' => true,
                'update_available' => true,
                'release' => [
                    'version' => '1.2.0',
                    'sha256_checksum' => $checksum,
                ],
            ]);
        $this->assertNotEmpty($relResp->json('release.signature'));

        // Self-unregister mencabut token secara instan
        $this->withToken($token)->postJson('/api/v1/agent/unregister', [
            'reason' => 'emergency_uninstall',
        ])->assertOk();

        $this->assertTrue($device->fresh()->isRevoked());
        $this->withToken($token)->postJson('/api/v1/agent/heartbeat', [])->assertStatus(401);
    }
}
