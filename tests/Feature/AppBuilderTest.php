<?php

namespace Tests\Feature;

use App\Models\AppBuild;
use App\Models\AppProject;
use App\Models\AppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AppBuilderTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_projects_index_page_loads_successfully(): void
    {
        $response = $this->get(route('builder.projects.index'));
        $response->assertStatus(200);
        $response->assertSee('Application Builder');
    }

    public function test_project_create_page_loads(): void
    {
        $response = $this->get(route('builder.projects.create'));
        $response->assertStatus(200);
        $response->assertSee('Konfigurasi Proyek Aplikasi');
    }

    public function test_project_creation_and_workspace_initialization(): void
    {
        $slug = 'test-suite-app-' . time();
        $payload = [
            'name' => 'Test Suite App',
            'slug' => $slug,
            'features' => [
                'auto_start' => true,
                'tray_notifications' => false,
            ],
            'configuration' => [
                'interval_seconds' => 4,
                'image_format' => 'jpg',
                'jpg_quality' => 85,
            ],
        ];

        $response = $this->post(route('builder.projects.store'), $payload);

        $project = AppProject::where('slug', $slug)->first();
        $this->assertNotNull($project, 'Project record should exist in database.');
        $response->assertRedirect(route('builder.projects.show', $project));

        // Assert Revision 1 created
        $this->assertEquals(1, $project->revisions()->count());
        $this->assertEquals(1, $project->revisions->first()->revision_number);

        // Assert Workspace exists
        $this->assertNotNull($project->workspace_path);
        $this->assertTrue(File::exists($project->workspace_path));
        $this->assertTrue(File::exists($project->workspace_path . '/backend/config.ini'));

        // Assert config was applied
        $configContent = File::get($project->workspace_path . '/backend/config.ini');
        $this->assertStringContainsString('interval_seconds = 4', $configContent);
        $this->assertStringContainsString('auto_start = true', $configContent);

        // Clean up created workspace
        if (File::exists($project->workspace_path)) {
            File::deleteDirectory($project->workspace_path);
        }
    }

    public function test_editor_security_rejects_unauthorized_files(): void
    {
        $template = AppTemplate::firstOrCreate(['slug' => 'loveyou'], [
            'name' => 'LoveYou',
            'version' => '1.0.0',
            'source_path' => config('application-builder.paths.template'),
        ]);

        $project = AppProject::create([
            'app_template_id' => $template->id,
            'name' => 'Security Test App',
            'slug' => 'security-test-app',
            'workspace_path' => config('application-builder.paths.workspace') . '/security-test-app',
        ]);

        // 1. Rejects sensitive / arbitrary file access
        $response = $this->get(route('builder.editor.show', [$project, 'file' => '.env']));
        $response->assertStatus(403);

        $response2 = $this->get(route('builder.editor.show', [$project, 'file' => '../backend/screenshot_service.py']));
        $response2->assertStatus(403);
    }

    public function test_build_status_api_returns_json(): void
    {
        $template = AppTemplate::firstOrCreate(['slug' => 'loveyou'], [
            'name' => 'LoveYou',
            'version' => '1.0.0',
            'source_path' => config('application-builder.paths.template'),
        ]);

        $project = AppProject::create([
            'app_template_id' => $template->id,
            'name' => 'Build Status Test',
            'slug' => 'build-status-test',
        ]);

        $build = AppBuild::create([
            'app_project_id' => $project->id,
            'status' => 'queued',
            'format' => 'onedir',
        ]);

        $response = $this->get(route('builder.builds.status', $build));
        $response->assertStatus(200);
        $response->assertJson([
            'id' => $build->id,
            'status' => 'queued',
            'format' => 'onedir',
        ]);
    }

    public function test_project_creation_with_icon_preset(): void
    {
        $slug = 'test-icon-app-' . time();
        $payload = [
            'name' => 'Icon Test App',
            'slug' => $slug,
            'icon_preset' => 'chrome',
            'features' => [],
            'configuration' => [
                'interval_seconds' => 5,
            ],
        ];

        $response = $this->post(route('builder.projects.store'), $payload);
        $project = AppProject::where('slug', $slug)->first();
        $this->assertNotNull($project);
        $this->assertEquals('chrome.ico', $project->configuration['icon'] ?? null);

        // Clean up
        if ($project->workspace_path && File::exists($project->workspace_path)) {
            File::deleteDirectory($project->workspace_path);
        }
    }

    public function test_download_rejects_incomplete_or_invalid_build(): void
    {
        $template = AppTemplate::firstOrCreate(['slug' => 'loveyou'], [
            'name' => 'LoveYou',
            'version' => '1.0.0',
            'source_path' => config('application-builder.paths.template'),
        ]);

        $project = AppProject::create([
            'app_template_id' => $template->id,
            'name' => 'Download Test App',
            'slug' => 'download-test-app',
        ]);

        $build = AppBuild::create([
            'app_project_id' => $project->id,
            'status' => 'building', // Not yet succeeded
        ]);

        $response = $this->get(route('builder.builds.download', $build));
        $response->assertStatus(404);
    }

    public function test_unchecked_features_are_saved_as_false_and_reflected_in_config_ini(): void
    {
        $slug = 'test-unchecked-feat-' . time();
        $payload = [
            'name' => 'Unchecked Features Test',
            'slug' => $slug,
            'features' => [
                'auto_start' => '1',
                // keep_local_file is unchecked, so not present in form POST
            ],
            'configuration' => [
                'interval_seconds' => 5,
            ],
        ];

        $response = $this->post(route('builder.projects.store'), $payload);
        $project = AppProject::where('slug', $slug)->first();
        $this->assertNotNull($project);
        $this->assertFalse($project->features['keep_local_file']);

        $configContent = File::get($project->workspace_path . '/backend/config.ini');
        $this->assertStringContainsString('keep_local_file = false', $configContent);

        // Clean up
        if ($project->workspace_path && File::exists($project->workspace_path)) {
            File::deleteDirectory($project->workspace_path);
        }
    }

    public function test_duplicate_slug_returns_validation_error_and_create_suggests_unique_slug(): void
    {
        $template = AppTemplate::firstOrCreate(['slug' => 'loveyou'], [
            'name' => 'LoveYou',
            'version' => '1.0.0',
            'source_path' => config('application-builder.paths.template'),
        ]);

        AppProject::create([
            'app_template_id' => $template->id,
            'name' => 'LoveYou Sweetheart',
            'slug' => 'loveyou-app',
        ]);

        // Create page should automatically suggest 'loveyou-app-2'
        $createResponse = $this->get(route('builder.projects.create'));
        $createResponse->assertStatus(200);
        $createResponse->assertSee('value="loveyou-app-2"', false);

        // Submitting duplicate slug should return validation error instead of 500
        $storeResponse = $this->from(route('builder.projects.create'))
            ->post(route('builder.projects.store'), [
                'name' => 'LoveYou Sweetheart',
                'slug' => 'loveyou-app',
                'configuration' => [
                    'interval_seconds' => 5,
                ],
            ]);

        $storeResponse->assertRedirect(route('builder.projects.create'));
        $storeResponse->assertSessionHasErrors('slug');
        $this->assertEquals(1, AppProject::where('slug', 'loveyou-app')->count());
    }
}
