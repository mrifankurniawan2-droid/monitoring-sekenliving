<?php

namespace App\Services;

use App\Models\Device;
use App\Models\NmsSetting;
use App\Models\Router;
use Exception;
use Illuminate\Support\Facades\Http;

class MikroTikService
{
    protected string $host;

    protected int $port;

    protected string $user;

    protected string $pass;

    protected string $apiType; // 'routeros_api' or 'rest'

    protected bool $useSsl;

    protected int $timeout;

    /**
     * @var resource|null
     */
    protected $socket = null;

    protected ?Router $router = null;

    public function __construct(null|array|Router $config = null)
    {
        if ($config instanceof Router) {
            $this->router = $config;
            $this->host = $config->ip_address;
            $this->port = (int) $config->port;
            $this->user = $config->username;
            $this->pass = $config->getDecryptedPassword() ?? '';
            $this->apiType = $config->api_type;
            $this->useSsl = (bool) $config->use_ssl;
        } else {
            $this->host = $config['host'] ?? NmsSetting::get('mikrotik_host', config('services.mikrotik.host', env('MIKROTIK_HOST', '192.168.10.1')));
            $this->port = (int) ($config['port'] ?? NmsSetting::get('mikrotik_port', config('services.mikrotik.port', env('MIKROTIK_PORT', 8728))));
            $this->user = $config['user'] ?? NmsSetting::get('mikrotik_user', config('services.mikrotik.user', env('MIKROTIK_USER', 'seken')));
            $this->pass = $config['pass'] ?? NmsSetting::get('mikrotik_pass', config('services.mikrotik.pass', env('MIKROTIK_PASS', '')));
            $this->apiType = $config['api_type'] ?? NmsSetting::get('mikrotik_api_type', config('services.mikrotik.api_type', env('MIKROTIK_API_TYPE', 'routeros_api')));
            $this->useSsl = filter_var($config['use_ssl'] ?? NmsSetting::get('mikrotik_use_ssl', config('services.mikrotik.use_ssl', env('MIKROTIK_USE_SSL', false))), FILTER_VALIDATE_BOOLEAN);
        }
        $this->timeout = 5;
    }

    /**
     * Test connection to MikroTik.
     *
     * @return array{success: bool, message: string, details?: array}
     */
    public function testConnection(): array
    {
        try {
            if ($this->apiType === 'rest') {
                return $this->testRestConnection();
            }

            return $this->testSocketConnection();
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Sync devices for all active routers in database.
     *
     * @return array{total_synced: int, routers: array}
     */
    public static function syncAllActiveRouters(): array
    {
        $routers = Router::where('is_active', true)->get();
        $totalSynced = 0;
        $results = [];

        foreach ($routers as $r) {
            try {
                $service = new self($r);
                $res = $service->syncDevices();
                $totalSynced += $res['synced'];
                $results[$r->name] = $res;
            } catch (\Throwable $e) {
                $r->update([
                    'last_sync_at' => now(),
                    'last_sync_status' => 'failed: '.$e->getMessage(),
                ]);
                $results[$r->name] = ['error' => $e->getMessage()];
            }
        }

        return [
            'total_synced' => $totalSynced,
            'routers' => $results,
        ];
    }

    /**
     * Sync devices from both /ip/dhcp-server/lease and /ip/arp.
     *
     * @return array{synced: int, leases: int, arps: int, errors: array}
     */
    public function syncDevices(): array
    {
        $leases = [];
        $arps = [];
        $errors = [];

        if ($this->apiType === 'rest') {
            try {
                $leases = $this->fetchRest('/ip/dhcp-server/lease');
            } catch (Exception $e) {
                $errors[] = 'REST DHCP Leases: '.$e->getMessage();
            }

            try {
                $arps = $this->fetchRest('/ip/arp');
            } catch (Exception $e) {
                $errors[] = 'REST ARP: '.$e->getMessage();
            }
        } else {
            try {
                $this->connectSocket();
                $leases = $this->comm('/ip/dhcp-server/lease/print');
                $arps = $this->comm('/ip/arp/print');
                $this->disconnectSocket();
            } catch (Exception $e) {
                $errors[] = 'RouterOS Socket API: '.$e->getMessage();
                $this->disconnectSocket();
            }
        }

        $now = now();
        $syncedCount = 0;
        $deviceMap = [];

        // 1. Process DHCP leases
        foreach ($leases as $lease) {
            $ip = $lease['address'] ?? null;
            if (! $ip || ! filter_var($ip, FILTER_VALIDATE_IP)) {
                continue;
            }

            $mac = strtolower($lease['mac-address'] ?? '');
            $hostname = $lease['host-name'] ?? $lease['hostname'] ?? null;
            $comment = $lease['comment'] ?? null;
            $status = $lease['status'] ?? 'bound';
            $isDynamic = ($lease['dynamic'] ?? 'false') === 'true';

            $deviceMap[$ip] = [
                'ip_address' => $ip,
                'mac_address' => $mac ?: null,
                'hostname' => $hostname ?: null,
                'comment' => $comment ?: null,
                'interface' => null,
                'status' => $status,
                'source' => 'dhcp',
                'is_online' => in_array(strtolower($status), ['bound', 'active', 'static']),
                'last_seen_at' => $now,
            ];
        }

        // 2. Process ARP entries (catches static IP devices and interfaces)
        foreach ($arps as $arp) {
            $ip = $arp['address'] ?? null;
            if (! $ip || ! filter_var($ip, FILTER_VALIDATE_IP)) {
                continue;
            }

            $mac = strtolower($arp['mac-address'] ?? '');
            $interface = $arp['interface'] ?? null;
            $comment = $arp['comment'] ?? null;
            $complete = ($arp['complete'] ?? 'true') === 'true';

            if (isset($deviceMap[$ip])) {
                $deviceMap[$ip]['source'] = 'both';
                if ($interface) {
                    $deviceMap[$ip]['interface'] = $interface;
                }
                if ($comment && empty($deviceMap[$ip]['comment'])) {
                    $deviceMap[$ip]['comment'] = $comment;
                }
                if ($mac && empty($deviceMap[$ip]['mac_address'])) {
                    $deviceMap[$ip]['mac_address'] = $mac;
                }
                $deviceMap[$ip]['is_online'] = true;
            } else {
                $deviceMap[$ip] = [
                    'ip_address' => $ip,
                    'mac_address' => $mac ?: null,
                    'hostname' => null,
                    'comment' => $comment ?: null,
                    'interface' => $interface ?: null,
                    'status' => $complete ? 'complete' : 'incomplete',
                    'source' => 'arp',
                    'is_online' => $complete,
                    'last_seen_at' => $now,
                ];
            }
        }

        // 3. Upsert into database
        foreach ($deviceMap as $ip => $data) {
            if ($this->router) {
                $data['router_id'] = $this->router->id;
                $data['router_name'] = $this->router->name;
            }

            Device::query()->updateOrCreate(
                ['ip_address' => $ip],
                $data
            );
            $syncedCount++;
        }

        if ($this->router) {
            $this->router->update([
                'device_count' => $syncedCount,
                'last_sync_at' => $now,
                'last_sync_status' => empty($errors) ? 'success' : 'partial_error',
            ]);
        }

        return [
            'synced' => $syncedCount,
            'leases' => count($leases),
            'arps' => count($arps),
            'errors' => $errors,
        ];
    }

    // -------------------------------------------------------------
    // REST API IMPLEMENTATION (RouterOS v7+)
    // -------------------------------------------------------------

    protected function testRestConnection(): array
    {
        $proto = $this->useSsl ? 'https' : 'http';
        $port = $this->port ?: ($this->useSsl ? 443 : 80);
        $url = "{$proto}://{$this->host}:{$port}/rest/system/resource";

        $response = Http::withBasicAuth($this->user, $this->pass)
            ->withoutVerifying()
            ->timeout($this->timeout)
            ->get($url);

        if ($response->successful()) {
            $data = $response->json();

            return [
                'success' => true,
                'message' => 'Successfully connected to RouterOS REST API ('.($data['version'] ?? 'v7').')',
                'details' => $data,
            ];
        }

        return [
            'success' => false,
            'message' => 'REST request failed with HTTP '.$response->status().': '.$response->body(),
        ];
    }

    protected function fetchRest(string $endpoint): array
    {
        $proto = $this->useSsl ? 'https' : 'http';
        $port = $this->port ?: ($this->useSsl ? 443 : 80);
        $url = "{$proto}://{$this->host}:{$port}/rest".(str_starts_with($endpoint, '/') ? $endpoint : '/'.$endpoint);

        $response = Http::withBasicAuth($this->user, $this->pass)
            ->withoutVerifying()
            ->timeout($this->timeout)
            ->get($url);

        if (! $response->successful()) {
            throw new Exception("REST HTTP {$response->status()}: ".$response->body());
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    // -------------------------------------------------------------
    // NATIVE SOCKET API IMPLEMENTATION (RouterOS v6 & v7)
    // -------------------------------------------------------------

    protected function testSocketConnection(): array
    {
        $this->connectSocket();
        $resources = $this->comm('/system/resource/print');
        $this->disconnectSocket();

        $info = $resources[0] ?? [];
        $version = $info['version'] ?? 'RouterOS';

        return [
            'success' => true,
            'message' => "Successfully connected via RouterOS API Socket (Port {$this->port}, {$version})",
            'details' => $info,
        ];
    }

    protected function connectSocket(): void
    {
        $transport = $this->useSsl ? 'ssl://' : '';
        $errNo = 0;
        $errStr = '';

        $socket = @fsockopen($transport.$this->host, $this->port, $errNo, $errStr, $this->timeout);

        if (! $socket) {
            throw new Exception("Unable to connect to RouterOS API at {$this->host}:{$this->port} ({$errNo}: {$errStr})");
        }

        stream_set_timeout($socket, $this->timeout);
        $this->socket = $socket;

        // Perform login
        $this->loginSocket();
    }

    protected function loginSocket(): void
    {
        // Modern login (RouterOS v6.43+ and v7)
        $this->writeWord('/login');
        $this->writeWord('=name='.$this->user);
        $this->writeWord('=password='.$this->pass);
        $this->writeWord(''); // Sentence terminator

        $response = $this->readSentence();

        // Check if !done or !trap
        if (isset($response[0]) && $response[0] === '!done') {
            // Check if challenge returned (older RouterOS v6 pre-6.43)
            foreach ($response as $line) {
                if (str_starts_with($line, '=ret=')) {
                    $challenge = substr($line, 5);
                    $key = md5(chr(0).$this->pass.pack('H*', $challenge));
                    $this->writeWord('/login');
                    $this->writeWord('=name='.$this->user);
                    $this->writeWord('=response=00'.$key);
                    $this->writeWord('');

                    $resp2 = $this->readSentence();
                    if (! isset($resp2[0]) || $resp2[0] !== '!done') {
                        throw new Exception('RouterOS legacy challenge login failed: '.json_encode($resp2));
                    }

                    return;
                }
            }

            return;
        }

        if (isset($response[0]) && $response[0] === '!trap') {
            $msg = 'Authentication failed';
            foreach ($response as $line) {
                if (str_starts_with($line, '=message=')) {
                    $msg = substr($line, 9);
                }
            }
            throw new Exception("MikroTik login error: {$msg}");
        }

        throw new Exception('Unexpected response during RouterOS login: '.json_encode($response));
    }

    /**
     * Send command and return array of records.
     *
     * @param  array<string>  $params
     * @return array<array<string, string>>
     */
    public function comm(string $command, array $params = []): array
    {
        if (! $this->socket) {
            throw new Exception('Socket not connected.');
        }

        $this->writeWord($command);
        foreach ($params as $param) {
            $this->writeWord($param);
        }
        $this->writeWord('');

        $results = [];
        $currentRecord = null;

        while (true) {
            $sentence = $this->readSentence();
            if (empty($sentence)) {
                break;
            }

            $type = $sentence[0];

            if ($type === '!re') {
                $record = [];
                for ($i = 1; $i < count($sentence); $i++) {
                    $item = $sentence[$i];
                    if (str_starts_with($item, '=')) {
                        $eqPos = strpos($item, '=', 1);
                        if ($eqPos !== false) {
                            $key = substr($item, 1, $eqPos - 1);
                            $val = substr($item, $eqPos + 1);
                            $record[$key] = $val;
                        }
                    }
                }
                $results[] = $record;
            } elseif ($type === '!done') {
                break;
            } elseif ($type === '!trap') {
                $msg = 'MikroTik trap error';
                foreach ($sentence as $line) {
                    if (str_starts_with($line, '=message=')) {
                        $msg = substr($line, 9);
                    }
                }
                throw new Exception($msg);
            } elseif ($type === '!fatal') {
                throw new Exception('Fatal error from MikroTik API');
            }
        }

        return $results;
    }

    protected function writeWord(string $word): void
    {
        $len = strlen($word);

        if ($len < 0x80) {
            fwrite($this->socket, chr($len));
        } elseif ($len < 0x4000) {
            $len |= 0x8000;
            fwrite($this->socket, chr(($len >> 8) & 0xFF).chr($len & 0xFF));
        } elseif ($len < 0x200000) {
            $len |= 0xC00000;
            fwrite($this->socket, chr(($len >> 16) & 0xFF).chr(($len >> 8) & 0xFF).chr($len & 0xFF));
        } elseif ($len < 0x10000000) {
            $len |= 0xE0000000;
            fwrite($this->socket, chr(($len >> 24) & 0xFF).chr(($len >> 16) & 0xFF).chr(($len >> 8) & 0xFF).chr($len & 0xFF));
        } else {
            fwrite($this->socket, chr(0xF0).chr(($len >> 24) & 0xFF).chr(($len >> 16) & 0xFF).chr(($len >> 8) & 0xFF).chr($len & 0xFF));
        }

        if ($len > 0) {
            fwrite($this->socket, $word);
        }
    }

    /**
     * @return array<string>
     */
    protected function readSentence(): array
    {
        $sentence = [];
        while (true) {
            $word = $this->readWord();
            if ($word === '') {
                break;
            }
            $sentence[] = $word;
        }

        return $sentence;
    }

    protected function readWord(): string
    {
        $byte = fgetc($this->socket);
        if ($byte === false) {
            return '';
        }

        $b = ord($byte);
        $len = 0;

        if (($b & 0x80) === 0x00) {
            $len = $b;
        } elseif (($b & 0xC0) === 0x80) {
            $b2 = ord(fgetc($this->socket));
            $len = (($b & 0x3F) << 8) + $b2;
        } elseif (($b & 0xE0) === 0xC0) {
            $b2 = ord(fgetc($this->socket));
            $b3 = ord(fgetc($this->socket));
            $len = (($b & 0x1F) << 16) + ($b2 << 8) + $b3;
        } elseif (($b & 0xF0) === 0xE0) {
            $b2 = ord(fgetc($this->socket));
            $b3 = ord(fgetc($this->socket));
            $b4 = ord(fgetc($this->socket));
            $len = (($b & 0x0F) << 24) + ($b2 << 16) + ($b3 << 8) + $b4;
        } elseif ($b === 0xF0) {
            $b1 = ord(fgetc($this->socket));
            $b2 = ord(fgetc($this->socket));
            $b3 = ord(fgetc($this->socket));
            $b4 = ord(fgetc($this->socket));
            $len = ($b1 << 24) + ($b2 << 16) + ($b3 << 8) + $b4;
        }

        if ($len === 0) {
            return '';
        }

        $word = '';
        while (strlen($word) < $len) {
            $chunk = fread($this->socket, $len - strlen($word));
            if ($chunk === false || strlen($chunk) === 0) {
                break;
            }
            $word .= $chunk;
        }

        return $word;
    }

    protected function disconnectSocket(): void
    {
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    public function __destruct()
    {
        $this->disconnectSocket();
    }
}
