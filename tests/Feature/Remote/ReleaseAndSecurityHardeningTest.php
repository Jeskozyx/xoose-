<?php

namespace Tests\Feature\Remote;

use App\Models\AppBuild;
use App\Models\AppDevice;
use App\Models\AppProject;
use App\Models\AppRelease;
use App\Models\AppTemplate;
use App\Models\ManagedApp;
use App\Models\User;
use App\Services\Remote\DeviceEnrollmentService;
use App\Services\Remote\ReleaseIntegrityService;
use Database\Seeders\ServiceDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReleaseAndSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private string $tempArtifactPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ServiceDefinitionSeeder::class);

        $dir = storage_path('app/test_m7_releases');
        File::ensureDirectoryExists($dir);
        $this->tempArtifactPath = $dir . '/LoveYou-2.0.0.exe';
        File::put($this->tempArtifactPath, 'SIGNED_LOVEYOU_V2_BINARY_BYTES');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/test_m7_releases'));
        parent::tearDown();
    }

    public function test_authenticated_release_download_integrity_check_and_tamper_detection(): void
    {
        $admin = User::factory()->create();
        $appA = ManagedApp::create(['owner_id' => $admin->id, 'name' => 'App A', 'slug' => 'app-a', 'is_active' => true]);
        $appB = ManagedApp::create(['owner_id' => $admin->id, 'name' => 'App B', 'slug' => 'app-b', 'is_active' => true]);

        $enrSvc = app(DeviceEnrollmentService::class);
        $codeA = $enrSvc->createEnrollmentCode($appA, $admin)->enrollment_code;
        $codeB = $enrSvc->createEnrollmentCode($appB, $admin)->enrollment_code;

        $devA = $enrSvc->enrollDevice(['enrollment_code' => $codeA, 'device_uuid' => (string) Str::uuid(), 'hostname' => 'PC-A']);
        $devB = $enrSvc->enrollDevice(['enrollment_code' => $codeB, 'device_uuid' => (string) Str::uuid(), 'hostname' => 'PC-B']);

        // Publish signed release dari hasil AppBuild
        $template = AppTemplate::create(['name' => 'T', 'slug' => 't-m7', 'version' => '1.0.0', 'source_path' => 'D:/KULIAH/KODINGAN/screenshotusingpy']);
        $project = AppProject::create(['app_template_id' => $template->id, 'name' => 'P', 'slug' => 'p-m7', 'status' => 'built']);
        $build = AppBuild::create([
            'app_project_id' => $project->id,
            'status' => 'succeeded',
            'format' => 'onefile',
            'artifact_path' => $this->tempArtifactPath,
        ]);

        $release = app(ReleaseIntegrityService::class)->publishFromBuild($appA, $build, '2.0.0', $admin, 'Hardened release');
        $this->assertNotEmpty($release->signature);
        $this->assertTrue(app(ReleaseIntegrityService::class)->verifySignature($release->sha256_checksum, $release->signature));

        // 1. Tanpa Bearer token -> 401
        $this->get("/api/v1/agent/releases/{$release->id}/download")->assertStatus(401);

        // 2. Perangkat App B tidak boleh mengunduh release App A -> 403
        $this->withToken($devB['device_token'])
            ->get("/api/v1/agent/releases/{$release->id}/download")
            ->assertStatus(403);

        // 3. Perangkat App A mengunduh sukses -> 200 + header SHA-256 & HMAC signature
        $okResp = $this->withToken($devA['device_token'])
            ->get("/api/v1/agent/releases/{$release->id}/download");
        $okResp->assertOk()
            ->assertHeader('X-Release-Sha256', $release->sha256_checksum)
            ->assertHeader('X-Release-Signature', $release->signature);

        // 4. Jika file pada disk dimodifikasi setelah terbit -> server menolak 409 Conflict
        File::put($this->tempArtifactPath, 'TAMPERED_ON_DISK');
        $this->withToken($devA['device_token'])
            ->get("/api/v1/agent/releases/{$release->id}/download")
            ->assertStatus(409);

        // 5. Path traversal di luar direktori yang diizinkan ditolak 403
        $badRel = AppRelease::create([
            'managed_app_id' => $appA->id,
            'version' => '2.0.1',
            'artifact_path' => base_path('.env'),
            'sha256_checksum' => hash('sha256', 'dummy'),
            'is_active' => false,
        ]);
        $this->withToken($devA['device_token'])
            ->get("/api/v1/agent/releases/{$badRel->id}/download")
            ->assertStatus(403);
    }

    public function test_security_headers_and_hidden_token_hash_hardening(): void
    {
        $admin = User::factory()->create();
        $app = ManagedApp::create(['owner_id' => $admin->id, 'name' => 'Sec App', 'slug' => 'sec-app', 'is_active' => true]);
        $code = app(DeviceEnrollmentService::class)->createEnrollmentCode($app, $admin)->enrollment_code;
        app(DeviceEnrollmentService::class)->enrollDevice([
            'enrollment_code' => $code,
            'device_uuid' => (string) Str::uuid(),
            'hostname' => 'PC-SEC',
        ]);

        $resp = $this->actingAs($admin)->getJson('/api/v1/admin/devices');
        $resp->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // token_hash tidak pernah terekspos ke API JSON
        $this->assertArrayNotHasKey('token_hash', $resp->json('devices.0'));
    }
}
