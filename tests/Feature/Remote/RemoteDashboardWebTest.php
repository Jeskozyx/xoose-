<?php

namespace Tests\Feature\Remote;

use App\Models\AppDevice;
use App\Models\AppRelease;
use App\Models\ManagedApp;
use App\Models\ServiceCommand;
use App\Models\ServiceConfig;
use App\Models\ServiceLog;
use App\Models\User;
use App\Services\Remote\DeviceEnrollmentService;
use Database\Seeders\ServiceDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class RemoteDashboardWebTest extends TestCase
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

    public function test_admin_login_logout_and_guest_redirect_protection(): void
    {
        // Guest diarahkan ke halaman login Xoose
        $this->get('/remote/apps')->assertRedirect(route('login'));
        $this->get('/remote/devices')->assertRedirect(route('login'));
        $this->get('/remote/commands')->assertRedirect(route('login'));
        $this->get('/remote/logs')->assertRedirect(route('login'));
        $this->get('/remote/releases')->assertRedirect(route('login'));

        // Halaman login Xoose tampil normal
        $this->get('/login')
            ->assertOk()
            ->assertSee('Xoose Control Plane');

        // Password salah ditolak
        $this->post('/login', [
            'email' => 'admin-a@xoose.local',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        // Login valid berhasil dan mengarahkan ke halaman intended terakhir (/remote/releases)
        $this->post('/login', [
            'email' => 'admin-a@xoose.local',
            'password' => 'secret123',
        ])->assertRedirect(route('remote.releases.index'));
        $this->assertAuthenticatedAs($this->adminA);

        // Logout membersihkan sesi
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_all_eight_remote_dashboard_pages_and_workflows_work_end_to_end(): void
    {
        // 1. Application Manager: create app & enrollment code
        $this->actingAs($this->adminA)
            ->get('/remote/apps')
            ->assertOk()
            ->assertSee('Application Manager');

        $this->actingAs($this->adminA)
            ->post('/remote/apps', [
                'name' => 'LoveYou Dashboard App',
                'slug' => 'loveyou-dash',
                'current_version' => '1.0.0',
            ])
            ->assertRedirect(route('remote.apps.index'));

        $managedApp = ManagedApp::where('slug', 'loveyou-dash')->firstOrFail();

        $this->actingAs($this->adminA)
            ->post("/remote/apps/{$managedApp->id}/enrollments", [
                'label' => 'Laptop Utama',
                'max_uses' => 2,
            ])
            ->assertRedirect(route('remote.apps.index'))
            ->assertSessionHas('generated_enrollment_code');

        // Daftarkan perangkat via EnrollmentService
        $code = $managedApp->enrollments()->latest()->firstOrFail()->enrollment_code;
        $enrolled = app(DeviceEnrollmentService::class)->enrollDevice([
            'enrollment_code' => $code,
            'device_uuid' => (string) Str::uuid(),
            'hostname' => 'LAPTOP-DASH-01',
            'os_info' => 'Windows 11 Pro',
            'app_slug' => 'loveyou-dash',
            'app_version' => '1.0.0',
        ], '127.0.0.1');
        /** @var AppDevice $device */
        $device = $enrolled['device'];

        // 2. Device Manager
        $this->actingAs($this->adminA)
            ->get('/remote/devices')
            ->assertOk()
            ->assertSee('Device Manager')
            ->assertSee('LAPTOP-DASH-01');

        // 3. Device Detail
        $this->actingAs($this->adminA)
            ->get("/remote/devices/{$device->id}")
            ->assertOk()
            ->assertSee('LAPTOP-DASH-01')
            ->assertSee('Screenshot Service')
            ->assertSee('Game Service');

        // Admin B diblokir oleh Policy (403)
        $this->actingAs($this->adminB)
            ->get("/remote/devices/{$device->id}")
            ->assertStatus(403);

        // 4. Service Manager: view & dispatch allowlisted STOP command + reject arbitrary shell
        $this->actingAs($this->adminA)
            ->get("/remote/devices/{$device->id}/services")
            ->assertOk()
            ->assertSee('Service Manager')
            ->assertSee('screenshot_service');

        $this->actingAs($this->adminA)
            ->post("/remote/devices/{$device->id}/services/screenshot_service/command", [
                'action' => 'stop',
            ])
            ->assertSessionHas('success');

        $cmd = ServiceCommand::where('device_id', $device->id)->latest()->firstOrFail();
        $this->assertSame('stop', $cmd->action);

        // Aksi shell ditolak tanpa membuat record baru
        $beforeCount = ServiceCommand::count();
        $this->actingAs($this->adminA)
            ->post("/remote/devices/{$device->id}/services/screenshot_service/command", [
                'action' => 'powershell',
            ])
            ->assertSessionHas('error');
        $this->assertSame($beforeCount, ServiceCommand::count());

        // 5. Service Configuration: index redirect, edit, update v1 -> v2, rollback to v1 -> v3
        $this->actingAs($this->adminA)
            ->get('/remote/configs')
            ->assertRedirect(route('remote.configs.edit', [
                'scope' => "app-{$managedApp->id}",
                'service' => 'screenshot_service',
            ]));

        $this->actingAs($this->adminA)
            ->get("/remote/configs/app-{$managedApp->id}/screenshot_service")
            ->assertOk()
            ->assertSee('Service Configuration (Runtime)');

        $this->actingAs($this->adminA)
            ->put("/remote/configs/app-{$managedApp->id}/screenshot_service", [
                'config' => [
                    'interval_seconds' => 6,
                    'image_format' => 'webp',
                    'jpg_quality' => 75,
                    'keep_local_file' => '0',
                    'local_folder' => 'screenshots',
                ],
                'change_notes' => 'Versi awal 6s',
            ])
            ->assertSessionHas('success');

        $this->actingAs($this->adminA)
            ->put("/remote/configs/app-{$managedApp->id}/screenshot_service", [
                'config' => [
                    'interval_seconds' => 15,
                    'image_format' => 'webp',
                    'jpg_quality' => 80,
                    'keep_local_file' => '0',
                    'local_folder' => 'screenshots',
                ],
                'change_notes' => 'Naik ke 15s',
            ])
            ->assertSessionHas('success');

        $this->actingAs($this->adminA)
            ->post("/remote/configs/app-{$managedApp->id}/screenshot_service/rollback/1")
            ->assertSessionHas('success');

        $latestCfg = ServiceConfig::where('managed_app_id', $managedApp->id)
            ->where('service_key', 'screenshot_service')
            ->where('is_active', true)
            ->firstOrFail();
        $this->assertSame(3, $latestCfg->config_version);
        $this->assertTrue($latestCfg->force_apply);
        $this->assertSame(6, $latestCfg->config_json['interval_seconds']);

        // 6. Command History: list, cancel, retry
        $this->actingAs($this->adminA)
            ->get('/remote/commands')
            ->assertOk()
            ->assertSee($cmd->command_id);

        $this->actingAs($this->adminA)
            ->post("/remote/commands/{$cmd->command_id}/cancel")
            ->assertSessionHas('success');
        $this->assertSame(ServiceCommand::STATUS_CANCELLED, $cmd->fresh()->status);

        $this->actingAs($this->adminA)
            ->post("/remote/commands/{$cmd->command_id}/retry")
            ->assertSessionHas('success');
        $this->assertSame(2, ServiceCommand::where('device_id', $device->id)->count());

        // 7. Logs & Monitoring
        $this->actingAs($this->adminA)
            ->get('/remote/logs')
            ->assertOk()
            ->assertSee('Logs, Errors & Admin Audit Trail', false)
            ->assertSee('admin_audit');

        // 8. Releases & Updates: list, publish v1.1.0 & v1.2.0, activate v1.1.0
        $this->actingAs($this->adminA)
            ->get('/remote/releases')
            ->assertOk()
            ->assertSee('Releases & Signed Updates', false);

        $sha1 = hash('sha256', 'pkg-v1.1.0');
        $sha2 = hash('sha256', 'pkg-v1.2.0');

        $this->actingAs($this->adminA)->post('/remote/releases', [
            'managed_app_id' => $managedApp->id,
            'version' => '1.1.0',
            'artifact_path' => 'builds/loveyou-dash/1.1.0/LoveYou.exe',
            'sha256_checksum' => $sha1,
            'release_notes' => 'Release 1.1.0',
        ])->assertRedirect(route('remote.releases.index'));

        $this->actingAs($this->adminA)->post('/remote/releases', [
            'managed_app_id' => $managedApp->id,
            'version' => '1.2.0',
            'artifact_path' => 'builds/loveyou-dash/1.2.0/LoveYou.exe',
            'sha256_checksum' => $sha2,
            'release_notes' => 'Release 1.2.0',
        ])->assertRedirect(route('remote.releases.index'));

        $rel110 = AppRelease::where('managed_app_id', $managedApp->id)->where('version', '1.1.0')->firstOrFail();
        $this->actingAs($this->adminA)
            ->post("/remote/releases/{$rel110->id}/activate")
            ->assertSessionHas('success');

        $this->assertTrue($rel110->fresh()->is_active);
        $this->assertSame('1.1.0', $managedApp->fresh()->current_version);

        // Revoke device via Web Dashboard
        $this->actingAs($this->adminA)
            ->post("/remote/devices/{$device->id}/revoke")
            ->assertSessionHas('success');
        $this->assertTrue($device->fresh()->isRevoked());
        $this->assertGreaterThan(0, ServiceLog::where('event_type', 'admin_audit')->count());
    }
}
