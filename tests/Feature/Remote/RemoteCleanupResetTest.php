<?php

namespace Tests\Feature\Remote;

use App\Models\AppDevice;
use App\Models\AppProject;
use App\Models\AppRelease;
use App\Models\AppTemplate;
use App\Models\DeviceEnrollment;
use App\Models\DeviceHeartbeat;
use App\Models\ManagedApp;
use App\Models\ServiceCommand;
use App\Models\ServiceConfig;
use App\Models\ServiceDefinition;
use App\Models\ServiceLog;
use App\Models\ServiceStatus;
use App\Models\User;
use App\Services\Remote\AuditLogService;
use App\Services\Remote\CommandDispatcherService;
use App\Services\Remote\DeviceEnrollmentService;
use Database\Seeders\ServiceDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class RemoteCleanupResetTest extends TestCase
{
    use RefreshDatabase;

    private User $adminA;
    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ServiceDefinitionSeeder::class);

        $this->adminA = User::factory()->create([
            'email' => 'admin-a@xoose.local',
            'password' => Hash::make('secret123'),
        ]);
        $this->adminB = User::factory()->create([
            'email' => 'admin-b@xoose.local',
            'password' => Hash::make('secret123'),
        ]);
    }

    /**
     * Helper untuk menyiapkan 1 ManagedApp lengkap dengan enrollment, device,
     * heartbeat, status, command, versioned config (v1 -> v2), release, dan logs.
     *
     * @return array{app: ManagedApp, device: AppDevice, enrollment: DeviceEnrollment, command: ServiceCommand}
     */
    private function seedFullAppData(User $owner, string $slug, ?AppProject $project = null): array
    {
        $app = ManagedApp::create([
            'app_project_id' => $project?->id,
            'owner_id' => $owner->id,
            'name' => 'App ' . strtoupper($slug),
            'slug' => $slug,
            'current_version' => '1.0.0',
            'build_status' => 'idle',
            'default_config' => [],
            'is_active' => true,
        ]);

        $enrollmentService = app(DeviceEnrollmentService::class);
        $enrollment = $enrollmentService->createEnrollmentCode(
            app: $app,
            actor: $owner,
            label: 'Test Device ' . $slug,
            maxUses: 2,
            expiresInHours: 24,
        );

        $enrolled = $enrollmentService->enrollDevice([
            'enrollment_code' => $enrollment->enrollment_code,
            'device_uuid' => (string) Str::uuid(),
            'hostname' => 'HOST-' . strtoupper($slug),
            'os_info' => 'Windows 11 Pro',
            'app_slug' => $slug,
            'app_version' => '1.0.0',
        ], '127.0.0.1');

        /** @var AppDevice $device */
        $device = $enrolled['device'];

        DeviceHeartbeat::create([
            'device_id' => $device->id,
            'app_version' => '1.0.0',
            'agent_uptime' => 120,
            'ip_address' => '127.0.0.1',
            'services_summary' => ['screenshot_service' => 'Running'],
            'received_at' => now(),
        ]);

        ServiceStatus::create([
            'device_id' => $device->id,
            'service_key' => 'screenshot_service',
            'current_status' => 'Running',
            'active_config_version' => 2,
            'details' => ['ok' => true],
            'reported_at' => now(),
        ]);

        $command = app(CommandDispatcherService::class)->dispatch(
            device: $device,
            serviceKey: 'screenshot_service',
            action: 'stop',
            payload: [],
            actor: $owner,
        );

        $cfgV1 = ServiceConfig::create([
            'managed_app_id' => $app->id,
            'device_id' => null,
            'service_key' => 'screenshot_service',
            'config_version' => 1,
            'config_json' => ['interval_seconds' => 10],
            'is_active' => false,
            'force_apply' => false,
            'updated_by' => $owner->id,
        ]);

        ServiceConfig::create([
            'managed_app_id' => $app->id,
            'device_id' => null,
            'service_key' => 'screenshot_service',
            'config_version' => 2,
            'config_json' => ['interval_seconds' => 15],
            'is_active' => true,
            'force_apply' => true,
            'updated_by' => $owner->id,
            'previous_version_id' => $cfgV1->id,
        ]);

        AppRelease::create([
            'managed_app_id' => $app->id,
            'version' => '1.0.0',
            'artifact_path' => "builds/{$slug}/1.0.0/app.exe",
            'sha256_checksum' => hash('sha256', $slug),
            'is_active' => true,
            'published_by' => $owner->id,
            'published_at' => now(),
        ]);

        app(AuditLogService::class)->adminAction(
            actor: $owner,
            message: "Audit log khusus untuk {$slug}",
            serviceKey: 'app_manager',
            app: $app,
            device: $device,
        );

        return [
            'app' => $app,
            'device' => $device,
            'enrollment' => $enrollment,
            'command' => $command,
        ];
    }

    private function createBuilderProject(string $slug): AppProject
    {
        $template = AppTemplate::create([
            'name' => 'LoveYou Template',
            'slug' => 'loveyou-tpl-' . $slug,
            'version' => '1.0.0',
            'source_path' => 'templates/loveyou',
            'manifest_path' => 'templates/loveyou/manifest.json',
            'is_active' => true,
        ]);

        return AppProject::create([
            'app_template_id' => $template->id,
            'name' => 'Builder Project ' . strtoupper($slug),
            'slug' => $slug,
            'status' => 'ready',
            'features' => ['screenshot' => true],
            'configuration' => [],
            'workspace_path' => 'workspaces/' . $slug,
        ]);
    }

    public function test_guest_cannot_access_cleanup_or_reset_endpoints(): void
    {
        $data = $this->seedFullAppData($this->adminA, 'guest-guard');

        $this->post('/remote/reset')->assertRedirect(route('login'));
        $this->delete("/remote/apps/{$data['app']->id}")->assertRedirect(route('login'));
        $this->delete("/remote/devices/{$data['device']->id}")->assertRedirect(route('login'));
        $this->delete('/remote/commands')->assertRedirect(route('login'));
        $this->delete('/remote/logs')->assertRedirect(route('login'));
    }

    public function test_deleting_single_managed_app_cascades_children_and_preserves_other_apps_and_builder_projects(): void
    {
        $builderProject = $this->createBuilderProject('builder-safe');
        $appOne = $this->seedFullAppData($this->adminA, 'trial-one', $builderProject);
        $appTwo = $this->seedFullAppData($this->adminA, 'trial-two');
        $appOtherOwner = $this->seedFullAppData($this->adminB, 'other-owner');

        // Admin B dilarang menghapus app milik Admin A (403)
        $this->actingAs($this->adminB)
            ->delete("/remote/apps/{$appOne['app']->id}")
            ->assertForbidden();
        $this->assertDatabaseHas('managed_apps', ['id' => $appOne['app']->id]);

        // Admin A menghapus appOne
        $this->actingAs($this->adminA)
            ->delete("/remote/apps/{$appOne['app']->id}")
            ->assertRedirect(route('remote.apps.index'))
            ->assertSessionHas('success');

        // Semua anak dari appOne hilang
        $this->assertDatabaseMissing('managed_apps', ['id' => $appOne['app']->id]);
        $this->assertDatabaseMissing('app_devices', ['id' => $appOne['device']->id]);
        $this->assertDatabaseMissing('device_enrollments', ['managed_app_id' => $appOne['app']->id]);
        $this->assertDatabaseMissing('service_commands', ['device_id' => $appOne['device']->id]);
        $this->assertDatabaseMissing('service_configs', ['managed_app_id' => $appOne['app']->id]);
        $this->assertDatabaseMissing('service_status', ['device_id' => $appOne['device']->id]);
        $this->assertDatabaseMissing('device_heartbeats', ['device_id' => $appOne['device']->id]);
        $this->assertDatabaseMissing('app_releases', ['managed_app_id' => $appOne['app']->id]);
        $this->assertDatabaseMissing('service_logs', ['managed_app_id' => $appOne['app']->id]);

        // AppProject Builder, appTwo, appOtherOwner, dan katalog service tetap utuh
        $this->assertDatabaseHas('app_projects', ['id' => $builderProject->id]);
        $this->assertDatabaseHas('managed_apps', ['id' => $appTwo['app']->id]);
        $this->assertDatabaseHas('app_devices', ['id' => $appTwo['device']->id]);
        $this->assertDatabaseHas('managed_apps', ['id' => $appOtherOwner['app']->id]);
        $this->assertGreaterThanOrEqual(7, ServiceDefinition::count());
    }

    public function test_reset_fresh_wipes_all_admin_remote_data_without_touching_users_builder_or_other_admins(): void
    {
        $builderProject = $this->createBuilderProject('builder-keep');
        $appA1 = $this->seedFullAppData($this->adminA, 'rge-trial', $builderProject);
        $appA2 = $this->seedFullAppData($this->adminA, 'nopal-trial');
        $appB = $this->seedFullAppData($this->adminB, 'admin-b-app');

        // Tambah log login/auth tanpa managed_app_id untuk Admin A dan Admin B
        app(AuditLogService::class)->adminAction(
            actor: $this->adminA,
            message: 'Admin A masuk ke dashboard.',
            serviceKey: 'auth',
        );
        app(AuditLogService::class)->adminAction(
            actor: $this->adminB,
            message: 'Admin B masuk ke dashboard.',
            serviceKey: 'auth',
        );

        $serviceDefCountBefore = ServiceDefinition::count();

        // Admin A menjalankan Reset Fresh Semua
        $this->actingAs($this->adminA)
            ->post('/remote/reset')
            ->assertRedirect(route('remote.apps.index'))
            ->assertSessionHas('success');

        // Semua data Remote Manager milik Admin A bersih 100%
        $this->assertSame(0, ManagedApp::where('owner_id', $this->adminA->id)->count());
        $this->assertDatabaseMissing('app_devices', ['id' => $appA1['device']->id]);
        $this->assertDatabaseMissing('app_devices', ['id' => $appA2['device']->id]);
        $this->assertDatabaseMissing('device_enrollments', ['managed_app_id' => $appA1['app']->id]);
        $this->assertDatabaseMissing('device_enrollments', ['managed_app_id' => $appA2['app']->id]);
        $this->assertDatabaseMissing('service_commands', ['device_id' => $appA1['device']->id]);
        $this->assertDatabaseMissing('service_commands', ['device_id' => $appA2['device']->id]);
        $this->assertDatabaseMissing('service_configs', ['managed_app_id' => $appA1['app']->id]);
        $this->assertDatabaseMissing('app_releases', ['managed_app_id' => $appA1['app']->id]);
        $this->assertSame(0, ServiceLog::where('actor_user_id', $this->adminA->id)->count());
        $this->assertSame(0, ServiceLog::whereIn('managed_app_id', [$appA1['app']->id, $appA2['app']->id])->count());

        // Halaman /remote/logs untuk Admin A benar-benar kosong (fresh)
        $this->actingAs($this->adminA)
            ->get('/remote/logs')
            ->assertOk()
            ->assertSee('Belum ada log atau audit trail yang tercatat.');

        // Akun User, ServiceDefinitions, AppProject Builder, dan seluruh data Admin B tetap 100% aman
        $this->assertDatabaseHas('users', ['id' => $this->adminA->id]);
        $this->assertDatabaseHas('users', ['id' => $this->adminB->id]);
        $this->assertSame($serviceDefCountBefore, ServiceDefinition::count());
        $this->assertDatabaseHas('app_projects', ['id' => $builderProject->id]);
        $this->assertDatabaseHas('managed_apps', ['id' => $appB['app']->id]);
        $this->assertDatabaseHas('app_devices', ['id' => $appB['device']->id]);
        $this->assertDatabaseHas('service_commands', ['id' => $appB['command']->id]);
        $this->assertGreaterThan(0, ServiceLog::where('actor_user_id', $this->adminB->id)->count());

        // Menekan Reset Fresh kedua kali ketika sudah bersih tetap sukses tanpa error
        $this->actingAs($this->adminA)
            ->post('/remote/reset')
            ->assertRedirect(route('remote.apps.index'))
            ->assertSessionHas('success');
    }

    public function test_deleting_single_device_and_clearing_commands_and_logs_enforce_ownership(): void
    {
        $dataA = $this->seedFullAppData($this->adminA, 'alpha-app');
        $dataB = $this->seedFullAppData($this->adminB, 'beta-app');

        // Tambah log auth untuk Admin A & Admin B
        app(AuditLogService::class)->adminAction(
            actor: $this->adminA,
            message: 'Auth log Admin A',
            serviceKey: 'auth',
        );
        app(AuditLogService::class)->adminAction(
            actor: $this->adminB,
            message: 'Auth log Admin B',
            serviceKey: 'auth',
        );

        // UI tombol terlihat di semua halaman terkait
        $this->actingAs($this->adminA)->get('/remote/apps')
            ->assertOk()
            ->assertSee('Reset Fresh Semua')
            ->assertSee('Hapus');
        $this->actingAs($this->adminA)->get('/remote/devices')
            ->assertOk()
            ->assertSee('Hapus');
        $this->actingAs($this->adminA)->get("/remote/devices/{$dataA['device']->id}")
            ->assertOk()
            ->assertSee('Hapus Perangkat');
        $this->actingAs($this->adminA)->get('/remote/commands')
            ->assertOk()
            ->assertSee('Bersihkan Riwayat');
        $this->actingAs($this->adminA)->get('/remote/logs')
            ->assertOk()
            ->assertSee('Bersihkan Semua Log');

        // Clear Commands hanya menghapus command milik Admin A
        $this->actingAs($this->adminA)
            ->delete('/remote/commands')
            ->assertRedirect(route('remote.commands.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('service_commands', ['id' => $dataA['command']->id]);
        $this->assertDatabaseHas('service_commands', ['id' => $dataB['command']->id]);
        $this->assertDatabaseHas('app_devices', ['id' => $dataA['device']->id]);

        // Clear Logs hanya menghapus log milik Admin A
        $this->actingAs($this->adminA)
            ->delete('/remote/logs')
            ->assertRedirect(route('remote.logs.index'))
            ->assertSessionHas('success');

        $this->assertSame(0, ServiceLog::where('managed_app_id', $dataA['app']->id)->count());
        $this->assertSame(0, ServiceLog::where('actor_user_id', $this->adminA->id)->count());
        $this->assertGreaterThan(0, ServiceLog::where('managed_app_id', $dataB['app']->id)->count());
        $this->assertGreaterThan(0, ServiceLog::where('actor_user_id', $this->adminB->id)->count());

        // Admin B dilarang menghapus device milik Admin A (403)
        $this->actingAs($this->adminB)
            ->delete("/remote/devices/{$dataA['device']->id}")
            ->assertForbidden();

        // Admin A menghapus device miliknya sendiri tanpa menghapus ManagedApp
        $this->actingAs($this->adminA)
            ->delete("/remote/devices/{$dataA['device']->id}")
            ->assertRedirect(route('remote.devices.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('app_devices', ['id' => $dataA['device']->id]);
        $this->assertDatabaseMissing('device_heartbeats', ['device_id' => $dataA['device']->id]);
        $this->assertDatabaseMissing('service_status', ['device_id' => $dataA['device']->id]);
        $this->assertDatabaseHas('managed_apps', ['id' => $dataA['app']->id]);
        $this->assertNull($dataA['enrollment']->fresh()->enrolled_device_id);
        $this->assertDatabaseHas('app_devices', ['id' => $dataB['device']->id]);
    }
}
