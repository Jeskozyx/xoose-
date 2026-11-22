<?php

namespace Tests\Feature;

use Tests\TestCase;

class DashboardFolderComponentTest extends TestCase
{
    /**
     * Test bahwa halaman dashboard tidak menampilkan data dummy saat database kosong.
     */
    public function test_dashboard_renders_no_dummy_data_when_empty(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
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
}
