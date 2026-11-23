<?php

namespace Database\Seeders;

use App\Models\ServiceDefinition;
use Illuminate\Database\Seeder;

class ServiceDefinitionSeeder extends Seeder
{
    /**
     * Menyelaraskan 7 layanan modular standar LoveYou ke tabel service_definitions.
     */
    public static function defaultDefinitions(): array
    {
        return [
            [
                'service_key' => 'screenshot_service',
                'display_name' => 'Screenshot Service',
                'description' => 'Layanan modular penangkap layar periodik berbasis MSS + Pillow.',
                'supported_actions' => ['start', 'stop', 'pause', 'resume', 'restart'],
                'permission_requirements' => ['screen_capture', 'local_pause_stop_control'],
                'configuration_schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'interval_seconds' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 3600, 'default' => 3],
                        'image_format' => ['type' => 'string', 'enum' => ['webp', 'jpg', 'jpeg', 'png'], 'default' => 'webp'],
                        'jpg_quality' => ['type' => 'integer', 'minimum' => 10, 'maximum' => 100, 'default' => 75],
                        'keep_local_file' => ['type' => 'boolean', 'default' => false],
                        'local_folder' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 260, 'default' => 'screenshots'],
                    ],
                ],
                'is_system' => true,
            ],
            [
                'service_key' => 'tray_service',
                'display_name' => 'Tray Service',
                'description' => 'Layanan pengelola ikon System Tray minimalis Windows (Pystray).',
                'supported_actions' => ['start', 'stop', 'restart'],
                'permission_requirements' => ['system_tray'],
                'configuration_schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'icon' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120, 'default' => 'heart.ico'],
                        'app_name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100, 'default' => 'LoveYou'],
                        'minimalist_tooltip' => ['type' => 'boolean', 'default' => true],
                    ],
                ],
                'is_system' => true,
            ],
            [
                'service_key' => 'sync_service',
                'display_name' => 'Sync Service',
                'description' => 'Layanan sinkronisasi & unggah antrean screenshot ke Supabase dengan retry jaringan.',
                'supported_actions' => ['start', 'stop', 'pause', 'resume', 'restart'],
                'permission_requirements' => ['network_outbound'],
                'configuration_schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'supabase_url' => ['type' => 'string', 'maxLength' => 500, 'default' => ''],
                        'table_name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100, 'default' => 'hasil_ss'],
                        'bucket_name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100, 'default' => 'screenshots'],
                        'max_queue_size' => ['type' => 'integer', 'minimum' => 10, 'maximum' => 2000, 'default' => 200],
                        'max_upload_retries' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20, 'default' => 6],
                        'retry_backoff_seconds' => ['type' => 'number', 'minimum' => 0.5, 'maximum' => 60.0, 'default' => 4.0],
                    ],
                ],
                'is_system' => true,
            ],
            [
                'service_key' => 'notification_service',
                'display_name' => 'Notification Service',
                'description' => 'Layanan notifikasi status & perubahan konfigurasi lokal Windows.',
                'supported_actions' => ['start', 'stop'],
                'permission_requirements' => ['windows_notifications'],
                'configuration_schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'enabled' => ['type' => 'boolean', 'default' => false],
                        'notify_on_capture' => ['type' => 'boolean', 'default' => false],
                        'notify_on_config_change' => ['type' => 'boolean', 'default' => false],
                        'notify_on_command' => ['type' => 'boolean', 'default' => false],
                    ],
                ],
                'is_system' => true,
            ],
            [
                'service_key' => 'ui_service',
                'display_name' => 'UI Service',
                'description' => 'Layanan antarmuka PyWebView lokal dengan pembaruan tanggal undangan & tema runtime.',
                'supported_actions' => ['start', 'stop', 'restart'],
                'permission_requirements' => ['desktop_ui'],
                'configuration_schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'window_title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120, 'default' => '❤️ LoveYou'],
                        'date_invitation_text' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200, 'default' => 'SABTU, 5 DESEMBER 2026'],
                        'date_countdown_target' => ['type' => 'string', 'minLength' => 4, 'maxLength' => 64, 'default' => '2026-12-05T12:00:00Z'],
                        'tanggal_date' => ['type' => 'string', 'minLength' => 8, 'maxLength' => 32, 'default' => '2026-12-05'],
                        'width' => ['type' => 'integer', 'minimum' => 320, 'maximum' => 1920, 'default' => 560],
                        'height' => ['type' => 'integer', 'minimum' => 400, 'maximum' => 1200, 'default' => 760],
                        'theme' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 64, 'default' => 'romantic-dark'],
                    ],
                ],
                'is_system' => true,
            ],
            [
                'service_key' => 'game_service',
                'display_name' => 'Game Service',
                'description' => 'Fondasi modular untuk ekstensi modul pengembangan game yang dikontrol remote.',
                'supported_actions' => ['start', 'stop', 'pause', 'resume', 'restart'],
                'permission_requirements' => ['game_runtime'],
                'configuration_schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'enabled' => ['type' => 'boolean', 'default' => false],
                        'game_mode' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 64, 'default' => 'idle'],
                        'tick_rate_hz' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 60, 'default' => 1],
                        'auto_save_interval_seconds' => ['type' => 'integer', 'minimum' => 5, 'maximum' => 3600, 'default' => 60],
                        'custom_params' => ['type' => 'object', 'default' => []],
                    ],
                ],
                'is_system' => true,
            ],
            [
                'service_key' => 'custom_service',
                'display_name' => 'Custom Service',
                'description' => 'Template plugin layanan kustom yang mengikuti kontrak BaseService.',
                'supported_actions' => ['start', 'stop', 'pause', 'resume', 'restart'],
                'permission_requirements' => [],
                'configuration_schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'enabled' => ['type' => 'boolean', 'default' => false],
                        'plugin_name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100, 'default' => 'default_plugin'],
                        'parameters' => ['type' => 'object', 'default' => []],
                    ],
                ],
                'is_system' => true,
            ],
            [
                'service_key' => 'browser_capture_service',
                'display_name' => 'Browser Capture Service',
                'description' => 'Layanan modular Specific Screen & Live Target Capture (Browser Domain, Application Window, atau Desktop MSS).',
                'supported_actions' => ['start', 'stop', 'pause', 'resume', 'restart'],
                'permission_requirements' => ['browser_extension', 'native_messaging', 'local_pause_stop_control'],
                'configuration_schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'capture_policy' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'active_source' => [
                                    'type' => 'string',
                                    'enum' => ['disabled', 'browser_domain', 'application_window', 'desktop_mss'],
                                    'default' => 'desktop_mss',
                                ],
                                'allow_live_switch' => ['type' => 'boolean', 'default' => true],
                            ],
                        ],
                        'browser_capture' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'enabled' => ['type' => 'boolean', 'default' => false],
                                'capture_mode' => ['type' => 'string', 'enum' => ['active_tab'], 'default' => 'active_tab'],
                                'permission_mode' => ['type' => 'string', 'enum' => ['local_start', 'automatic_domain'], 'default' => 'local_start'],
                                'local_session_required' => ['type' => 'boolean', 'default' => true],
                                'pause_when_browser_unfocused' => ['type' => 'boolean', 'default' => true],
                                'pause_when_tab_inactive' => ['type' => 'boolean', 'default' => true],
                                'image_format' => ['type' => 'string', 'enum' => ['webp', 'png'], 'default' => 'webp'],
                                'fallback_format' => ['type' => 'string', 'enum' => ['png', 'jpeg'], 'default' => 'png'],
                                'capture_interval_seconds' => ['type' => 'integer', 'minimum' => 2, 'maximum' => 3600, 'default' => 10],
                                'rules' => [
                                    'type' => 'array',
                                    'maxItems' => 50,
                                    'default' => [],
                                    'items' => [
                                        'type' => 'object',
                                        'additionalProperties' => false,
                                        'properties' => [
                                            'id' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 64, 'pattern' => '/^[a-z0-9][a-z0-9_-]{1,63}$/'],
                                            'enabled' => ['type' => 'boolean', 'default' => true],
                                            'domain' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 253, 'pattern' => '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/'],
                                            'include_subdomains' => ['type' => 'boolean', 'default' => true],
                                            'schemes' => [
                                                'type' => 'array',
                                                'minItems' => 1,
                                                'maxItems' => 2,
                                                'items' => ['type' => 'string', 'enum' => ['https', 'http']],
                                            ],
                                            'browsers' => [
                                                'type' => 'array',
                                                'minItems' => 1,
                                                'maxItems' => 3,
                                                'items' => ['type' => 'string', 'enum' => ['chrome', 'edge', 'firefox']],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'window_capture' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'enabled' => ['type' => 'boolean', 'default' => false],
                                'capture_mode' => ['type' => 'string', 'enum' => ['active_window'], 'default' => 'active_window'],
                                'capture_interval_seconds' => ['type' => 'integer', 'minimum' => 2, 'maximum' => 3600, 'default' => 10],
                                'rules' => [
                                    'type' => 'array',
                                    'maxItems' => 50,
                                    'default' => [],
                                    'items' => [
                                        'type' => 'object',
                                        'additionalProperties' => false,
                                        'properties' => [
                                            'id' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 64, 'pattern' => '/^[a-z0-9][a-z0-9_-]{1,63}$/'],
                                            'enabled' => ['type' => 'boolean', 'default' => false],
                                            'process' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120, 'pattern' => '/^[a-zA-Z0-9._-]{1,120}$/'],
                                            'window_title_contains' => ['type' => 'string', 'maxLength' => 200, 'default' => ''],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'is_system' => true,
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::defaultDefinitions() as $def) {
            ServiceDefinition::updateOrCreate(
                ['service_key' => $def['service_key']],
                $def
            );
        }
    }
}
