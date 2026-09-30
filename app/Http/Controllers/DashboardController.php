<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AppSignature;
use App\Models\Device;
use App\Models\NmsSetting;
use App\Models\Router;
use App\Services\DnsLogParser;
use App\Services\MikroTikService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    /**
     * Display the main NMS dashboard view.
     */
    public function index(): View
    {
        $settings = [
            'mikrotik_host' => NmsSetting::get('mikrotik_host', env('MIKROTIK_HOST', '192.168.10.1')),
            'mikrotik_port' => NmsSetting::get('mikrotik_port', env('MIKROTIK_PORT', '8728')),
            'mikrotik_user' => NmsSetting::get('mikrotik_user', env('MIKROTIK_USER', 'seken')),
            'mikrotik_api_type' => NmsSetting::get('mikrotik_api_type', env('MIKROTIK_API_TYPE', 'routeros_api')),
            'mikrotik_use_ssl' => NmsSetting::get('mikrotik_use_ssl', '0') === '1',
            'syslog_port' => NmsSetting::get('syslog_port', env('SYSLOG_PORT', '514')),
        ];

        $appSignatures = AppSignature::query()->orderBy('app_name')->get();
        $routers = Router::query()->orderBy('name')->get();

        return view('dashboard', compact('settings', 'appSignatures', 'routers'));
    }

    /**
     * Get aggregate statistics for charts and widgets.
     */
    public function stats(): JsonResponse
    {
        $today = Carbon::today();

        $totalDevices = Device::query()->count();
        $onlineDevices = Device::query()->where('is_online', true)->count();
        $activitiesToday = ActivityLog::query()->where('logged_at', '>=', $today)->count();
        $uniqueAppsToday = ActivityLog::query()->where('logged_at', '>=', $today)->distinct('app_name')->count('app_name');

        // Top apps today
        $topApps = ActivityLog::query()
            ->selectRaw('app_name, category, COUNT(*) as count')
            ->where('logged_at', '>=', $today)
            ->groupBy('app_name', 'category')
            ->orderByDesc('count')
            ->limit(7)
            ->get();

        // Get colors for top apps
        $signatures = AppSignature::query()->pluck('badge_color', 'app_name');
        $topAppsData = $topApps->map(function ($item) use ($signatures) {
            return [
                'app_name' => $item->app_name,
                'category' => $item->category ?? 'General',
                'count' => (int) $item->count,
                'color' => $signatures[$item->app_name] ?? '#64748b',
            ];
        });

        // Top active devices today
        $topDevices = ActivityLog::query()
            ->selectRaw('ip_address, hostname, COUNT(*) as count')
            ->where('logged_at', '>=', $today)
            ->groupBy('ip_address', 'hostname')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        // Syslog daemon status
        $lastHeartbeat = NmsSetting::get('syslog_daemon_last_heartbeat');
        $isDaemonRunning = false;
        if ($lastHeartbeat) {
            $diffSeconds = Carbon::parse($lastHeartbeat)->diffInSeconds(now());
            $isDaemonRunning = $diffSeconds <= 15;
        }

        return response()->json([
            'total_devices' => $totalDevices,
            'online_devices' => $onlineDevices,
            'activities_today' => $activitiesToday,
            'unique_apps_today' => $uniqueAppsToday,
            'top_apps' => $topAppsData,
            'top_devices' => $topDevices,
            'daemon' => [
                'running' => $isDaemonRunning,
                'host' => NmsSetting::get('syslog_daemon_host', '0.0.0.0'),
                'port' => NmsSetting::get('syslog_daemon_port', '514'),
                'last_heartbeat' => $lastHeartbeat,
                'packets_total' => (int) NmsSetting::get('syslog_daemon_packets_total', 0),
                'matched_total' => (int) NmsSetting::get('syslog_daemon_matched_total', 0),
            ],
        ]);
    }

    /**
     * Get paginated or incremental activities stream.
     */
    public function activities(Request $request): JsonResponse
    {
        $query = ActivityLog::query()->with('device')->latest('logged_at');

        if ($request->boolean('hide_system')) {
            $query->whereNotIn('app_name', ['Google Services']);
        }

        if ($request->filled('app')) {
            $query->where('app_name', $request->app);
        }

        if ($request->filled('router_id')) {
            $query->where('router_id', $request->router_id);
        }

        if ($request->filled('ip')) {
            $query->where('ip_address', $request->ip);
        }

        if ($request->filled('search')) {
            $search = '%'.$request->search.'%';
            $query->where(function ($q) use ($search) {
                $q->where('ip_address', 'like', $search)
                    ->orWhere('hostname', 'like', $search)
                    ->orWhere('domain', 'like', $search)
                    ->orWhere('app_name', 'like', $search);
            });
        }

        if ($request->boolean('live')) {
            $logs = $query->limit(25)->get();

            return response()->json([
                'data' => $logs,
            ]);
        }

        $logs = $query->paginate(25);

        return response()->json($logs);
    }

    /**
     * Get list of devices with filter and search.
     */
    public function devices(Request $request): JsonResponse
    {
        $query = Device::query()->with('router')->withCount(['activities as today_activities_count' => function ($q) {
            $q->where('logged_at', '>=', Carbon::today());
        }])->orderByDesc('is_online')->orderByDesc('last_seen_at');

        if ($request->filled('router_id')) {
            $query->where('router_id', $request->router_id);
        }

        if ($request->filled('search')) {
            $search = '%'.$request->search.'%';
            $query->where(function ($q) use ($search) {
                $q->where('ip_address', 'like', $search)
                    ->orWhere('mac_address', 'like', $search)
                    ->orWhere('hostname', 'like', $search)
                    ->orWhere('comment', 'like', $search);
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'online') {
                $query->where('is_online', true);
            } elseif ($request->status === 'offline') {
                $query->where('is_online', false);
            }
        }

        $devices = $query->paginate(30);

        return response()->json($devices);
    }

    /**
     * Get specific device detail with app usage breakdown.
     */
    public function deviceDetail(Device $device): JsonResponse
    {
        $today = Carbon::today();

        $appUsage = ActivityLog::query()
            ->where('device_id', $device->id)
            ->where('logged_at', '>=', $today)
            ->selectRaw('app_name, category, count(*) as count, max(logged_at) as last_activity')
            ->groupBy('app_name', 'category')
            ->orderByDesc('count')
            ->get();

        $recentActivities = ActivityLog::query()
            ->where('device_id', $device->id)
            ->latest('logged_at')
            ->limit(15)
            ->get();

        return response()->json([
            'device' => $device,
            'app_usage' => $appUsage,
            'recent_activities' => $recentActivities,
        ]);
    }

    /**
     * Sync devices from MikroTik on-demand.
     */
    public function syncMikrotik(): JsonResponse
    {
        try {
            $mikrotik = new MikroTikService;
            $result = $mikrotik->syncDevices();

            return response()->json([
                'success' => true,
                'message' => "Successfully synced {$result['synced']} devices from MikroTik (Leases: {$result['leases']}, ARP: {$result['arps']})",
                'details' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'MikroTik Sync failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test connection to MikroTik with submitted or saved credentials.
     */
    public function testMikrotik(Request $request): JsonResponse
    {
        $config = [];
        if ($request->filled('host')) {
            $config = [
                'host' => $request->host,
                'port' => (int) $request->port,
                'user' => $request->user,
                'pass' => $request->pass ?? '',
                'api_type' => $request->api_type ?? 'routeros_api',
                'use_ssl' => (bool) $request->use_ssl,
            ];
        }

        $service = new MikroTikService($config);
        $result = $service->testConnection();

        return response()->json($result);
    }

    /**
     * Save MikroTik & NMS Settings.
     */
    public function saveSettings(Request $request): JsonResponse
    {
        $request->validate([
            'mikrotik_host' => 'required|string',
            'mikrotik_port' => 'required|numeric',
            'mikrotik_user' => 'required|string',
            'mikrotik_api_type' => 'required|in:routeros_api,rest',
            'syslog_port' => 'required|numeric',
        ]);

        NmsSetting::set('mikrotik_host', $request->mikrotik_host);
        NmsSetting::set('mikrotik_port', (string) $request->mikrotik_port);
        NmsSetting::set('mikrotik_user', $request->mikrotik_user);

        if ($request->has('mikrotik_pass') && $request->mikrotik_pass !== null) {
            NmsSetting::set('mikrotik_pass', (string) $request->mikrotik_pass);
        }

        NmsSetting::set('mikrotik_api_type', $request->mikrotik_api_type);
        NmsSetting::set('mikrotik_use_ssl', $request->boolean('mikrotik_use_ssl') ? '1' : '0');
        NmsSetting::set('syslog_port', (string) $request->syslog_port);

        return response()->json([
            'success' => true,
            'message' => 'Settings saved successfully!',
        ]);
    }

    /**
     * Store new or updated App Signature.
     */
    public function saveSignature(Request $request): JsonResponse
    {
        $request->validate([
            'app_name' => 'required|string|max:100',
            'category' => 'required|string|max:100',
            'domain_pattern' => 'required|string',
            'badge_color' => 'required|string|max:20',
        ]);

        $signature = AppSignature::query()->updateOrCreate(
            ['id' => $request->id],
            [
                'app_name' => $request->app_name,
                'category' => $request->category,
                'domain_pattern' => $request->domain_pattern,
                'badge_color' => $request->badge_color,
                'icon' => $request->icon ?? 'globe',
                'is_active' => $request->boolean('is_active', true),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Application signature saved!',
            'signature' => $signature,
        ]);
    }

    /**
     * Delete an App Signature.
     */
    public function deleteSignature(AppSignature $signature): JsonResponse
    {
        $signature->delete();

        return response()->json([
            'success' => true,
            'message' => 'Application signature deleted!',
        ]);
    }

    /**
     * Simulate a DNS query log from web UI for live testing.
     */
    public function simulateLog(Request $request, DnsLogParser $parser): JsonResponse
    {
        $request->validate([
            'ip' => 'required|ip',
            'domain' => 'required|string',
        ]);

        $rawMessage = sprintf(
            '<13>%s mikrotik dns,packet: query from %s:53421: #%d A %s',
            now()->format('M/d H:i:s'),
            $request->ip,
            rand(1000, 9999),
            $request->domain
        );

        $parsed = $parser->parse($rawMessage);
        $log = $parser->recordActivity($parsed, true);

        return response()->json([
            'success' => true,
            'message' => 'Simulated DNS query processed!',
            'parsed' => $parsed,
            'log' => $log,
        ]);
    }

    /**
     * Get list of registered routers.
     */
    public function routers(): JsonResponse
    {
        $routers = Router::query()->withCount('devices')->orderBy('name')->get();

        return response()->json([
            'data' => $routers,
        ]);
    }

    /**
     * Create or update a Router.
     */
    public function saveRouter(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'ip_address' => 'required|string|max:45',
            'port' => 'required|numeric',
            'username' => 'required|string|max:100',
            'api_type' => 'required|in:routeros_api,rest',
        ]);

        $data = [
            'name' => $request->name,
            'ip_address' => $request->ip_address,
            'port' => (int) $request->port,
            'username' => $request->username,
            'api_type' => $request->api_type,
            'use_ssl' => $request->boolean('use_ssl'),
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($request->filled('password')) {
            $data['password'] = $request->password;
        }

        $router = Router::query()->updateOrCreate(
            ['id' => $request->id],
            $data
        );

        return response()->json([
            'success' => true,
            'message' => 'Router successfully saved!',
            'router' => $router,
        ]);
    }

    /**
     * Delete a Router.
     */
    public function deleteRouter(Router $router): JsonResponse
    {
        $router->delete();

        return response()->json([
            'success' => true,
            'message' => 'Router deleted successfully!',
        ]);
    }

    /**
     * Test connection to a specific Router.
     */
    public function testRouter(Request $request): JsonResponse
    {
        $config = [
            'host' => $request->ip_address,
            'port' => (int) $request->port,
            'user' => $request->username,
            'pass' => $request->password ?? '',
            'api_type' => $request->api_type ?? 'routeros_api',
            'use_ssl' => (bool) $request->use_ssl,
        ];

        // If ID provided and password empty, load stored decrypted password
        if ($request->filled('id') && empty($request->password)) {
            $existing = Router::query()->find($request->id);
            if ($existing) {
                $config['pass'] = $existing->getDecryptedPassword() ?? '';
            }
        }

        $service = new MikroTikService($config);
        $result = $service->testConnection();

        return response()->json($result);
    }

    /**
     * Sync single Router.
     */
    public function syncRouter(Router $router): JsonResponse
    {
        try {
            $service = new MikroTikService($router);
            $result = $service->syncDevices();

            return response()->json([
                'success' => true,
                'message' => "Successfully synced {$result['synced']} devices from {$router->name}!",
                'details' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => "Sync failed for {$router->name}: ".$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync all active Routers.
     */
    public function syncAllRouters(): JsonResponse
    {
        try {
            $result = MikroTikService::syncAllActiveRouters();

            return response()->json([
                'success' => true,
                'message' => "Synced {$result['total_synced']} devices across all active routers!",
                'details' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Multi-router sync failed: '.$e->getMessage(),
            ], 500);
        }
    }
}
