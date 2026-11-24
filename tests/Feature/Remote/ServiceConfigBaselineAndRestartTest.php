<?php

namespace Tests\Feature\Remote;

use App\Models\AppDevice;
use App\Models\AppProject;
use App\Models\AppTemplate;
use App\Models\ManagedApp;
use App\Models\ServiceCommand;
use App\Models\ServiceConfig;
use App\Models\User;
use App\Services\Remote\DeviceEnrollmentService;
use Database\Seeders\ServiceDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ServiceConfigBaselineAndRestartTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ServiceDefinitionSeeder::class);
        $this->admin = User::factory()->create();
    }

    private function createProjectAppAndDevice(): array
    {
        $template = AppTemplate::create([
            'name' => 'LoveYou Template',
            'slug' => 'loveyou',
            'version' => '1.0.0',
            'source_path' => 'D:/KULIAH/KODINGAN/screenshotusingpy',
            'is_active' => true,
        ]);

        $project = AppProject::create([
            'app_template_id' => $template->id,
            'name' => 'LoveYou Ongoing',
            'slug' => 'loveyou-ongoing',
            'status' => 'built',
            'features' => [
                'keep_local_file' => false,
                'tray_notifications' => false,
                'emergency_uninstall_hotkey' => true,
            ],
            'configuration' => [
                'interval_seconds' => 5,
                'image_format' => 'webp',
                'jpg_quality' => 100,
                'date_invitation_text' => 'MINGGU, 10 JANUARI 2027',
                'date_countdown_target' => '2027-01-10T12:00:00Z',
                'uninstall_hotkey_combo' => 'Ctrl+Alt+Q',
                'icon' => 'heart.ico',
            ],
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->post('/remote/apps', [
            'name' => 'LoveYou Ongoing',
            'slug' => 'loveyou-ongoing',
            'app_project_id' => $project->id,
            'current_version' => '1.0.0',
        ])->assertRedirect(route('remote.apps.index'));

        $managedApp = ManagedApp::where('slug', 'loveyou-ongoing')->firstOrFail();

        $enrollment = app(DeviceEnrollmentService::class)->createEnrollmentCode(
            app: $managedApp,
            actor: $this->admin,
            label: 'Laptop User',
            maxUses: 2,
        );

        $enrolled = app(DeviceEnrollmentService::class)->enrollDevice([
            'enrollment_code' => $enrollment->enrollment_code,
            'device_uuid' => (string) Str::uuid(),
            'hostname' => 'LAPTOP-USER-01',
            'os_info' => 'Windows 11',
            'app_slug' => 'loveyou-ongoing',
            'app_version' => '1.0.0',
        ], '127.0.0.1');

        return [
            'project' => $project,
            'app' => $managedApp,
            'device' => $enrolled['device'],
            'token' => $enrolled['device_token'],
        ];
    }

    public function test_device_and_app_service_config_inherit_app_builder_baseline_and_show_app_version(): void
    {
        ['app' => $app, 'device' => $device] = $this->createProjectAppAndDevice();

        // ManagedApp otomatis menyimpan baseline default_config dari AppProject
        $this->assertSame(5, $app->default_config['screenshot_service']['interval_seconds'] ?? null);
        $this->assertSame('webp', $app->default_config['screenshot_service']['image_format'] ?? null);
        $this->assertSame(100, $app->default_config['screenshot_service']['jpg_quality'] ?? null);

        // Scope App menampilkan versi v1.0.0 dan nilai awal App Builder (5s, webp, 100)
        $appPage = $this->actingAs($this->admin)
            ->get("/remote/configs/app-{$app->id}/screenshot_service");

        $appPage->assertOk()
            ->assertSee('v1.0.0')
            ->assertSee('Baseline App Builder (App v1.0.0)')
            ->assertSee('name="config[interval_seconds]"', false)
            ->assertSee('value="5"', false)
            ->assertSee('value="100"', false)
            ->assertSee('Restart UI Service')
            ->assertSee('Simpan &amp; Terbitkan Versi Baru', false);

        // Scope Device juga menampilkan versi v1.0.0 dan mewarisi 5s, webp, 100% dari App Builder
        $devicePage = $this->actingAs($this->admin)
            ->get("/remote/configs/device-{$device->id}/screenshot_service");

        $devicePage->assertOk()
            ->assertSee('v1.0.0')
            ->assertSee('Baseline App Builder (App v1.0.0)')
            ->assertSee('value="5"', false)
            ->assertSee('value="100"', false)
            ->assertSee('Restart UI Service');

        // Saat App config di-update ke v1 (interval 12s) dan v2 (interval 15s),
        // Device override v1 berikutnya memakai force_apply=true agar Agent di v2 tetap menerapkan override perangkat.
        $this->actingAs($this->admin)->put("/remote/configs/app-{$app->id}/screenshot_service", [
            'config' => [
                'interval_seconds' => 12,
                'image_format' => 'webp',
                'jpg_quality' => 100,
                'keep_local_file' => '0',
                'local_folder' => 'screenshots',
            ],
        ])->assertSessionHas('success');

        $this->actingAs($this->admin)->put("/remote/configs/app-{$app->id}/screenshot_service", [
            'config' => [
                'interval_seconds' => 15,
                'image_format' => 'webp',
                'jpg_quality' => 100,
                'keep_local_file' => '0',
                'local_folder' => 'screenshots',
            ],
        ])->assertSessionHas('success');

        // Halaman Device sekarang mewarisi App Config v2 (15s)
        $this->actingAs($this->admin)
            ->get("/remote/configs/device-{$device->id}/screenshot_service")
            ->assertOk()
            ->assertSee('Warisan App Config v2')
            ->assertSee('value="15"', false);

        // Simpan Device Override v1 (8s) -> force_apply = true
        $this->actingAs($this->admin)->put("/remote/configs/device-{$device->id}/screenshot_service", [
            'config' => [
                'interval_seconds' => 8,
                'image_format' => 'webp',
                'jpg_quality' => 100,
                'keep_local_file' => '0',
                'local_folder' => 'screenshots',
            ],
        ])->assertSessionHas('success');

        $deviceCfg = ServiceConfig::where('device_id', $device->id)
            ->where('service_key', 'screenshot_service')
            ->where('is_active', true)
            ->firstOrFail();

        $this->assertSame(1, $deviceCfg->config_version);
        $this->assertTrue($deviceCfg->force_apply);
        $this->assertSame(8, $deviceCfg->config_json['interval_seconds']);
        $this->assertSame(100, $deviceCfg->config_json['jpg_quality']);
    }

    public function test_restart_ui_service_button_dispatches_restart_command_for_device_and_app_scopes(): void
    {
        ['app' => $app, 'device' => $device] = $this->createProjectAppAndDevice();

        // 1. Restart UI Service dari scope Device
        $this->actingAs($this->admin)
            ->from("/remote/configs/device-{$device->id}/screenshot_service")
            ->post("/remote/configs/device-{$device->id}/restart-ui")
            ->assertRedirect("/remote/configs/device-{$device->id}/screenshot_service")
            ->assertSessionHas('success');

        $cmd = ServiceCommand::where('device_id', $device->id)
            ->where('service_key', 'ui_service')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('restart', $cmd->action);
        $this->assertSame(ServiceCommand::STATUS_PENDING, $cmd->status);

        // 2. Restart UI Service dari scope App (broadcast ke semua perangkat aktif milik app)
        $this->actingAs($this->admin)
            ->from("/remote/configs/app-{$app->id}/screenshot_service")
            ->post("/remote/configs/app-{$app->id}/restart-ui")
            ->assertRedirect("/remote/configs/app-{$app->id}/screenshot_service")
            ->assertSessionHas('success');

        $this->assertSame(
            2,
            ServiceCommand::where('device_id', $device->id)
                ->where('service_key', 'ui_service')
                ->where('action', 'restart')
                ->count()
        );
    }

    public function test_emergency_hotkey_service_inherits_builder_keybind_and_updates_at_runtime(): void
    {
        ['app' => $app, 'device' => $device, 'token' => $token] = $this->createProjectAppAndDevice();

        // Scope Device untuk hotkey_service menampilkan keybind bawaan App Builder (Ctrl+Alt+Q)
        $page = $this->actingAs($this->admin)
            ->get("/remote/configs/device-{$device->id}/hotkey_service");

        $page->assertOk()
            ->assertSee('Emergency Hotkey')
            ->assertSee('Baseline App Builder (App v1.0.0)')
            ->assertSee('Ctrl+Alt+Q');

        // Ubah shortcut runtime ke Ctrl+Shift+End pada perangkat
        $this->actingAs($this->admin)->put("/remote/configs/device-{$device->id}/hotkey_service", [
            'config' => [
                'enabled' => '1',
                'hotkey_combo' => 'Ctrl+Shift+End',
                'keep_backup_hotkey' => '1',
                'clean_local_screenshots' => '1',
                'unregister_startup' => '1',
            ],
            'change_notes' => 'Ganti shortcut uninstall ke Ctrl+Shift+End',
        ])->assertSessionHas('success');

        // Agent menarik config runtime dan menerima hotkey_service v1
        $agentRes = $this->withToken($token)->getJson('/api/v1/agent/configs?versions[hotkey_service]=0');
        $agentRes->assertOk()
            ->assertJsonPath('configs.0.service_key', 'hotkey_service')
            ->assertJsonPath('configs.0.config_version', 1)
            ->assertJsonPath('configs.0.config_json.hotkey_combo', 'Ctrl+Shift+End')
            ->assertJsonPath('configs.0.config_json.clean_local_screenshots', true)
            ->assertJsonPath('configs.0.config_json.unregister_startup', true);

        // Kombinasi tidak valid ditolak oleh schema validator
        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/configs/{$app->id}/hotkey_service", [
                'config_json' => ['hotkey_combo' => 'Alt+F4'],
            ])
            ->assertStatus(422);
    }
}
