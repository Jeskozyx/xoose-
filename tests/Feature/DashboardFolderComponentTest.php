<?php

namespace Tests\Feature;

use Tests\TestCase;

class DashboardFolderComponentTest extends TestCase
{
    /**
     * Test bahwa halaman dashboard tidak menampilkan data dummy atau elemen template palsu.
     */
    public function test_dashboard_renders_no_dummy_data_when_empty(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Overview');
        $response->assertSee('User Folders');
        $response->assertSee('App Builder');
        $response->assertSee('Remote Control');
        $response->assertDontSee('My Workflows');
        $response->assertDontSee('Credits remaining');
        $response->assertDontSee('Task Automation');
        $response->assertDontSee('Notes & Highlights');
        $response->assertDontSee('Billing & Invoicing');
        $response->assertDontSee('Email Responder');
        $response->assertDontSee('Mental Health Tracker');
    }

    /**
     * Test bahwa komponen x-folder-card merender struktur folder dengan benar.
     */
    public function test_folder_card_component_renders_correctly(): void
    {
        $folder = [
            'id'      => 'folder-1',
            'title'   => 'Roedi',
            'items'   => 5,
            'theme'   => 'peach',
            'avatars' => ['alex', 'marcus'],
            'extra'   => 2,
        ];

        $view = $this->blade('<x-folder-card :folder="$folder" />', ['folder' => $folder]);

        $view->assertSee('Roedi');
        $view->assertSee('5 items');
        $view->assertSee('data-user="roedi"', false);
        $view->assertSee('data-title="roedi"', false);
        $view->assertSee('+2');
    }

    /**
     * Test bahwa halaman folder menampilkan panel detail device name, status, dan resolution.
     */
    public function test_folders_page_renders_detail_panel(): void
    {
        $response = $this->get('/folders');

        $response->assertStatus(200);
        $response->assertSee('detail-user-name', false);
        $response->assertSee('detail-device-name', false);
        $response->assertSee('detail-resolution', false);
        $response->assertSee('detail-ss-status', false);
        $response->assertSee('Device Name');
        $response->assertSee('Resolution');
        $response->assertSee('Status');
    }

    /**
     * Test bahwa halaman folders menampilkan timestamp dengan jam, menit, dan detik (WIB).
     */
    public function test_folders_page_displays_seconds_in_screenshot_timestamp(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            '*/user_responses*' => \Illuminate\Support\Facades\Http::response([
                ['id' => 1, 'nama' => 'Roedi', 'status' => 'yes', 'tanggal_date' => '2026-11-24'],
            ], 200),
            '*/hasil_ss*' => \Illuminate\Support\Facades\Http::response([
                [
                    'id' => 10,
                    'user_name' => 'Roedi',
                    'filename' => 'cap_10.webp',
                    'active_window' => 'Chrome',
                    'resolution' => '1920x1080',
                    'file_size_kb' => 24.5,
                    'status' => 'success',
                    'image_url' => 'https://example.com/cap_10.webp',
                    'created_at' => '2026-11-24T16:27:49Z',
                ],
            ], 200),
        ]);

        $response = $this->get('/folders?folder=Roedi');

        $response->assertStatus(200);
        // 16:27:49 UTC == 23:27:49 WIB
        $response->assertSee('23:27:49 WIB');
        $response->assertSee('${hour}:${minute}:${second} WIB', false);
    }
}
