// Network Monitoring System (NMS) Frontend Controller
document.addEventListener('DOMContentLoaded', () => {
    let latestActivityId = 0;
    let liveFeedPaused = false;
    let statsInterval = null;
    let activityInterval = null;
    let currentDevicePage = 1;

    // Elements
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    const syncBtn = document.getElementById('btn-sync-mikrotik');
    const livePauseBtn = document.getElementById('btn-pause-live');
    const liveIndicator = document.getElementById('live-stream-status');
    const activityFeedTbody = document.getElementById('activity-feed-tbody');
    const devicesTbody = document.getElementById('devices-tbody');
    const deviceSearchInput = document.getElementById('device-search');
    const deviceStatusFilter = document.getElementById('device-status-filter');
    const activitySearchInput = document.getElementById('activity-search');
    const activityAppFilter = document.getElementById('activity-app-filter');

    // Tab Switching
    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.dataset.tab;
            tabButtons.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            const targetEl = document.getElementById(`tab-${target}`);
            if (targetEl) targetEl.classList.add('active');

            if (target === 'devices') {
                loadDevices();
            } else if (target === 'analytics') {
                loadStats();
            }
        });
    });

    // Toast Notification Utility
    window.showToast = function(message, type = 'info') {
        const container = document.getElementById('toast-container');
        if (!container) return;
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <span>${type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ'}</span>
            <div>${message}</div>
        `;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    };

    // Load Overall Stats
    async function loadStats() {
        try {
            const res = await fetch('/api/stats');
            const data = await res.json();

            // Update top cards
            document.getElementById('stat-online-devices').textContent = `${data.online_devices} / ${data.total_devices}`;
            document.getElementById('stat-activities-today').textContent = Number(data.activities_today).toLocaleString();
            document.getElementById('stat-unique-apps').textContent = data.unique_apps_today;

            // Daemon status pill
            const daemonBadge = document.getElementById('daemon-status-badge');
            const daemonDot = document.getElementById('daemon-pulse-dot');
            const daemonText = document.getElementById('daemon-status-text');
            const daemonPackets = document.getElementById('stat-daemon-packets');

            if (data.daemon.running) {
                daemonBadge.className = 'status-badge live';
                daemonDot.className = 'pulse-dot';
                daemonText.textContent = `Syslog UDP: Active (Port ${data.daemon.port})`;
                if (daemonPackets) {
                    daemonPackets.textContent = `${data.daemon.matched_total.toLocaleString()} matched (${data.daemon.packets_total.toLocaleString()} pkts)`;
                }
            } else {
                daemonBadge.className = 'status-badge offline';
                daemonDot.className = 'pulse-dot offline';
                daemonText.textContent = `Syslog Daemon: Offline (Port ${data.daemon.port})`;
                if (daemonPackets) {
                    daemonPackets.textContent = 'Waiting for php artisan syslog:listen';
                }
            }

            // Top Apps Widget
            renderTopApps(data.top_apps, data.activities_today);
            renderTopDevices(data.top_devices);

        } catch (e) {
            console.error('Failed to load stats:', e);
        }
    }

    // Render Top Apps Progress Bars
    function renderTopApps(apps, totalActivities) {
        const container = document.getElementById('top-apps-container');
        if (!container) return;

        if (!apps || apps.length === 0) {
            container.innerHTML = `<div style="color: var(--text-muted); text-align: center; padding: 2rem;">No application activities recorded yet today.</div>`;
            return;
        }

        const maxCount = Math.max(...apps.map(a => a.count), 1);
        container.innerHTML = apps.map(app => {
            const pct = Math.round((app.count / (totalActivities || 1)) * 100);
            const barWidth = Math.round((app.count / maxCount) * 100);
            return `
                <div class="app-stat-row">
                    <div style="width: 100%;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                            <span class="app-badge" style="background: ${app.color}22; color: ${app.color}; border: 1px solid ${app.color}44;">
                                ${escapeHtml(app.app_name)}
                            </span>
                            <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-primary); font-family: var(--font-mono);">
                                ${app.count.toLocaleString()} queries (${pct}%)
                            </span>
                        </div>
                        <div class="progress-bar-bg">
                            <div class="progress-bar-fill" style="width: ${barWidth}%; background-color: ${app.color};"></div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    // Render Top Active Client Devices
    function renderTopDevices(devices) {
        const container = document.getElementById('top-devices-container');
        if (!container) return;

        if (!devices || devices.length === 0) {
            container.innerHTML = `<div style="color: var(--text-muted); text-align: center; padding: 2rem;">No active devices yet.</div>`;
            return;
        }

        container.innerHTML = devices.map((d, index) => {
            return `
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid rgba(255,255,255,0.04);">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); width: 18px;">#${index + 1}</span>
                        <div>
                            <div style="font-weight: 600; font-size: 0.85rem; color: var(--text-primary);">${escapeHtml(d.hostname || 'Unknown Host')}</div>
                            <span class="ip-pill">${escapeHtml(d.ip_address)}</span>
                        </div>
                    </div>
                    <span style="font-family: var(--font-mono); font-size: 0.85rem; font-weight: 700; color: var(--accent-indigo);">
                        ${Number(d.count).toLocaleString()} req
                    </span>
                </div>
            `;
        }).join('');
    }

    // Live Activity Stream Polling with Smooth Debouncing & Deduplication
    async function pollLiveActivities() {
        if (liveFeedPaused) return;

        const hideSystem = document.getElementById('feed-hide-system')?.checked ? 1 : 0;
        const appFilter = document.getElementById('feed-app-filter')?.value || '';
        const routerFilter = document.getElementById('feed-router-filter')?.value || '';

        try {
            const url = `/api/activities?live=1&hide_system=${hideSystem}&app=${encodeURIComponent(appFilter)}&router_id=${encodeURIComponent(routerFilter)}`;
            const res = await fetch(url);
            const resJson = await res.json();
            const items = resJson.data || [];

            if (items.length > 0) {
                const emptyRow = document.getElementById('empty-activity-row');
                if (emptyRow) emptyRow.remove();

                // Process in reverse so oldest in page is placed first, newest on top
                const reversed = [...items].reverse();
                reversed.forEach(item => updateOrPrependActivityRow(item));

                // Prune excess rows beyond 50
                while (activityFeedTbody.children.length > 50) {
                    activityFeedTbody.removeChild(activityFeedTbody.lastElementChild);
                }
            }
        } catch (e) {
            console.error('Error polling live activities:', e);
        }
    }

    function updateOrPrependActivityRow(item) {
        const date = new Date(item.logged_at);
        const timeStr = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        const appColor = getAppColor(item.app_name);
        const hostDisplay = item.hostname || (item.device ? (item.device.hostname || item.device.comment) : null) || 'Unknown Device';
        const hitCount = item.hit_count || 1;

        // Check if an existing row for this log ID already exists in table
        const existingRow = activityFeedTbody.querySelector(`tr[data-id="${item.id}"]`);

        if (existingRow) {
            // Update in-place smoothly
            existingRow.querySelector('.time-cell').textContent = timeStr;
            existingRow.querySelector('.domain-cell').innerHTML = `<span class="domain-pill" title="${escapeHtml(item.domain)}">${escapeHtml(item.domain)}</span>`;
            
            // Update hit badge
            const badgeContainer = existingRow.querySelector('.app-badge-cell');
            let hitBadge = badgeContainer.querySelector('.hit-badge');
            if (hitCount > 1) {
                if (!hitBadge) {
                    hitBadge = document.createElement('span');
                    hitBadge.className = 'hit-badge';
                    hitBadge.style.cssText = 'background: rgba(250, 204, 21, 0.15); color: #facc15; font-size: 0.72rem; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 99px; margin-left: 0.35rem; border: 1px solid rgba(250, 204, 21, 0.3);';
                    badgeContainer.appendChild(hitBadge);
                }
                hitBadge.textContent = `x${hitCount} hits`;
            }

            // Flash row gently
            existingRow.style.backgroundColor = 'rgba(99, 102, 241, 0.12)';
            setTimeout(() => { existingRow.style.backgroundColor = ''; }, 1000);
            return;
        }

        // New row
        const tr = document.createElement('tr');
        tr.dataset.id = item.id;
        tr.style.transition = 'background-color 1s ease';

        const hitBadgeHtml = hitCount > 1 
            ? `<span class="hit-badge" style="background: rgba(250, 204, 21, 0.15); color: #facc15; font-size: 0.72rem; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 99px; margin-left: 0.35rem; border: 1px solid rgba(250, 204, 21, 0.3);">x${hitCount} hits</span>`
            : '';

        const routerDisplay = item.router_name || (item.router ? item.router.name : null);
        const routerBadgeHtml = routerDisplay 
            ? `<span style="font-size: 0.7rem; color: #94a3b8; background: rgba(255,255,255,0.06); padding: 0.12rem 0.35rem; border-radius: 4px; margin-left: 0.35rem; border: 1px solid rgba(255,255,255,0.08);">🌐 ${escapeHtml(routerDisplay)}</span>` 
            : '';

        tr.innerHTML = `
            <td class="time-cell" style="font-family: var(--font-mono); font-size: 0.78rem; color: var(--text-muted);">${timeStr}</td>
            <td>
                <div style="font-weight: 600; color: var(--text-primary); font-size: 0.85rem; display: flex; align-items: center; flex-wrap: wrap;">
                    ${escapeHtml(hostDisplay)}
                    ${routerBadgeHtml}
                </div>
                <span class="ip-pill">${escapeHtml(item.ip_address)}</span>
            </td>
            <td class="app-badge-cell">
                <span class="app-badge" style="background: ${appColor}22; color: ${appColor}; border: 1px solid ${appColor}44;">
                    ● ${escapeHtml(item.app_name)}
                </span>
                ${hitBadgeHtml}
                <span style="font-size: 0.72rem; color: var(--text-muted); margin-left: 0.35rem;">${escapeHtml(item.category || '')}</span>
            </td>
            <td class="domain-cell">
                <span class="domain-pill" title="${escapeHtml(item.domain)}">${escapeHtml(item.domain)}</span>
            </td>
            <td>
                <span style="font-family: var(--font-mono); font-size: 0.75rem; background: rgba(255,255,255,0.05); padding: 0.2rem 0.4rem; border-radius: 4px;">
                    ${escapeHtml(item.query_type || 'A')}
                </span>
            </td>
            <td style="text-align: right;">
                <button class="btn btn-outline btn-sm btn-inspect-device" data-ip="${escapeHtml(item.ip_address)}">
                    Inspect Device
                </button>
            </td>
        `;

        activityFeedTbody.prepend(tr);

        // Bind inspect device
        tr.querySelector('.btn-inspect-device').addEventListener('click', (e) => {
            const ip = e.currentTarget.dataset.ip;
            inspectDeviceByIp(ip);
        });
    }

    function getAppColor(appName) {
        const colors = {
            'YouTube': '#ef4444',
            'WhatsApp': '#22c55e',
            'TikTok': '#06b6d4',
            'Instagram': '#ec4899',
            'Facebook': '#3b82f6',
            'Netflix': '#dc2626',
            'Spotify': '#10b981',
            'Telegram': '#0ea5e9',
            'Twitter / X': '#64748b',
            'Shopee': '#f97316',
            'Tokopedia': '#16a34a',
            'Mobile Legends': '#8b5cf6',
            'Roblox': '#e11d48',
            'Zoom': '#2563eb',
            'Google Services': '#4285f4'
        };
        return colors[appName] || '#818cf8';
    }

    // Pause/Resume Live Feed
    if (livePauseBtn) {
        livePauseBtn.addEventListener('click', () => {
            liveFeedPaused = !liveFeedPaused;
            if (liveFeedPaused) {
                livePauseBtn.innerHTML = `<span>▶</span> Resume Feed`;
                liveIndicator.textContent = 'Paused';
                liveIndicator.style.color = 'var(--accent-amber)';
            } else {
                livePauseBtn.innerHTML = `<span>⏸</span> Pause Feed`;
                liveIndicator.textContent = 'Streaming Live';
                liveIndicator.style.color = 'var(--accent-emerald)';
                pollLiveActivities();
            }
        });
    }

    // Load Devices Table
    async function loadDevices(page = 1) {
        currentDevicePage = page;
        const search = deviceSearchInput ? deviceSearchInput.value : '';
        const status = deviceStatusFilter ? deviceStatusFilter.value : '';
        const routerFilter = document.getElementById('device-router-filter')?.value || '';

        try {
            devicesTbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding: 2rem; color: var(--text-muted);">Loading devices from database...</td></tr>`;
            const res = await fetch(`/api/devices?page=${page}&search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}&router_id=${encodeURIComponent(routerFilter)}`);
            const data = await res.json();
            const items = data.data || [];

            if (items.length === 0) {
                devicesTbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding: 2rem; color: var(--text-muted);">No devices found. Click "Sync MikroTik" to fetch connected devices!</td></tr>`;
                return;
            }

            devicesTbody.innerHTML = items.map(dev => {
                const isOnline = dev.is_online;
                const lastSeen = dev.last_seen_at ? new Date(dev.last_seen_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', month: 'short', day: 'numeric' }) : '-';
                const routerName = dev.router_name || (dev.router ? dev.router.name : null);
                const routerPill = routerName ? `<div style="font-size: 0.72rem; color: #38bdf8; margin-top: 0.2rem;">🌐 ${escapeHtml(routerName)}</div>` : '';

                return `
                    <tr>
                        <td>
                            <div style="font-weight: 700; color: var(--text-primary); font-size: 0.9rem;">
                                ${escapeHtml(dev.hostname || dev.comment || 'Unknown')}
                            </div>
                            ${dev.comment && dev.hostname ? `<div style="font-size: 0.75rem; color: var(--text-muted);">${escapeHtml(dev.comment)}</div>` : ''}
                            ${routerPill}
                        </td>
                        <td>
                            <span class="ip-pill">${escapeHtml(dev.ip_address)}</span>
                        </td>
                        <td>
                            <span class="mac-pill">${escapeHtml(dev.mac_address || 'N/A')}</span>
                        </td>
                        <td>
                            <span style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; color: var(--text-muted); background: rgba(255,255,255,0.04); padding: 0.2rem 0.5rem; border-radius: 4px;">
                                ${escapeHtml(dev.source)} ${dev.interface ? `(${escapeHtml(dev.interface)})` : ''}
                            </span>
                        </td>
                        <td>
                            <span class="indicator-pill ${isOnline ? 'online' : 'offline'}">
                                <span class="dot-sm"></span>
                                ${isOnline ? 'Online' : 'Offline'}
                            </span>
                        </td>
                        <td>
                            <span style="font-family: var(--font-mono); font-weight: 700; color: ${dev.today_activities_count > 0 ? 'var(--accent-indigo)' : 'var(--text-muted)'};">
                                ${dev.today_activities_count} events
                            </span>
                        </td>
                        <td style="font-size: 0.78rem; color: var(--text-muted);">
                            ${lastSeen}
                        </td>
                        <td style="text-align: right;">
                            <button class="btn btn-outline btn-sm btn-view-device-detail" data-id="${dev.id}">
                                Activities & History
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');

            // Bind detail buttons
            document.querySelectorAll('.btn-view-device-detail').forEach(btn => {
                btn.addEventListener('click', () => {
                    openDeviceDetailModal(btn.dataset.id);
                });
            });

        } catch (e) {
            console.error('Failed to load devices:', e);
            devicesTbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding: 2rem; color: var(--accent-rose);">Error loading devices.</td></tr>`;
        }
    }

    // Device Filter Listeners
    if (deviceSearchInput) {
        let debounceTimer;
        deviceSearchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => loadDevices(1), 300);
        });
    }

    if (deviceStatusFilter) {
        deviceStatusFilter.addEventListener('change', () => loadDevices(1));
    }

    // MikroTik Sync Button
    if (syncBtn) {
        syncBtn.addEventListener('click', async () => {
            const originalHtml = syncBtn.innerHTML;
            syncBtn.disabled = true;
            syncBtn.innerHTML = `<span class="spin">⟳</span> Syncing MikroTik...`;

            try {
                const res = await fetch('/api/mikrotik/sync', { method: 'POST', headers: { 'X-CSRF-TOKEN': getCsrfToken() } });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    loadDevices(1);
                    loadStats();
                } else {
                    showToast(data.message || 'Sync failed', 'error');
                }
            } catch (e) {
                showToast('Failed to trigger MikroTik sync: ' + e.message, 'error');
            } finally {
                syncBtn.disabled = false;
                syncBtn.innerHTML = originalHtml;
            }
        });
    }

    // Inspect Device Detail Modal
    async function openDeviceDetailModal(deviceId) {
        const modal = document.getElementById('device-modal');
        const modalBody = document.getElementById('device-modal-body');
        if (!modal || !modalBody) return;

        modal.classList.add('active');
        modalBody.innerHTML = `<div style="text-align: center; padding: 2rem; color: var(--text-muted);">Loading device activity history...</div>`;

        try {
            const res = await fetch(`/api/devices/${deviceId}`);
            const data = await res.json();
            const dev = data.device;
            const appUsage = data.app_usage || [];
            const recent = data.recent_activities || [];

            document.getElementById('device-modal-title').textContent = `${dev.hostname || dev.comment || 'Device'} (${dev.ip_address})`;

            modalBody.innerHTML = `
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; background: rgba(15,23,42,0.6); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-subtle);">
                    <div>
                        <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase;">IP Address</div>
                        <div style="font-family: var(--font-mono); font-weight: 600; color: #fff;">${escapeHtml(dev.ip_address)}</div>
                    </div>
                    <div>
                        <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase;">MAC Address</div>
                        <div style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-secondary);">${escapeHtml(dev.mac_address || 'N/A')}</div>
                    </div>
                    <div>
                        <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase;">Discovered Via</div>
                        <div style="font-weight: 600; text-transform: uppercase; font-size: 0.8rem; color: var(--accent-cyan);">${escapeHtml(dev.source)}</div>
                    </div>
                    <div>
                        <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase;">Status</div>
                        <div><span class="indicator-pill ${dev.is_online ? 'online' : 'offline'}"><span class="dot-sm"></span>${dev.is_online ? 'Online' : 'Offline'}</span></div>
                    </div>
                </div>

                <h4 style="font-size: 0.9rem; font-weight: 700; margin-bottom: 0.75rem; color: var(--text-primary);">Apps Accessed Today</h4>
                <div style="display: flex; flex-wrap: wrap; gap: 0.6rem; margin-bottom: 1.5rem;">
                    ${appUsage.length > 0 ? appUsage.map(app => `
                        <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-subtle); padding: 0.5rem 0.85rem; border-radius: var(--radius-md); display: flex; align-items: center; gap: 0.6rem;">
                            <span class="app-badge" style="background: ${getAppColor(app.app_name)}22; color: ${getAppColor(app.app_name)};">
                                ● ${escapeHtml(app.app_name)}
                            </span>
                            <span style="font-family: var(--font-mono); font-weight: 700; font-size: 0.82rem; color: #fff;">
                                ${app.count} req
                            </span>
                        </div>
                    `).join('') : '<div style="color: var(--text-muted); font-size: 0.82rem;">No specific application activity logged today for this device.</div>'}
                </div>

                <h4 style="font-size: 0.9rem; font-weight: 700; margin-bottom: 0.75rem; color: var(--text-primary);">Recent DNS Activity Timeline</h4>
                <div style="max-height: 240px; overflow-y: auto; border: 1px solid var(--border-subtle); border-radius: var(--radius-md);">
                    <table class="data-table" style="font-size: 0.8rem;">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>App</th>
                                <th>Domain Accessed</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${recent.length > 0 ? recent.map(r => `
                                <tr>
                                    <td style="font-family: var(--font-mono); color: var(--text-muted);">${new Date(r.logged_at).toLocaleTimeString()}</td>
                                    <td>
                                        <span class="app-badge" style="background: ${getAppColor(r.app_name)}22; color: ${getAppColor(r.app_name)};">
                                            ${escapeHtml(r.app_name)}
                                        </span>
                                    </td>
                                    <td><span class="domain-pill">${escapeHtml(r.domain)}</span></td>
                                </tr>
                            `).join('') : '<tr><td colspan="3" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">No recent queries.</td></tr>'}
                        </tbody>
                    </table>
                </div>
            `;
        } catch (e) {
            modalBody.innerHTML = `<div style="color: var(--accent-rose); padding: 1.5rem;">Error loading device detail: ${e.message}</div>`;
        }
    }

    async function inspectDeviceByIp(ip) {
        try {
            const res = await fetch(`/api/devices?search=${encodeURIComponent(ip)}`);
            const data = await res.json();
            const dev = (data.data && data.data[0]) ? data.data[0] : null;
            if (dev) {
                openDeviceDetailModal(dev.id);
            } else {
                showToast(`No registered device found for IP ${ip}`, 'info');
            }
        } catch (e) {
            console.error(e);
        }
    }

    // Modal Close
    document.querySelectorAll('.modal-close, .btn-modal-close').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
        });
    });

    // Test MikroTik Connection Button
    const btnTestMikrotik = document.getElementById('btn-test-mikrotik');
    if (btnTestMikrotik) {
        btnTestMikrotik.addEventListener('click', async () => {
            const originalHtml = btnTestMikrotik.innerHTML;
            btnTestMikrotik.disabled = true;
            btnTestMikrotik.innerHTML = `<span class="spin">⟳</span> Testing connection...`;

            const host = document.getElementById('setting-host').value;
            const port = document.getElementById('setting-port').value;
            const user = document.getElementById('setting-user').value;
            const pass = document.getElementById('setting-pass').value;
            const apiType = document.getElementById('setting-api-type').value;
            const useSsl = document.getElementById('setting-use-ssl').checked;

            try {
                const res = await fetch('/api/mikrotik/test', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                    body: JSON.stringify({ host, port, user, pass, api_type: apiType, use_ssl: useSsl })
                });
                const data = await res.json();

                const resultBox = document.getElementById('test-result-box');
                resultBox.style.display = 'block';
                if (data.success) {
                    resultBox.className = 'toast success';
                    resultBox.innerHTML = `<strong>✓ Success:</strong> ${data.message}`;
                    showToast('Connection verified successfully!', 'success');
                } else {
                    resultBox.className = 'toast error';
                    resultBox.innerHTML = `<strong>✕ Connection Failed:</strong> ${data.message}`;
                    showToast(data.message, 'error');
                }
            } catch (e) {
                showToast('Failed to test connection: ' + e.message, 'error');
            } finally {
                btnTestMikrotik.disabled = false;
                btnTestMikrotik.innerHTML = originalHtml;
            }
        });
    }

    // Save Settings Form
    const settingsForm = document.getElementById('settings-form');
    if (settingsForm) {
        settingsForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = settingsForm.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<span class="spin">⟳</span> Saving...`;

            const formData = {
                mikrotik_host: document.getElementById('setting-host').value,
                mikrotik_port: document.getElementById('setting-port').value,
                mikrotik_user: document.getElementById('setting-user').value,
                mikrotik_pass: document.getElementById('setting-pass').value,
                mikrotik_api_type: document.getElementById('setting-api-type').value,
                mikrotik_use_ssl: document.getElementById('setting-use-ssl').checked,
                syslog_port: document.getElementById('setting-syslog-port').value,
            };

            try {
                const res = await fetch('/api/settings', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                    body: JSON.stringify(formData)
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message || 'Failed to save settings', 'error');
                }
            } catch (err) {
                showToast('Save error: ' + err.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        });
    }

    // DNS Simulation Playground
    const simForm = document.getElementById('sim-form');
    if (simForm) {
        simForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const ip = document.getElementById('sim-ip').value;
            const domain = document.getElementById('sim-domain').value;

            try {
                const res = await fetch('/api/simulate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                    body: JSON.stringify({ ip, domain })
                });
                const data = await res.json();

                if (data.success) {
                    const parsed = data.parsed;
                    showToast(`Simulated query for ${parsed.app || 'Unmatched'} (${domain})!`, 'success');
                    pollLiveActivities();
                    loadStats();
                }
            } catch (err) {
                showToast('Simulation error: ' + err.message, 'error');
            }
        });

        // Preset Quick Buttons
        document.querySelectorAll('.btn-sim-preset').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('sim-domain').value = btn.dataset.domain;
                simForm.dispatchEvent(new Event('submit'));
            });
        });
    }

    // Add Signature Modal & Form
    const btnOpenSigModal = document.getElementById('btn-open-sig-modal');
    const sigModal = document.getElementById('sig-modal');
    const sigForm = document.getElementById('sig-form');

    if (btnOpenSigModal && sigModal) {
        btnOpenSigModal.addEventListener('click', () => {
            sigForm.reset();
            document.getElementById('sig-id').value = '';
            document.getElementById('sig-modal-title').textContent = 'Add Application Signature';
            sigModal.classList.add('active');
        });
    }

    if (sigForm) {
        sigForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                id: document.getElementById('sig-id').value || null,
                app_name: document.getElementById('sig-name').value,
                category: document.getElementById('sig-category').value,
                domain_pattern: document.getElementById('sig-domains').value,
                badge_color: document.getElementById('sig-color').value,
                is_active: document.getElementById('sig-active').checked,
            };

            try {
                const res = await fetch('/api/signatures', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    sigModal.classList.remove('active');
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    showToast('Failed to save signature', 'error');
                }
            } catch (err) {
                showToast(err.message, 'error');
            }
        });
    }

    // Delete Signature
    document.querySelectorAll('.btn-delete-sig').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm(`Delete application signature for "${btn.dataset.name}"?`)) return;

            try {
                const res = await fetch(`/api/signatures/${btn.dataset.id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': getCsrfToken() }
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    btn.closest('.sig-card').remove();
                }
            } catch (e) {
                showToast('Failed to delete signature: ' + e.message, 'error');
            }
        });
    });

    // Copy to clipboard helper
    window.copyScript = function(elementId) {
        const text = document.getElementById(elementId).innerText;
        navigator.clipboard.writeText(text).then(() => {
            showToast('MikroTik script copied to clipboard!', 'success');
        });
    };

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    // Speed and Filter Listeners for Live Stream
    let feedSpeedMs = 7000;
    const speedSelect = document.getElementById('feed-speed-select');
    const hideSystemCheck = document.getElementById('feed-hide-system');
    const appFilterSelect = document.getElementById('feed-app-filter');

    function restartActivityTimer() {
        if (activityInterval) clearInterval(activityInterval);
        activityInterval = setInterval(pollLiveActivities, feedSpeedMs);
    }

    if (speedSelect) {
        speedSelect.addEventListener('change', () => {
            feedSpeedMs = parseInt(speedSelect.value, 10);
            restartActivityTimer();
            showToast(`Feed speed changed to ${speedSelect.options[speedSelect.selectedIndex].text}`, 'info');
        });
    }

    if (hideSystemCheck) {
        hideSystemCheck.addEventListener('change', () => {
            activityFeedTbody.innerHTML = '';
            pollLiveActivities();
        });
    }

    if (appFilterSelect) {
        appFilterSelect.addEventListener('change', () => {
            activityFeedTbody.innerHTML = '';
            pollLiveActivities();
        });
    }

    const feedRouterFilter = document.getElementById('feed-router-filter');
    if (feedRouterFilter) {
        feedRouterFilter.addEventListener('change', () => {
            activityFeedTbody.innerHTML = '';
            pollLiveActivities();
        });
    }

    const deviceRouterFilter = document.getElementById('device-router-filter');
    if (deviceRouterFilter) {
        deviceRouterFilter.addEventListener('change', () => {
            loadDevices(1);
        });
    }

    // Multi-Router Management Logic
    const btnOpenRouterModal = document.getElementById('btn-open-router-modal');
    const routerModal = document.getElementById('router-modal');
    const routerForm = document.getElementById('router-form');
    const routerTestResultBox = document.getElementById('router-test-result-box');
    const btnTestRouterModal = document.getElementById('btn-test-router-modal');
    const btnSyncAllRouters = document.getElementById('btn-sync-all-routers');

    if (btnOpenRouterModal && routerModal) {
        btnOpenRouterModal.addEventListener('click', () => {
            routerForm.reset();
            document.getElementById('router-id').value = '';
            document.getElementById('router-modal-title').textContent = 'Tambah MikroTik Router';
            document.getElementById('router-port').value = 8728;
            document.getElementById('router-is-active').checked = true;
            if (routerTestResultBox) routerTestResultBox.style.display = 'none';
            routerModal.classList.add('active');
        });
    }

    // Edit Router Button Handler (delegated on tbody or directly)
    document.querySelectorAll('.btn-edit-router').forEach(btn => {
        btn.addEventListener('click', () => {
            try {
                const data = JSON.parse(btn.dataset.json);
                document.getElementById('router-id').value = data.id || '';
                document.getElementById('router-name').value = data.name || '';
                document.getElementById('router-ip').value = data.ip_address || '';
                document.getElementById('router-port').value = data.port || 8728;
                document.getElementById('router-username').value = data.username || '';
                document.getElementById('router-password').value = '';
                document.getElementById('router-api-type').value = data.api_type || 'routeros_api';
                document.getElementById('router-use-ssl').checked = !!data.use_ssl;
                document.getElementById('router-is-active').checked = !!data.is_active;

                document.getElementById('router-modal-title').textContent = `Edit Router: ${data.name}`;
                if (routerTestResultBox) routerTestResultBox.style.display = 'none';
                routerModal.classList.add('active');
            } catch (err) {
                console.error('Failed to parse router data:', err);
            }
        });
    });

    // Save Router Form Submission
    if (routerForm) {
        routerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btnSave = document.getElementById('btn-save-router');
            const originalText = btnSave.textContent;
            btnSave.disabled = true;
            btnSave.textContent = 'Menyimpan...';

            const payload = {
                id: document.getElementById('router-id').value || null,
                name: document.getElementById('router-name').value,
                ip_address: document.getElementById('router-ip').value,
                port: document.getElementById('router-port').value,
                username: document.getElementById('router-username').value,
                password: document.getElementById('router-password').value || null,
                api_type: document.getElementById('router-api-type').value,
                use_ssl: document.getElementById('router-use-ssl').checked,
                is_active: document.getElementById('router-is-active').checked,
            };

            try {
                const res = await fetch('/api/routers', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    routerModal.classList.remove('active');
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    showToast(data.message || 'Gagal menyimpan router', 'error');
                }
            } catch (err) {
                showToast('Error: ' + err.message, 'error');
            } finally {
                btnSave.disabled = false;
                btnSave.textContent = originalText;
            }
        });
    }

    // Test Router in Modal
    if (btnTestRouterModal) {
        btnTestRouterModal.addEventListener('click', async () => {
            const originalText = btnTestRouterModal.textContent;
            btnTestRouterModal.disabled = true;
            btnTestRouterModal.textContent = 'Testing...';

            const payload = {
                id: document.getElementById('router-id').value || null,
                ip_address: document.getElementById('router-ip').value,
                port: document.getElementById('router-port').value,
                username: document.getElementById('router-username').value,
                password: document.getElementById('router-password').value || null,
                api_type: document.getElementById('router-api-type').value,
                use_ssl: document.getElementById('router-use-ssl').checked,
            };

            try {
                const res = await fetch('/api/routers/test', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                routerTestResultBox.style.display = 'block';

                if (data.success) {
                    routerTestResultBox.className = 'status-badge live';
                    routerTestResultBox.style.cssText = 'display: block; padding: 0.75rem; border-radius: 6px; background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.3); color: #34d399; font-size: 0.85rem;';
                    routerTestResultBox.innerHTML = `✓ ${escapeHtml(data.message)} (${data.details?.response_time_ms || 0}ms)`;
                } else {
                    routerTestResultBox.className = 'status-badge offline';
                    routerTestResultBox.style.cssText = 'display: block; padding: 0.75rem; border-radius: 6px; background: rgba(244,63,94,0.15); border: 1px solid rgba(244,63,94,0.3); color: #fb7185; font-size: 0.85rem;';
                    routerTestResultBox.innerHTML = `✕ ${escapeHtml(data.message)}`;
                }
            } catch (err) {
                routerTestResultBox.style.display = 'block';
                routerTestResultBox.style.cssText = 'display: block; padding: 0.75rem; border-radius: 6px; background: rgba(244,63,94,0.15); border: 1px solid rgba(244,63,94,0.3); color: #fb7185; font-size: 0.85rem;';
                routerTestResultBox.innerHTML = `✕ Connection test error: ${escapeHtml(err.message)}`;
            } finally {
                btnTestRouterModal.disabled = false;
                btnTestRouterModal.textContent = originalText;
            }
        });
    }

    // Sync Single Router Button
    document.querySelectorAll('.btn-sync-single-router').forEach(btn => {
        btn.addEventListener('click', async () => {
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.innerHTML = `<span class="spin">⟳</span> Sync...`;

            try {
                const res = await fetch(`/api/routers/${btn.dataset.id}/sync`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': getCsrfToken() }
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    loadDevices(currentDevicePage);
                    loadStats();
                } else {
                    showToast(data.message || 'Sync failed', 'error');
                }
            } catch (err) {
                showToast('Sync error: ' + err.message, 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        });
    });

    // Sync All Active Routers Button
    if (btnSyncAllRouters) {
        btnSyncAllRouters.addEventListener('click', async () => {
            const originalText = btnSyncAllRouters.textContent;
            btnSyncAllRouters.disabled = true;
            btnSyncAllRouters.innerHTML = `<span class="spin">⟳</span> Syncing All Routers...`;

            try {
                const res = await fetch('/api/routers/sync-all', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': getCsrfToken() }
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    loadDevices(1);
                    loadStats();
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast(data.message || 'Multi-router sync failed', 'error');
                }
            } catch (err) {
                showToast('Sync error: ' + err.message, 'error');
            } finally {
                btnSyncAllRouters.disabled = false;
                btnSyncAllRouters.textContent = originalText;
            }
        });
    }

    // Delete Router Button
    document.querySelectorAll('.btn-delete-router').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm(`Hapus router "${btn.dataset.name}" dari sistem monitoring?`)) return;

            try {
                const res = await fetch(`/api/routers/${btn.dataset.id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': getCsrfToken() }
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    btn.closest('tr').remove();
                } else {
                    showToast(data.message || 'Gagal menghapus router', 'error');
                }
            } catch (err) {
                showToast('Delete error: ' + err.message, 'error');
            }
        });
    });

    // Initialize Polling
    loadStats();
    pollLiveActivities();
    loadDevices(1);

    statsInterval = setInterval(loadStats, 6000);
    restartActivityTimer();
});
