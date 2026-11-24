<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_renders_only_xoose_menus_without_dummy_template_items(): void
    {
        $view = $this->blade('<x-sidebar active-key="dashboard" />');

        // Brand & active Xoose menus
        $view->assertSee('Xoose');
        $view->assertDontSee('Laravel');
        $view->assertSee('Control Plane');
        $view->assertSee('Workspace');
        $view->assertSee('Dashboard');
        $view->assertSee('Folders');
        $view->assertSee('App Builder');
        $view->assertSee('Remote Manager');
        $view->assertSee('Applications');
        $view->assertSee('Devices');
        $view->assertSee('Capture Rules');
        $view->assertSee('Service Config');
        $view->assertSee('Commands');
        $view->assertSee('Logs & Audit');
        $view->assertSee('Releases');
        $view->assertSee('Masuk Admin Control Plane');

        // Removed template dummy items
        $view->assertDontSee('Product');
        $view->assertDontSee('Customers');
        $view->assertDontSee('Shop');
        $view->assertDontSee('Income');
        $view->assertDontSee('Promote');
        $view->assertDontSee('product.overview');
    }

    public function test_sidebar_renders_authenticated_admin_footer_and_active_remote_state(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Xoose',
            'email' => 'admin@xoose.local',
        ]);

        $this->actingAs($admin);

        $view = $this->blade('<x-sidebar active-key="remote.devices" />');

        $view->assertSee('Admin Xoose');
        $view->assertSee('admin@xoose.local');
        $view->assertSee('AX');
        $view->assertSee('data-active-group="true"', false);
        $view->assertSee('aria-current="page"', false);
    }

    public function test_brand_logo_is_rendered_on_sidebar_and_login_page(): void
    {
        $sidebar = $this->blade('<x-sidebar active-key="dashboard" />');
        $sidebar->assertSee('M6.5 6.5L14.5 16L6.5 25.5', false);
        $sidebar->assertDontSee('hexGradTL', false);

        $login = $this->get('/login');
        $login->assertOk();
        $login->assertSee('M6.5 6.5L14.5 16L6.5 25.5', false);
        $login->assertSee('favicon.svg', false);
    }
}
