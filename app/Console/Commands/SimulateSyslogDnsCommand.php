<?php

namespace App\Console\Commands;

use App\Services\DnsLogParser;
use Illuminate\Console\Command;

class SimulateSyslogDnsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'syslog:simulate 
                            {ip=192.168.88.105 : Client IP address}
                            {domain=www.youtube.com : Domain queried}
                            {--udp : Send as actual UDP 514 packet instead of direct database recording}
                            {--port=514 : UDP Port to send to if using --udp}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate a MikroTik DNS query log entry for testing';

    /**
     * Execute the console command.
     */
    public function handle(DnsLogParser $parser): int
    {
        $ip = (string) $this->argument('ip');
        $domain = (string) $this->argument('domain');
        $sendUdp = (bool) $this->option('udp');
        $port = (int) $this->option('port');

        // Standard MikroTik DNS log line
        $rawMessage = sprintf(
            '<13>%s mikrotik dns,packet: query from %s:53421: #%d A %s',
            now()->format('M/d H:i:s'),
            $ip,
            rand(1000, 9999),
            $domain
        );

        if ($sendUdp) {
            $this->info("Sending UDP packet to 127.0.0.1:{$port}...");
            $sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($sock) {
                @socket_sendto($sock, $rawMessage, strlen($rawMessage), 0, '127.0.0.1', $port);
                @socket_close($sock);
                $this->info("UDP packet sent: {$rawMessage}");

                return self::SUCCESS;
            } else {
                $this->error('Failed to create UDP socket to send packet.');

                return self::FAILURE;
            }
        }

        $this->info('Parsing and recording DNS log directly...');
        $parsed = $parser->parse($rawMessage);
        $this->line("Raw: <fg=gray>{$rawMessage}</>");
        $this->line('Parsed: IP='.($parsed['ip'] ?? 'null').', Domain='.($parsed['domain'] ?? 'null').', App='.($parsed['app'] ?? 'Unmatched'));

        $log = $parser->recordActivity($parsed, true);
        if ($log) {
            $this->info("Successfully recorded activity for [{$log->app_name}] by IP {$log->ip_address}!");
        } else {
            $this->warn('No activity recorded (unmatched or invalid IP).');
        }

        return self::SUCCESS;
    }
}
