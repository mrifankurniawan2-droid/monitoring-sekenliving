<?php

namespace App\Console\Commands;

use App\Services\MikroTikService;
use Illuminate\Console\Command;
use Throwable;

class SyncMikrotikDevicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mikrotik:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch connected devices from MikroTik /ip/dhcp-server/lease and /ip/arp';

    /**
     * Execute the console command.
     */
    public function handle(MikroTikService $mikrotik): int
    {
        $this->info('Connecting to MikroTik to fetch connected devices...');

        try {
            $result = $mikrotik->syncDevices();

            $this->info('Sync completed successfully!');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Synced Devices', $result['synced']],
                    ['DHCP Leases Found', $result['leases']],
                    ['ARP Entries Found', $result['arps']],
                ]
            );

            if (! empty($result['errors'])) {
                $this->warn('Warnings / Errors encountered:');
                foreach ($result['errors'] as $err) {
                    $this->error(' - '.$err);
                }
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Failed to sync devices: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
