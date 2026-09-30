<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Device;
use App\Models\NmsSetting;
use App\Models\Router;
use Illuminate\Database\Seeder;

class RouterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $host = NmsSetting::get('mikrotik_host', env('MIKROTIK_HOST', '192.168.10.1'));
        $user = NmsSetting::get('mikrotik_user', env('MIKROTIK_USER', 'seken'));
        $pass = NmsSetting::get('mikrotik_pass', env('MIKROTIK_PASS', ''));
        $port = (int) NmsSetting::get('mikrotik_port', env('MIKROTIK_PORT', 8728));
        $apiType = NmsSetting::get('mikrotik_api_type', env('MIKROTIK_API_TYPE', 'routeros_api'));
        $useSsl = NmsSetting::get('mikrotik_use_ssl', '0') === '1';

        $router = Router::query()->firstOrCreate(
            ['ip_address' => $host],
            [
                'name' => 'MikroTik Utama',
                'port' => $port,
                'username' => $user,
                'password' => $pass,
                'api_type' => $apiType,
                'use_ssl' => $useSsl,
                'is_active' => true,
            ]
        );

        Device::query()->whereNull('router_id')->update([
            'router_id' => $router->id,
            'router_name' => $router->name,
        ]);

        ActivityLog::query()->whereNull('router_id')->update([
            'router_id' => $router->id,
            'router_name' => $router->name,
        ]);
    }
}
