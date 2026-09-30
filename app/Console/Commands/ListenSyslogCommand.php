<?php

namespace App\Console\Commands;

use App\Models\NmsSetting;
use App\Services\DnsLogParser;
use Illuminate\Console\Command;
use Throwable;

class ListenSyslogCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'syslog:listen 
                            {--host=0.0.0.0 : Host IP address to bind UDP socket}
                            {--port=514 : UDP Port for Syslog (default 514)}
                            {--all : Log all domain queries, not only matched apps}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen for incoming MikroTik Remote DNS Syslog UDP packets, parse queries, and log app activity';

    /**
     * Execute the console command.
     */
    public function handle(DnsLogParser $parser): int
    {
        $host = (string) $this->option('host');
        $port = (int) $this->option('port');
        $logAll = (bool) $this->option('all');

        $this->info('=================================================');
        $this->info(' MikroTik Remote DNS Syslog Daemon (Production Safe)');
        $this->info('=================================================');
        $this->line("Listening on UDP: <fg=cyan>{$host}:{$port}</>");
        $this->line('Matching Mode: '.($logAll ? '<fg=yellow>All DNS Queries</>' : '<fg=green>Configured App Signatures Only</>'));
        $this->line("Press <fg=red>Ctrl+C</> to stop.\n");

        if (! extension_loaded('sockets')) {
            $this->error('PHP sockets extension is required to run UDP Syslog listener.');

            return self::FAILURE;
        }

        $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($socket === false) {
            $errCode = socket_last_error();
            $this->error('Failed to create UDP socket: '.socket_strerror($errCode));

            return self::FAILURE;
        }

        // Allow address reuse
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

        // Bind socket to port
        if (! @socket_bind($socket, $host, $port)) {
            $errCode = socket_last_error($socket);
            $errStr = socket_strerror($errCode);
            $this->error("Failed to bind UDP socket to {$host}:{$port} - {$errStr}");
            $this->warn('Note: Port 514 on Windows or Linux may require elevated/Administrator privileges, or may be occupied.');
            $this->warn('Tip: You can specify another port (e.g. --port=1514) and configure MikroTik remote-port=1514.');
            socket_close($socket);

            return self::FAILURE;
        }

        // Set non-blocking or timeout so we can update heartbeat
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 0]);

        // Initial signature load
        $parser->loadSignatures();
        $this->info('Loaded application signatures into memory.');

        $packetCount = 0;
        $matchedCount = 0;
        $lastHeartbeat = 0;

        NmsSetting::set('syslog_daemon_status', 'running');
        NmsSetting::set('syslog_daemon_host', $host);
        NmsSetting::set('syslog_daemon_port', (string) $port);

        while (true) {
            $buffer = '';
            $from = '';
            $fromPort = 0;

            $now = time();
            if ($now - $lastHeartbeat >= 3) {
                NmsSetting::set('syslog_daemon_last_heartbeat', now()->toIso8601String());
                NmsSetting::set('syslog_daemon_packets_total', (string) $packetCount);
                NmsSetting::set('syslog_daemon_matched_total', (string) $matchedCount);
                $lastHeartbeat = $now;
            }

            $bytes = @socket_recvfrom($socket, $buffer, 4096, 0, $from, $fromPort);

            if ($bytes === false) {
                // Timeout or no packet, continue loop
                continue;
            }

            if ($bytes > 0 && trim($buffer) !== '') {
                $packetCount++;

                if ($packetCount <= 10) {
                    file_put_contents(storage_path('logs/syslog_sample.log'), '['.now()->toIso8601String()."] FROM {$from}: ".trim($buffer)."\n", FILE_APPEND);
                }

                try {
                    $parsed = $parser->parse($buffer, $from);

                    if ($parsed['matched'] || ($logAll && $parsed['domain'])) {
                        $log = $parser->recordActivity($parsed, $logAll, $from);
                        if ($log) {
                            $matchedCount++;
                            $deviceInfo = $log->hostname ? "{$log->ip_address} ({$log->hostname})" : $log->ip_address;
                            $routerTag = $log->router_name ? "<fg=magenta>[{$log->router_name}]</> " : '';
                            $this->line(sprintf(
                                '[<fg=gray>%s</>] %s<fg=green>[%s]</> <fg=yellow>%s</> -> <fg=cyan>%s</> (%s)',
                                now()->format('H:i:s'),
                                $routerTag,
                                $log->app_name,
                                $deviceInfo,
                                $log->domain,
                                $log->query_type
                            ));
                        }
                    }
                } catch (Throwable $e) {
                    $this->warn('Parsing error: '.$e->getMessage());
                }
            }
        }

        socket_close($socket);
        NmsSetting::set('syslog_daemon_status', 'stopped');

        return self::SUCCESS;
    }
}
