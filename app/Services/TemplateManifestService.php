<?php

namespace App\Services;

use App\Models\AppTemplate;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class TemplateManifestService
{
    /**
     * Path to template directory from config.
     */
    protected string $templatePath;

    public function __construct(?string $templatePath = null)
    {
        $this->templatePath = $templatePath ?: config('application-builder.paths.template', 'D:/KULIAH/KODINGAN/screenshotusingpy');
    }

    /**
     * Read and decode the template manifest.
     */
    public function getTemplateManifest(): array
    {
        $manifestPath = rtrim($this->templatePath, '/\\') . '/template-manifest.json';

        if (!File::exists($manifestPath)) {
            // Fallback to engine master copy if not in template root
            $engineManifest = config('application-builder.paths.engine', 'D:/KULIAH/KODINGAN/xoose-loveyou/builder') . '/manifests/loveyou.json';
            if (File::exists($engineManifest)) {
                $manifestPath = $engineManifest;
            } else {
                throw new InvalidArgumentException("Template manifest tidak ditemukan di: {$manifestPath}");
            }
        }

        $content = File::get($manifestPath);
        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            throw new InvalidArgumentException("Manifest template bukan format JSON yang valid.");
        }

        return $decoded;
    }

    /**
     * Form schema for dashboard UI form builder.
     */
    public function getFormSchema(): array
    {
        $manifest = $this->getTemplateManifest();

        return [
            'template' => $manifest['template'] ?? [],
            'features' => $manifest['features_schema'] ?? [],
            'configuration' => $manifest['configuration_schema'] ?? [],
            'editable_files' => $manifest['editable_files'] ?? [],
            'defaults' => $this->getDefaultValues($manifest),
        ];
    }

    /**
     * Extract default values for all features and configuration fields.
     */
    public function getDefaultValues(?array $manifest = null): array
    {
        $manifest = $manifest ?: $this->getTemplateManifest();
        $defaults = [
            'features' => [],
            'configuration' => [],
        ];

        foreach ($manifest['features_schema'] ?? [] as $feat) {
            $defaults['features'][$feat['key']] = $feat['default'] ?? false;
        }

        foreach ($manifest['configuration_schema'] ?? [] as $conf) {
            $defaults['configuration'][$conf['key']] = $conf['default'] ?? null;
        }

        return $defaults;
    }

    /**
     * Validate project configuration input according to manifest schema.
     */
    public function validateInput(array $data): array
    {
        $manifest = $this->getTemplateManifest();
        $rules = [
            'name' => 'required|string|min:3|max:100',
            'slug' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
            'features' => 'nullable|array',
            'configuration' => 'nullable|array',
            'configuration.icon' => 'nullable|string',
        ];

        // Dynamic rules from configuration_schema
        foreach ($manifest['configuration_schema'] ?? [] as $field) {
            $key = $field['key'];
            $fieldRules = [];

            if (!empty($field['required'])) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            switch ($field['type'] ?? 'string') {
                case 'integer':
                    $fieldRules[] = 'integer';
                    if (isset($field['min'])) {
                        $fieldRules[] = 'min:' . $field['min'];
                    }
                    if (isset($field['max'])) {
                        $fieldRules[] = 'max:' . $field['max'];
                    }
                    break;

                case 'select':
                    if (!empty($field['options'])) {
                        $fieldRules[] = 'in:' . implode(',', $field['options']);
                    }
                    break;

                case 'boolean':
                    $fieldRules[] = 'boolean';
                    break;

                default:
                    $fieldRules[] = 'string';
                    break;
            }

            $rules["configuration.{$key}"] = $fieldRules;
        }

        $validator = validator($data, $rules, [
            'slug.regex' => 'Slug hanya boleh memuat huruf kecil, angka, dan tanda hubung tunggal (contoh: my-app-1).',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Generate complete project build manifest compatible with Python Engine.
     */
    public function generateProjectManifest(string $name, string $slug, array $features = [], array $configuration = [], string $icon = 'heart.ico'): array
    {
        $template = $this->getTemplateManifest();
        $defaults = $this->getDefaultValues($template);

        // Merge features and configuration with defaults
        $finalFeatures = array_merge($defaults['features'], $features);
        $finalConfig = array_merge($defaults['configuration'], $configuration);

        return [
            'template' => [
                'id' => $template['template']['id'] ?? 'loveyou',
                'name' => $template['template']['name'] ?? 'LoveYou',
                'version' => $template['template']['version'] ?? '1.0.0',
                'source_path' => str_replace('\\', '/', $this->templatePath),
            ],
            'application' => [
                'name' => $name,
                'slug' => $slug,
                'version' => '1.0.0',
            ],
            'build' => [
                'platform' => $template['build']['platform'] ?? 'windows',
                'format' => $template['build']['format'] ?? 'onedir',
                'entry_point' => $template['build']['entry_point'] ?? 'backend/screenshot_service.py',
                'icon' => $icon ?: 'heart.ico',
            ],
            'features' => $finalFeatures,
            'configuration' => $finalConfig,
            'editable_files' => $template['editable_files'] ?? [],
        ];
    }

    /**
     * Ensure the default LoveYou template is registered in the database.
     */
    public function ensureDefaultTemplateRegistered(): AppTemplate
    {
        $manifest = $this->getTemplateManifest();
        $templateData = $manifest['template'] ?? [];

        return AppTemplate::firstOrCreate(
            ['slug' => $templateData['id'] ?? 'loveyou'],
            [
                'name' => $templateData['name'] ?? 'LoveYou',
                'version' => $templateData['version'] ?? '1.0.0',
                'source_path' => str_replace('\\', '/', $this->templatePath),
                'manifest_path' => str_replace('\\', '/', $this->templatePath) . '/template-manifest.json',
                'description' => $templateData['description'] ?? null,
                'is_active' => true,
            ]
        );
    }
}
