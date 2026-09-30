<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AppSignature;
use App\Models\Device;
use App\Models\Router;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_page_loads_successfully(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('MikroTik Sentinel NMS');
    }

    public function test_api_stats_returns_json_structure(): void
    {
        Device::create([
            'ip_address' => '192.168.88.55',
            'mac_address' => '48:8F:5A:11:22:33',
            'hostname' => 'MacBook-Pro',
            'is_online' => true,
        ]);

        ActivityLog::create([
            'ip_address' => '192.168.88.55',
            'app_name' => 'YouTube',
            'domain' => 'www.youtube.com',
            'logged_at' => now(),
        ]);

        $response = $this->getJson('/api/stats');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_devices',
                'online_devices',
                'activities_today',
                'unique_apps_today',
                'top_apps',
                'daemon',
            ]);

        $response->assertJson([
            'total_devices' => 1,
            'online_devices' => 1,
            'activities_today' => 1,
        ]);
    }

    public function test_api_devices_listing_and_filtering(): void
    {
        Device::create([
            'ip_address' => '192.168.88.20',
            'mac_address' => '00:11:22:33:44:55',
            'hostname' => 'Camera-Front',
            'is_online' => true,
        ]);

        Device::create([
            'ip_address' => '192.168.88.21',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'hostname' => 'Office-Printer',
            'is_online' => false,
        ]);

        $response = $this->getJson('/api/devices?search=Camera');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('192.168.88.20', $response->json('data.0.ip_address'));
    }

    public function test_api_simulate_dns_query(): void
    {
        AppSignature::create([
            'app_name' => 'TikTok',
            'category' => 'Social Video',
            'domain_pattern' => 'tiktok.com',
            'badge_color' => '#06b6d4',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/simulate', [
            'ip' => '192.168.88.77',
            'domain' => 'v16.tiktok.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('activity_logs', [
            'ip_address' => '192.168.88.77',
            'app_name' => 'TikTok',
            'domain' => 'v16.tiktok.com',
        ]);
    }

    public function test_router_crud_and_password_encryption(): void
    {
        // 1. Create Router
        $response = $this->postJson('/api/routers', [
            'name' => 'MikroTik Cabang',
            'ip_address' => '192.168.20.1',
            'port' => 8728,
            'username' => 'admin_cabang',
            'password' => 'SuperSecret123!',
            'api_type' => 'routeros_api',
            'use_ssl' => false,
            'is_active' => true,
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        // Verify router stored and password is encrypted in database
        $router = Router::where('ip_address', '192.168.20.1')->first();
        $this->assertNotNull($router);
        $this->assertNotEquals('SuperSecret123!', $router->getRawOriginal('password'));
        $this->assertEquals('SuperSecret123!', $router->getDecryptedPassword());

        // 2. List Routers
        $listRes = $this->getJson('/api/routers');
        $listRes->assertStatus(200)->assertJsonStructure(['data']);
        $this->assertTrue(collect($listRes->json('data'))->contains('name', 'MikroTik Cabang'));

        // 3. Delete Router
        $delRes = $this->deleteJson("/api/routers/{$router->id}");
        $delRes->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseMissing('routers', ['id' => $router->id]);
    }
}
