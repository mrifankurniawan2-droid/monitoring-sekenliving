<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MikroTik Sentinel NMS - Production Safe Network Monitoring</title>
    
    <!-- Modern Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- NMS Stylesheet -->
    <link rel="stylesheet" href="/css/nms.css">
</head>
<body>

    <!-- Header Navigation -->
    <header class="app-header">
        <div class="header-container">
            <a href="/" class="brand">
                <div class="brand-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"></path>
                        <path d="M2 12h20"></path>
                    </svg>
                </div>
                <div class="brand-text">
                    <h1>MikroTik Sentinel NMS</h1>
                    <span>Production Safe Network &amp; App Activity Monitor</span>
                </div>
            </a>

            <!-- Real-time Status Badges -->
            <div class="header-status">
                <div id="daemon-status-badge" class="status-badge offline">
                    <span id="daemon-pulse-dot" class="pulse-dot offline"></span>
                    <span id="daemon-status-text">Syslog UDP: Initializing...</span>
                </div>
            </div>

            <!-- Quick Header Actions -->
            <div class="header-actions">
                <button id="btn-sync-mikrotik" class="btn btn-primary btn-sm">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path>
                    </svg>
                    Sync MikroTik
                </button>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="main-wrapper">

        <!-- Top Statistics Grid -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Connected Devices</h3>
                    <div id="stat-online-devices" class="stat-value">- / -</div>
                    <div class="stat-sub">Online / Total Discovered</div>
                </div>
                <div class="stat-icon devices">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <h3>App Activities Today</h3>
                    <div id="stat-activities-today" class="stat-value">-</div>
                    <div class="stat-sub">Recognized App Queries</div>
                </div>
                <div class="stat-icon activities">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                    </svg>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <h3>Monitored Apps</h3>
                    <div id="stat-unique-apps" class="stat-value">{{ $appSignatures->count() }}</div>
                    <div class="stat-sub">Signatures Active</div>
                </div>
                <div class="stat-icon apps">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <h3>Syslog Stream</h3>
                    <div id="stat-daemon-packets" class="stat-value" style="font-size: 1.15rem; font-family: var(--font-mono); padding-top: 0.35rem;">Monitoring UDP:514</div>
                    <div class="stat-sub">Zero CPU Router Logging</div>
                </div>
                <div class="stat-icon daemon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                    </svg>
                </div>
            </div>
        </section>

        <!-- Navigation Tabs -->
        <nav class="tabs-container">
            <button class="tab-btn active" data-tab="live">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="2"></circle>
                    <path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"></path>
                </svg>
                Live App Activity Stream
                <span class="tab-badge" id="live-stream-status" style="color: var(--accent-emerald);">Streaming</span>
            </button>

            <button class="tab-btn" data-tab="devices">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
                Connected Devices (MikroTik)
            </button>

            <button class="tab-btn" data-tab="analytics">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="20" x2="18" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="14"></line>
                </svg>
                App Analytics
            </button>

            <button class="tab-btn" data-tab="signatures">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                    <line x1="7" y1="7" x2="7.01" y2="7"></line>
                </svg>
                App Signatures ({{ $appSignatures->count() }})
            </button>

            <button class="tab-btn" data-tab="mikrotik-setup">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="4,10 12,14 20,10"></polyline>
                </svg>
                MikroTik Safe Setup Guide
            </button>

            <button class="tab-btn" data-tab="simulator">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="5 3 19 12 5 21 5 3"></polygon>
                </svg>
                Traffic Simulator
            </button>

            <button class="tab-btn" data-tab="routers">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                    <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                    <line x1="6" y1="6" x2="6.01" y2="6"></line>
                    <line x1="6" y1="18" x2="6.01" y2="18"></line>
                </svg>
                Routers (<span id="tab-router-count">{{ $routers->count() }}</span>)
            </button>

            <button class="tab-btn" data-tab="settings">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
                Router &amp; API Settings
            </button>
        </nav>

        <!-- TAB 1: Live Activity Stream -->
        <section id="tab-live" class="tab-content active">
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            Real-Time Application Access Feed
                        </div>
                        <div class="card-subtitle">
                            Displaying real-time application access events resolved via MikroTik Remote DNS logging.
                        </div>
                    </div>
                    <div class="toolbar" style="gap: 0.6rem; align-items: center;">
                        <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; color: var(--text-secondary); cursor: pointer; user-select: none;">
                            <input type="checkbox" id="feed-hide-system" checked>
                            Hide Background/OS Traffic
                        </label>

                        <select id="feed-router-filter" class="input-field btn-sm" style="background: rgba(15,23,42,0.8); font-size: 0.78rem;">
                            <option value="">All Routers</option>
                            @foreach ($routers as $r)
                                <option value="{{ $r->id }}">{{ $r->name }}</option>
                            @endforeach
                        </select>

                        <select id="feed-app-filter" class="input-field btn-sm" style="background: rgba(15,23,42,0.8); font-size: 0.78rem;">
                            <option value="">All Apps</option>
                            @foreach ($appSignatures as $sig)
                                @if ($sig->app_name !== 'Google Services')
                                    <option value="{{ $sig->app_name }}">{{ $sig->app_name }}</option>
                                @endif
                            @endforeach
                        </select>

                        <select id="feed-speed-select" class="input-field btn-sm" style="background: rgba(15,23,42,0.8); font-size: 0.78rem;">
                            <option value="3000">Speed: Normal (3s)</option>
                            <option value="7000" selected>Speed: Slow (7s)</option>
                            <option value="15000">Speed: Relaxed (15s)</option>
                        </select>

                        <button id="btn-pause-live" class="btn btn-outline btn-sm">
                            <span>⏸</span> Pause Feed
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 110px;">Time</th>
                                <th>Device / Client</th>
                                <th>Router Source</th>
                                <th>Detected Application</th>
                                <th>Resolved Domain Query</th>
                                <th>Type</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="activity-feed-tbody">
                            <tr id="empty-activity-row">
                                <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                    Waiting for incoming DNS logs from MikroTik or simulator...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- TAB 2: Connected Devices (MikroTik DHCP & ARP) -->
        <section id="tab-devices" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                <line x1="8" y1="21" x2="16" y2="21"></line>
                                <line x1="12" y1="17" x2="12" y2="21"></line>
                            </svg>
                            Active &amp; Discovered Network Devices
                        </div>
                        <div class="card-subtitle">
                            Data synchronized directly from MikroTik <code>/ip/dhcp-server/lease</code> and <code>/ip/arp</code>.
                        </div>
                    </div>
                    <div class="toolbar">
                        <select id="device-router-filter" class="input-field">
                            <option value="">All Routers</option>
                            @foreach ($routers as $r)
                                <option value="{{ $r->id }}">{{ $r->name }} ({{ $r->ip_address }})</option>
                            @endforeach
                        </select>
                        <div class="search-input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <input type="text" id="device-search" class="input-field" placeholder="Search IP, MAC, Hostname...">
                        </div>
                        <select id="device-status-filter" class="input-field">
                            <option value="">All Statuses</option>
                            <option value="online">Online Only</option>
                            <option value="offline">Offline Only</option>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Hostname / Device</th>
                                <th>IP Address</th>
                                <th>MAC Address</th>
                                <th>Source / Interface</th>
                                <th>Status</th>
                                <th>App Activity Today</th>
                                <th>Last Seen</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="devices-tbody">
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                                    Click "Sync MikroTik" to fetch connected devices!
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- TAB 3: App Analytics & Ranking -->
        <section id="tab-analytics" class="tab-content">
            <div class="analytics-grid">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            Top Accessed Applications (Today)
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="top-apps-container">
                            <div style="text-align:center; padding: 2rem; color: var(--text-muted);">Loading application stats...</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                            </svg>
                            Top Active Client IPs
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="top-devices-container">
                            <div style="text-align:center; padding: 2rem; color: var(--text-muted);">Loading device rankings...</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- TAB 4: Application Signatures & Rules -->
        <section id="tab-signatures" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                            </svg>
                            Application DNS Signatures
                        </div>
                        <div class="card-subtitle">
                            Domain matching patterns used by the Syslog Daemon to detect application access without DPI or packet inspection.
                        </div>
                    </div>
                    <button id="btn-open-sig-modal" class="btn btn-primary btn-sm">
                        + Add Application
                    </button>
                </div>

                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.25rem;">
                        @foreach ($appSignatures as $sig)
                            <div class="card sig-card" style="border: 1px solid rgba(255,255,255,0.06); padding: 1.25rem; background: rgba(15,23,42,0.5);">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                                    <div>
                                        <span class="app-badge" style="background: {{ $sig->badge_color }}22; color: {{ $sig->badge_color }}; border: 1px solid {{ $sig->badge_color }}44;">
                                            ● {{ $sig->app_name }}
                                        </span>
                                        <span style="font-size: 0.75rem; color: var(--text-muted); margin-left: 0.4rem;">{{ $sig->category }}</span>
                                    </div>
                                    <button class="btn btn-outline btn-sm btn-delete-sig" data-id="{{ $sig->id }}" data-name="{{ $sig->app_name }}" style="padding: 0.2rem 0.5rem; color: var(--accent-rose);">
                                        Delete
                                    </button>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.35rem; font-weight: 600;">Domain Patterns:</div>
                                <div style="font-family: var(--font-mono); font-size: 0.78rem; color: #93c5fd; background: rgba(0,0,0,0.3); padding: 0.5rem; border-radius: var(--radius-sm); max-height: 85px; overflow-y: auto;">
                                    {!! nl2br(e($sig->domain_pattern)) !!}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <!-- TAB 5: Safe Production Setup Guide -->
        <section id="tab-mikrotik-setup" class="tab-content">
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="card-header">
                    <div class="card-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                            <polyline points="2 17 12 22 22 17"></polyline>
                            <polyline points="2 12 12 17 22 12"></polyline>
                        </svg>
                        Kenapa Arsitektur Ini Aman Untuk MikroTik Produksi? (Zero CPU Spike)
                    </div>
                </div>
                <div class="card-body" style="font-size: 0.9rem; line-height: 1.7; color: var(--text-secondary);">
                    <p style="margin-bottom: 1rem;">
                        Pada router MikroTik yang menangani trafik produksi, menggunakan <strong>Layer 7 Protocol (L7) Regex</strong>, <strong>Mangle Connection Marking</strong>, atau <strong>Deep Packet Inspection (DPI)</strong> akan memaksa CPU router memeriksa setiap payload paket data. Hal ini sering mengakibatkan <strong>CPU Router 100%</strong> dan koneksi internet melambat.
                    </p>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-top: 1rem;">
                        <div style="background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.2); padding: 1rem; border-radius: var(--radius-md);">
                            <h4 style="color: var(--accent-emerald); font-weight: 700; margin-bottom: 0.35rem;">Metode 1: MikroTik API Polling</h4>
                            <p style="font-size: 0.82rem;">Hanya membaca tabel DHCP Lease &amp; ARP pada interval berkala. Tidak menyentuh firewall, tidak ada mangle rules, dan sangat ramah memori.</p>
                        </div>
                        <div style="background: rgba(6,182,212,0.08); border: 1px solid rgba(6,182,212,0.2); padding: 1rem; border-radius: var(--radius-md);">
                            <h4 style="color: var(--accent-cyan); font-weight: 700; margin-bottom: 0.35rem;">Metode 2: Remote DNS Syslog</h4>
                            <p style="font-size: 0.82rem;">MikroTik hanya melempar salinan log DNS via UDP socket ke server Laravel. Seluruh proses parsing teks, regex domain, dan pengelompokan aplikasi dikerjakan oleh Laravel daemon, bukan router!</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="4 17 10 11 4 5"></polyline>
                            <line x1="12" y1="19" x2="20" y2="19"></line>
                        </svg>
                        Script Konfigurasi MikroTik (Tinggal Copy-Paste ke Terminal RouterOS)
                    </div>
                </div>
                <div class="card-body">
                    <h4 style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">Langkah 1: Aktifkan Remote DNS Logging ke Server Laravel</h4>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                        Ganti <code>192.168.88.50</code> dengan IP komputer/server tempat Laravel NMS ini berjalan.
                    </p>
                    <div class="code-box" id="script-dns-log"># 1. Tambahkan target remote log UDP ke IP server Laravel
/system logging action add name=nms-remote target=remote remote=192.168.88.50 remote-port=514

# 2. Arahkan topic DNS ke action remote tersebut
/system logging add topics=dns,packet action=nms-remote</div>
                    <div style="margin-top: 0.5rem; margin-bottom: 1.5rem;">
                        <button class="btn btn-outline btn-sm" onclick="copyScript('script-dns-log')">Copy Script Langkah 1</button>
                    </div>

                    <h4 style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">Langkah 2: Buat User API MikroTik (Read-Only)</h4>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                        Demi keamanan, gunakan user khusus API dengan privilege read-only.
                    </p>
                    <div class="code-box" id="script-user-api"># Buat group khusus nms dengan izin read & api
/user group add name=nms-group policy=read,api,test,!local,!telnet,!ssh,!ftp,!reboot,!write,!policy,!password,!web,!sniff,!sensitive,!romon

# Buat user baru dengan password yang kuat
/user add name=nmsuser group=nms-group password="PasswordNms123!" comment="NMS Monitoring Agent"

# Pastikan API service aktif (port 8728)
/ip service enable api</div>
                    <div style="margin-top: 0.5rem; margin-bottom: 1.5rem;">
                        <button class="btn btn-outline btn-sm" onclick="copyScript('script-user-api')">Copy Script Langkah 2</button>
                    </div>

                    <h4 style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">Langkah 3: Jalankan Daemon Listener di Server Laravel</h4>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                        Jalankan Artisan command ini di terminal server Anda agar daemon mulai mendengarkan port UDP 514:
                    </p>
                    <div class="code-box" id="script-laravel-daemon">php artisan syslog:listen --port=514</div>
                    <div style="margin-top: 0.5rem;">
                        <button class="btn btn-outline btn-sm" onclick="copyScript('script-laravel-daemon')">Copy Command Daemon</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- TAB 6: Traffic Simulator Playground -->
        <section id="tab-simulator" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="5 3 19 12 5 21 5 3"></polygon>
                            </svg>
                            Live DNS Log Simulator &amp; Playground
                        </div>
                        <div class="card-subtitle">
                            Simulasikan log query DNS dari MikroTik secara langsung untuk menguji pencocokan aplikasi dan timeline tanpa harus menunggu paket riil dari router.
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form id="sim-form" style="max-width: 600px;">
                        <div class="form-group">
                            <label class="form-label">Client IP Address</label>
                            <input type="text" id="sim-ip" class="input-field" value="192.168.88.105" required style="width: 100%;">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Domain Query</label>
                            <input type="text" id="sim-domain" class="input-field" value="www.youtube.com" required style="width: 100%;">
                        </div>

                        <div style="margin-bottom: 1.25rem;">
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.5rem;">Quick Presets:</div>
                            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                                <button type="button" class="btn btn-outline btn-sm btn-sim-preset" data-domain="www.youtube.com">YouTube</button>
                                <button type="button" class="btn btn-outline btn-sm btn-sim-preset" data-domain="web.whatsapp.com">WhatsApp</button>
                                <button type="button" class="btn btn-outline btn-sm btn-sim-preset" data-domain="v16-webapp-prime.tiktok.com">TikTok</button>
                                <button type="button" class="btn btn-outline btn-sm btn-sim-preset" data-domain="graph.instagram.com">Instagram</button>
                                <button type="button" class="btn btn-outline btn-sm btn-sim-preset" data-domain="api-global.netflix.com">Netflix</button>
                                <button type="button" class="btn btn-outline btn-sm btn-sim-preset" data-domain="zoom.us">Zoom</button>
                                <button type="button" class="btn btn-outline btn-sm btn-sim-preset" data-domain="shopee.co.id">Shopee</button>
                                <button type="button" class="btn btn-outline btn-sm btn-sim-preset" data-domain="tokopedia.com">Tokopedia</button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            ⚡ Send Simulated DNS Query
                        </button>
                    </form>
                </div>
            </div>
        </section>

        <!-- TAB 7: Router & System Settings -->
        <section id="tab-settings" class="tab-content">
            <div class="card" style="max-width: 800px;">
                <div class="card-header">
                    <div>
                        <div class="card-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                            </svg>
                            MikroTik Router Connection Settings
                        </div>
                        <div class="card-subtitle">
                            Konfigurasikan alamat IP dan kredensial untuk mengakses API MikroTik.
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form id="settings-form">
                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label">MikroTik IP / Hostname</label>
                                <input type="text" id="setting-host" class="input-field" value="{{ $settings['mikrotik_host'] }}" required style="width: 100%;">
                            </div>
                            <div class="form-group">
                                <label class="form-label">API Port</label>
                                <input type="number" id="setting-port" class="input-field" value="{{ $settings['mikrotik_port'] }}" required style="width: 100%;">
                                <div class="form-help">Default: 8728 (Socket) atau 80/443 (REST)</div>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label">API Username</label>
                                <input type="text" id="setting-user" class="input-field" value="{{ $settings['mikrotik_user'] }}" required style="width: 100%;">
                            </div>
                            <div class="form-group">
                                <label class="form-label">API Password</label>
                                <input type="password" id="setting-pass" class="input-field" placeholder="Biarkan kosong jika tidak berubah" style="width: 100%;">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label">API Connection Mode</label>
                                <select id="setting-api-type" class="input-field" style="width: 100%;">
                                    <option value="routeros_api" {{ $settings['mikrotik_api_type'] === 'routeros_api' ? 'selected' : '' }}>Socket API (RouterOS v6 &amp; v7 - Port 8728)</option>
                                    <option value="rest" {{ $settings['mikrotik_api_type'] === 'rest' ? 'selected' : '' }}>REST API (RouterOS v7+ - Port 80/443)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Syslog UDP Listen Port</label>
                                <input type="number" id="setting-syslog-port" class="input-field" value="{{ $settings['syslog_port'] }}" required style="width: 100%;">
                                <div class="form-help">Default 514 (Syslog standar)</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.85rem;">
                                <input type="checkbox" id="setting-use-ssl" {{ $settings['mikrotik_use_ssl'] ? 'checked' : '' }}>
                                Enable SSL / TLS (for API-SSL port 8729 or HTTPS REST)
                            </label>
                        </div>

                        <div id="test-result-box" style="display: none; margin-bottom: 1.25rem;"></div>

                        <div style="display: flex; gap: 0.75rem; align-items: center;">
                            <button type="submit" class="btn btn-primary">
                                Simpan Pengaturan
                            </button>
                            <button type="button" id="btn-test-mikrotik" class="btn btn-outline">
                                Test Koneksi ke MikroTik
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <!-- TAB: Multi-Router Management -->
        <section id="tab-routers" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                                <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                                <line x1="6" y1="6" x2="6.01" y2="6"></line>
                                <line x1="6" y1="18" x2="6.01" y2="18"></line>
                            </svg>
                            MikroTik Multi-Router Management
                        </div>
                        <div class="card-subtitle">
                            Kelola banyak router MikroTik sekaligus. Sistem dapat memonitor dan menyinkronkan perangkat dari setiap router yang terdaftar.
                        </div>
                    </div>
                    <div class="toolbar">
                        <button id="btn-sync-all-routers" class="btn btn-outline btn-sm">
                            ⟳ Sync Semua Router
                        </button>
                        <button id="btn-open-router-modal" class="btn btn-primary btn-sm">
                            + Tambah Router Baru
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nama Router</th>
                                <th>IP Address</th>
                                <th>API Mode / Port</th>
                                <th>Username</th>
                                <th>Status</th>
                                <th>Perangkat Terdata</th>
                                <th>Terakhir Disinkronkan</th>
                                <th style="text-align: right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="routers-tbody">
                            @forelse ($routers as $r)
                                <tr>
                                    <td>
                                        <div style="font-weight: 700; color: #fff;">{{ $r->name }}</div>
                                    </td>
                                    <td>
                                        <span class="ip-pill">{{ $r->ip_address }}</span>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.78rem; text-transform: uppercase; color: var(--accent-cyan); font-weight: 600;">
                                            {{ $r->api_type === 'rest' ? 'REST' : 'Socket API' }} (Port {{ $r->port }})
                                        </span>
                                    </td>
                                    <td style="color: var(--text-secondary); font-family: var(--font-mono); font-size: 0.8rem;">
                                        {{ $r->username }}
                                    </td>
                                    <td>
                                        <span class="indicator-pill {{ $r->is_active ? 'online' : 'offline' }}">
                                            <span class="dot-sm"></span>
                                            {{ $r->is_active ? 'Active' : 'Disabled' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-family: var(--font-mono); font-weight: 700; color: var(--accent-indigo);">
                                            {{ $r->device_count }} devices
                                        </span>
                                    </td>
                                    <td style="font-size: 0.78rem; color: var(--text-muted);">
                                        {{ $r->last_sync_at ? $r->last_sync_at->diffForHumans() : 'Belum pernah' }}
                                    </td>
                                    <td style="text-align: right;">
                                        <button class="btn btn-outline btn-sm btn-sync-single-router" data-id="{{ $r->id }}" data-name="{{ $r->name }}">
                                            Sync
                                        </button>
                                        <button class="btn btn-outline btn-sm btn-edit-router" data-json="{{ json_encode($r) }}">
                                            Edit
                                        </button>
                                        <button class="btn btn-outline btn-sm btn-delete-router" data-id="{{ $r->id }}" data-name="{{ $r->name }}" style="color: var(--accent-rose);">
                                            Hapus
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                                        Belum ada router tambahan. Klik "+ Tambah Router Baru" untuk mendaftarkan MikroTik lainnya!
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    </main>

    <!-- Modal: Device Activities Detail -->
    <div id="device-modal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <div>
                    <h3 id="device-modal-title" style="font-size: 1.1rem; font-weight: 700; color: #fff;">Device Activities</h3>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Application access history &amp; usage patterns</div>
                </div>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body" id="device-modal-body">
                <!-- Content dynamically populated via JS -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline btn-modal-close">Close</button>
            </div>
        </div>
    </div>

    <!-- Modal: Add Application Signature -->
    <div id="sig-modal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="sig-modal-title" style="font-size: 1.1rem; font-weight: 700; color: #fff;">Add Application Signature</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form id="sig-form">
                <input type="hidden" id="sig-id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Application Name</label>
                        <input type="text" id="sig-name" class="input-field" placeholder="e.g. Disney+ Hotstar" required style="width: 100%;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select id="sig-category" class="input-field" style="width: 100%;">
                            <option value="Video Streaming">Video Streaming</option>
                            <option value="Instant Messaging">Instant Messaging</option>
                            <option value="Social Media">Social Media</option>
                            <option value="Gaming">Gaming</option>
                            <option value="E-Commerce">E-Commerce</option>
                            <option value="Conferencing">Conferencing</option>
                            <option value="Cloud & Productivity">Cloud & Productivity</option>
                            <option value="General">General</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Domain Patterns (Satu per baris atau dipisah koma)</label>
                        <textarea id="sig-domains" class="input-field" rows="4" placeholder="hotstar.com&#10;disneyplus.com" required style="width: 100%; font-family: var(--font-mono);"></textarea>
                        <div class="form-help">Pencocokan akan otomatis mencakup subdomain (misal: <code>youtube.com</code> cocok dengan <code>rr1.sn-xxx.youtube.com</code>).</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Badge Color (Hex)</label>
                        <input type="color" id="sig-color" class="input-field" value="#6366f1" style="width: 100%; height: 40px; padding: 4px; cursor: pointer;">
                    </div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.85rem;">
                            <input type="checkbox" id="sig-active" checked>
                            Active Signature
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline btn-modal-close">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Signature</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Add / Edit MikroTik Router -->
    <div id="router-modal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <div>
                    <h3 id="router-modal-title" style="font-size: 1.1rem; font-weight: 700; color: #fff;">Tambah MikroTik Router</h3>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Daftarkan perangkat MikroTik baru untuk monitoring multi-router</div>
                </div>
                <button class="modal-close">&times;</button>
            </div>
            <form id="router-form">
                <input type="hidden" id="router-id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Nama Router / Identitas</label>
                        <input type="text" id="router-name" class="input-field" placeholder="e.g. MikroTik Kantor Cabang, Router Lt 2" required style="width: 100%;">
                    </div>

                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">IP Address MikroTik</label>
                            <input type="text" id="router-ip" class="input-field" placeholder="192.168.10.1" required style="width: 100%;">
                        </div>
                        <div class="form-group">
                            <label class="form-label">API Port</label>
                            <input type="number" id="router-port" class="input-field" value="8728" required style="width: 100%;">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">API Username</label>
                            <input type="text" id="router-username" class="input-field" placeholder="seken / nmsuser" required style="width: 100%;">
                        </div>
                        <div class="form-group">
                            <label class="form-label">API Password</label>
                            <input type="password" id="router-password" class="input-field" placeholder="Biarkan kosong jika tidak berubah" style="width: 100%;">
                            <div class="form-help">Tersimpan dengan enkripsi AES-256</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tipe Koneksi API</label>
                        <select id="router-api-type" class="input-field" style="width: 100%;">
                            <option value="routeros_api">Socket API (RouterOS v6 &amp; v7 - Port Standar 8728)</option>
                            <option value="rest">REST API (RouterOS v7+ - Port Standar 80 / 443)</option>
                        </select>
                    </div>

                    <div style="display: flex; gap: 1.5rem; margin-top: 0.5rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.85rem;">
                            <input type="checkbox" id="router-use-ssl">
                            Enable SSL / TLS
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.85rem;">
                            <input type="checkbox" id="router-is-active" checked>
                            Status Aktif (Include in Sync &amp; Monitoring)
                        </label>
                    </div>

                    <div id="router-test-result-box" style="display: none; margin-top: 1rem;"></div>
                </div>
                <div class="modal-footer" style="display: flex; justify-content: space-between;">
                    <button type="button" id="btn-test-router-modal" class="btn btn-outline">
                        ⚡ Test Koneksi
                    </button>
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="button" class="btn btn-outline btn-modal-close">Batal</button>
                        <button type="submit" class="btn btn-primary" id="btn-save-router">Simpan Router</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notifications Container -->
    <div id="toast-container" class="toast-container"></div>

    <!-- NMS Scripts -->
    <script src="/js/nms.js"></script>
</body>
</html>
