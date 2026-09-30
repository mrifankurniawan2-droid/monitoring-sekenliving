<?php

namespace Database\Seeders;

use App\Models\Device;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $devices = [
            [
                'ip_address' => '192.168.88.100',
                'mac_address' => '48:8F:5A:A1:B2:C3',
                'hostname' => 'Budi-iPhone-15',
                'comment' => 'Staff Mobile',
                'interface' => 'bridge-lan',
                'status' => 'bound',
                'source' => 'dhcp',
                'is_online' => true,
                'last_seen_at' => now(),
            ],
            [
                'ip_address' => '192.168.88.101',
                'mac_address' => '00:2B:67:88:99:AA',
                'hostname' => 'ThinkPad-X1-Carbon',
                'comment' => 'Manager Workstation',
                'interface' => 'bridge-lan',
                'status' => 'bound',
                'source' => 'dhcp',
                'is_online' => true,
                'last_seen_at' => now(),
            ],
            [
                'ip_address' => '192.168.88.102',
                'mac_address' => 'A4:D1:8C:33:44:55',
                'hostname' => 'LG-Smart-OLED-TV',
                'comment' => 'Meeting Room 1',
                'interface' => 'ether2',
                'status' => 'bound',
                'source' => 'dhcp',
                'is_online' => true,
                'last_seen_at' => now(),
            ],
            [
                'ip_address' => '192.168.88.103',
                'mac_address' => 'F0:72:EA:66:77:88',
                'hostname' => 'Samsung-Galaxy-S24',
                'comment' => 'Guest Device',
                'interface' => 'wlan1',
                'status' => 'bound',
                'source' => 'dhcp',
                'is_online' => true,
                'last_seen_at' => now(),
            ],
            [
                'ip_address' => '192.168.88.200',
                'mac_address' => '3C:EF:8C:11:22:33',
                'hostname' => 'CCTV-Gate-Primary',
                'comment' => 'Static Security Camera',
                'interface' => 'ether3',
                'status' => 'complete',
                'source' => 'arp',
                'is_online' => true,
                'last_seen_at' => now(),
            ],
            [
                'ip_address' => '192.168.88.201',
                'mac_address' => '3C:EF:8C:44:55:66',
                'hostname' => 'CCTV-Backyard',
                'comment' => 'Static Security Camera',
                'interface' => 'ether3',
                'status' => 'complete',
                'source' => 'arp',
                'is_online' => true,
                'last_seen_at' => now(),
            ],
        ];

        foreach ($devices as $d) {
            Device::query()->updateOrCreate(
                ['ip_address' => $d['ip_address']],
                $d
            );
        }
    }
}
