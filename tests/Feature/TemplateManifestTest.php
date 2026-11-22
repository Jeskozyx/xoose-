<?php

namespace Tests\Feature;

use App\Services\TemplateManifestService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TemplateManifestTest extends TestCase
{
    protected TemplateManifestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TemplateManifestService();
    }

    public function test_service_reads_template_manifest(): void
    {
        $manifest = $this->service->getTemplateManifest();

        $this->assertIsArray($manifest);
        $this->assertEquals('loveyou', $manifest['template']['id']);
        $this->assertNotEmpty($manifest['configuration_schema']);
        $this->assertNotEmpty($manifest['features_schema']);
    }

    public function test_service_returns_form_schema(): void
    {
        $schema = $this->service->getFormSchema();

        $this->assertArrayHasKey('features', $schema);
        $this->assertArrayHasKey('configuration', $schema);
        $this->assertArrayHasKey('defaults', $schema);
    }

    public function test_validation_rejects_invalid_slug(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->validateInput([
            'name' => 'Invalid Slug App',
            'slug' => 'Bad_Slug_Uppercase',
        ]);
    }

    public function test_validation_rejects_invalid_image_format(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->validateInput([
            'name' => 'Bad Format App',
            'slug' => 'bad-format-app',
            'configuration' => [
                'image_format' => 'bmp', // Only jpg and png allowed
            ],
        ]);
    }

    public function test_validation_and_manifest_generation_succeeds(): void
    {
        $input = [
            'name' => 'Aplikasi Romantis',
            'slug' => 'aplikasi-romantis',
            'features' => [
                'auto_start' => true,
            ],
            'configuration' => [
                'supabase_url' => 'https://example.supabase.co',
                'supabase_key' => 'sb_anon_test_key',
                'table_name' => 'hasil_ss',
                'bucket_name' => 'screenshots',
                'interval_seconds' => 5,
                'image_format' => 'png',
                'jpg_quality' => 90,
            ],
        ];

        $validated = $this->service->validateInput($input);
        $manifest = $this->service->generateProjectManifest(
            $validated['name'],
            $validated['slug'],
            $validated['features'] ?? [],
            $validated['configuration'] ?? []
        );

        $this->assertEquals('Aplikasi Romantis', $manifest['application']['name']);
        $this->assertEquals('aplikasi-romantis', $manifest['application']['slug']);
        $this->assertTrue($manifest['features']['auto_start']);
        $this->assertEquals('png', $manifest['configuration']['image_format']);
        $this->assertEquals(5, $manifest['configuration']['interval_seconds']);
    }
}
