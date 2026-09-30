<?php

/**
 * Test Syslog UDP Listener (Standalone - 1 File Saja)
 * Jalankan di terminal: php test_syslog_local.php
 */
$host = '0.0.0.0';
$port = 514;

echo "=======================================================\n";
echo " MIKROTIK REMOTE DNS TESTER (100% LOKAL DI LAPTOP ANDA)\n";
echo "=======================================================\n";
echo "Mendengarkan di UDP port: {$port}...\n";
echo "Tekan Ctrl+C untuk berhenti.\n\n";

if (! extension_loaded('sockets')) {
    exit("Error: PHP sockets extension belum aktif di php.ini\n");
}

$socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if (! $socket) {
    exit('Gagal membuat UDP socket: '.socket_strerror(socket_last_error())."\n");
}

if (! @socket_bind($socket, $host, $port)) {
    $err = socket_strerror(socket_last_error($socket));
    echo "Peringatan: Gagal bind ke port {$port} ({$err}).\n";
    echo "Jika di Windows membutuhkan Administrator, coba port 1514:\n";
    echo "-> php test_syslog_local.php 1514\n";
    exit(1);
}

// Pola aplikasi sederhana
$apps = [
    'YouTube' => ['youtube.com', 'googlevideo.com', 'ytimg.com'],
    'WhatsApp' => ['whatsapp.com', 'whatsapp.net', 'wa.me'],
    'TikTok' => ['tiktok.com', 'tiktokcdn.com', 'byteoversea.com'],
    'Instagram' => ['instagram.com', 'cdninstagram.com'],
    'Facebook' => ['facebook.com', 'fbcdn.net'],
    'Netflix' => ['netflix.com', 'nflxvideo.net'],
];

while (true) {
    $buffer = '';
    $fromIp = '';
    $fromPort = 0;

    $bytes = @socket_recvfrom($socket, $buffer, 4096, 0, $fromIp, $fromPort);
    if ($bytes > 0 && trim($buffer) !== '') {
        $timestamp = date('H:i:s');

        // Cari IP client dan domain dari log MikroTik
        $clientIp = $fromIp;
        $domain = null;
        $matchedApp = null;

        // Ekstrak IP jika ada di teks query
        if (preg_match('/query\s+from\s+([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/i', $buffer, $m)) {
            $clientIp = $m[1];
        }

        // Ekstrak domain
        if (preg_match('/([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $buffer, $mDomain)) {
            $domain = strtolower($mDomain[1]);
        }

        if ($domain) {
            foreach ($apps as $app => $patterns) {
                foreach ($patterns as $pattern) {
                    if ($domain === $pattern || str_ends_with($domain, '.'.$pattern)) {
                        $matchedApp = $app;
                        break 2;
                    }
                }
            }
        }

        if ($matchedApp) {
            echo "[{$timestamp}] TERDETEKSI APLIKASI: [{$matchedApp}] oleh IP {$clientIp} -> domain: {$domain}\n";
        } else {
            echo "[{$timestamp}] DNS Query biasa dari {$clientIp} -> ".($domain ?: $buffer)."\n";
        }
    }
}
