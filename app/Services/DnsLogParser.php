<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AppSignature;
use App\Models\Device;
use App\Models\Router;
use Illuminate\Support\Collection;

class DnsLogParser
{
    /**
     * @var Collection<int, AppSignature>|null
     */
    protected ?Collection $signatures = null;

    /**
     * @var array<string, Device>
     */
    protected array $deviceCache = [];

    /**
     * Store last seen client IP from multi-line MikroTik 'dns,packet' logs
     */
    protected ?string $lastClientIp = null;

    protected int $lastClientIpTime = 0;

    /**
     * Load or reload signatures into memory for fast lookup.
     */
    public function loadSignatures(): void
    {
        $this->signatures = AppSignature::query()->where('is_active', true)->get();
    }

    /**
     * Parse a raw syslog message and extract DNS activity.
     *
     * @return array{matched: bool, ip: ?string, domain: ?string, app: ?string, category: ?string, type: ?string, raw: string}
     */
    public function parse(string $message, ?string $senderIp = null): array
    {
        if ($this->signatures === null) {
            $this->loadSignatures();
        }

        $result = [
            'matched' => false,
            'ip' => null,
            'domain' => null,
            'app' => null,
            'category' => null,
            'type' => 'A',
            'raw' => $message,
        ];

        // Track state from multi-line MikroTik 'dns,packet' queries:
        // Line 1: '--- got query from 192.168.10.82:61669:'
        if (preg_match('/(?:got\s+query\s+from|query\s+from)\s+([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)(?::\d+)?/i', $message, $mClient)) {
            $this->lastClientIp = $mClient[1];
            $this->lastClientIpTime = time();
        }

        // Filter out non-DNS messages quickly
        if (! stripos($message, 'dns') && ! stripos($message, 'query') && ! stripos($message, 'question')) {
            return $result;
        }

        $clientIp = null;
        $domain = null;
        $queryType = 'A';

        // Format A: MikroTik multi-line 'dns,packet question: ipcdn.apple.com:A:IN' or 'question: domain:type:IN'
        if (preg_match('/question:\s+([a-zA-Z0-9.-]+\.[a-zA-Z]{2,}):([A-Za-z0-9_-]+)(?::IN)?/i', $message, $mQ)) {
            $domain = strtolower($mQ[1]);
            $queryType = strtoupper($mQ[2]);
            if ($this->lastClientIp && (time() - $this->lastClientIpTime <= 3)) {
                $clientIp = $this->lastClientIp;
            }
        }
        // Format B: Single line "query from 192.168.88.100: #1234 a www.youtube.com" or with port
        elseif (preg_match('/query\s+from\s+([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)(?::\d+)?:\s+(?:#\d+\s+)?([A-Za-z0-9_-]+)\s+([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $message, $m)) {
            $clientIp = $m[1];
            $queryType = strtoupper($m[2]);
            $domain = strtolower($m[3]);
        }
        // Format C: "dns: 192.168.88.100:54321 query: ... www.youtube.com"
        elseif (preg_match('/(?:dns:?\s+)?([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+):(?:\d+)\s+(?:query|question):?\s+(?:#\d+\s+)?([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})(?:\s+([A-Za-z0-9]+))?/i', $message, $m)) {
            $clientIp = $m[1];
            $domain = strtolower($m[2]);
            $queryType = ! empty($m[3]) ? strtoupper($m[3]) : 'A';
        }
        // Format D: "question: [domain] [type]" with IP earlier in the line
        elseif (preg_match('/([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+).*?(?:question|query):\s+([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})(?:\s+([A-Za-z0-9]+))?/i', $message, $m)) {
            $clientIp = $m[1];
            $domain = strtolower($m[2]);
            $queryType = ! empty($m[3]) ? strtoupper($m[3]) : 'A';
        }
        // Format E: Standard fallback detection
        elseif (preg_match('/([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $message, $mDomain)) {
            if (preg_match('/([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})/', $message, $mIp)) {
                $clientIp = $mIp[1];
                $domain = strtolower($mDomain[1]);
            }
        }

        // Clean domain
        if ($domain) {
            $domain = trim($domain, '. ');
        }

        // If no IP found yet, use sender IP or last seen client IP
        if (! $clientIp && $this->lastClientIp && (time() - $this->lastClientIpTime <= 3)) {
            $clientIp = $this->lastClientIp;
        }

        // Ignore common router self-queries or invalid IPs
        if (! $clientIp || $clientIp === '0.0.0.0' || $clientIp === '127.0.0.1' || ! $domain || ! str_contains($domain, '.')) {
            return $result;
        }

        // Ignore reverse PTR lookups (.in-addr.arpa or .ip6.arpa)
        if (str_ends_with($domain, '.arpa')) {
            return $result;
        }

        $result['ip'] = $clientIp;
        $result['domain'] = $domain;
        $result['type'] = $queryType;

        // Match against signatures
        foreach ($this->signatures as $signature) {
            if ($signature->matchesDomain($domain)) {
                $result['matched'] = true;
                $result['app'] = $signature->app_name;
                $result['category'] = $signature->category;
                break;
            }
        }

        return $result;
    }

    /**
     * @var array<string, Router|null>
     */
    protected array $routerCache = [];

    /**
     * Resolve Router by sender IP address.
     */
    public function getRouterByIp(?string $senderIp): ?Router
    {
        if (! $senderIp) {
            return null;
        }

        if (! array_key_exists($senderIp, $this->routerCache)) {
            $this->routerCache[$senderIp] = Router::query()->where('ip_address', $senderIp)->first();
        }

        return $this->routerCache[$senderIp];
    }

    /**
     * Process parsed result and store activity log in database.
     */
    public function recordActivity(array $parsed, bool $storeUnmatched = false, ?string $senderIp = null): ?ActivityLog
    {
        if (empty($parsed['ip']) || empty($parsed['domain'])) {
            return null;
        }

        $appName = $parsed['app'] ?? null;
        $category = $parsed['category'] ?? 'General';

        if (! $appName) {
            if (! $storeUnmatched) {
                return null;
            }
            $appName = 'Web / '.parse_url('http://'.$parsed['domain'], PHP_URL_HOST);
            $category = 'Other';
        }

        $ip = $parsed['ip'];
        $router = $this->getRouterByIp($senderIp);
        $routerId = $router?->id;
        $routerName = $router?->name;

        // Get or cache device
        if (! isset($this->deviceCache[$ip])) {
            $device = Device::query()->where('ip_address', $ip)->first();
            if (! $device) {
                $device = Device::query()->create([
                    'router_id' => $routerId,
                    'router_name' => $routerName,
                    'ip_address' => $ip,
                    'status' => 'active',
                    'source' => 'dns_traffic',
                    'is_online' => true,
                    'last_seen_at' => now(),
                ]);
            } else {
                $updates = ['is_online' => true, 'last_seen_at' => now()];
                if (! $device->router_id && $routerId) {
                    $updates['router_id'] = $routerId;
                    $updates['router_name'] = $routerName;
                }
                $device->update($updates);
            }
            $this->deviceCache[$ip] = $device;
        } else {
            $this->deviceCache[$ip]->update([
                'last_seen_at' => now(),
                'is_online' => true,
            ]);
        }

        $device = $this->deviceCache[$ip];

        // Intelligent Debouncing: Avoid duplicate spam logs if the same device is querying the same app in 20 seconds
        $recentLog = ActivityLog::query()
            ->where('ip_address', $ip)
            ->where('app_name', $appName)
            ->where('logged_at', '>=', now()->subSeconds(20))
            ->latest('id')
            ->first();

        if ($recentLog) {
            $recentLog->increment('hit_count');
            $recentLog->update([
                'domain' => $parsed['domain'],
                'logged_at' => now(),
            ]);

            return $recentLog;
        }

        return ActivityLog::query()->create([
            'device_id' => $device->id,
            'router_id' => $routerId ?: $device->router_id,
            'router_name' => $routerName ?: $device->router_name,
            'ip_address' => $ip,
            'hostname' => $device->hostname ?: $device->comment,
            'app_name' => $appName,
            'category' => $category,
            'domain' => $parsed['domain'],
            'query_type' => $parsed['type'] ?? 'A',
            'hit_count' => 1,
            'raw_log' => mb_substr($parsed['raw'] ?? '', 0, 500),
            'logged_at' => now(),
        ]);
    }
}
