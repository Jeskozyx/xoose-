<?php

namespace Tests\Feature;

use App\Models\AppProject;
use App\Models\AppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupabaseResourceCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_stats_endpoint_returns_json()
    {
        Http::fake([
            '*/rest/v1/hasil_ss*' => Http::response([], 200, ['Content-Range' => '0-0/15']),
            '*/rest/v1/user_responses*' => Http::response([
                ['id' => 1, 'nama' => 'tester1'],
                ['id' => 2, 'nama' => 'tester2'],
            ], 200, ['Content-Range' => '0-1/2']),
        ]);

        $response = $this->getJson(route('builder.resources.stats'));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'total_screenshots' => 15,
                'total_users' => 2,
                'users' => ['tester1', 'tester2'],
            ],
        ]);
    }

    public function test_cleanup_validation_fails_on_missing_action()
    {
        $response = $this->postJson(route('builder.resources.cleanup'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['action']);
    }

    public function test_cleanup_screenshots_calls_supabase_endpoints()
    {
        Http::fake([
            '*/rest/v1/hasil_ss*' => Http::response([['id' => 1], ['id' => 2]], 200),
            '*/storage/v1/object/list/screenshots' => Http::response([], 200),
        ]);

        $response = $this->postJson(route('builder.resources.cleanup'), [
            'action' => 'screenshots',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }

    public function test_dashboard_folders_calculates_realtime_counts_with_pool()
    {
        Http::fake([
            '*/rest/v1/user_responses*' => Http::response([
                ['id' => 1, 'nama' => 'negro'],
                ['id' => 2, 'nama' => 'jemski'],
            ], 200),
            '*/rest/v1/hasil_ss*user_name=eq.negro*' => Http::response([['id' => 10]], 206, ['Content-Range' => '0-0/9']),
            '*/rest/v1/hasil_ss*user_name=eq.jemski*' => Http::response([['id' => 11]], 206, ['Content-Range' => '0-0/4']),
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('9 items');
        $response->assertSee('negro');
    }
}
