<?php

namespace Database\Seeders;

use App\Models\AppSignature;
use App\Models\NmsSetting;
use Illuminate\Database\Seeder;

class AppSignatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $signatures = [
            [
                'app_name' => 'YouTube',
                'category' => 'Video Streaming',
                'domain_pattern' => "youtube.com\ngooglevideo.com\nytimg.com\nyoutu.be\nyoutubei.googleapis.com",
                'badge_color' => '#ef4444',
                'icon' => 'play-circle',
                'is_active' => true,
            ],
            [
                'app_name' => 'WhatsApp',
                'category' => 'Instant Messaging',
                'domain_pattern' => "whatsapp.com\nwhatsapp.net\nwa.me\nwhatsapp-cdn.net\nmmg.whatsapp.net",
                'badge_color' => '#22c55e',
                'icon' => 'message-circle',
                'is_active' => true,
            ],
            [
                'app_name' => 'TikTok',
                'category' => 'Social Video',
                'domain_pattern' => "tiktok.com\ntiktokcdn.com\ntiktokv.com\nbyteoversea.com\nibyteimg.com\nmusical.ly",
                'badge_color' => '#06b6d4',
                'icon' => 'video',
                'is_active' => true,
            ],
            [
                'app_name' => 'Instagram',
                'category' => 'Social Media',
                'domain_pattern' => "instagram.com\ncdninstagram.com\nig.me",
                'badge_color' => '#ec4899',
                'icon' => 'camera',
                'is_active' => true,
            ],
            [
                'app_name' => 'Facebook',
                'category' => 'Social Media',
                'domain_pattern' => "facebook.com\nfbcdn.net\nmeta.com\nmessenger.com\nfbsbx.com",
                'badge_color' => '#3b82f6',
                'icon' => 'users',
                'is_active' => true,
            ],
            [
                'app_name' => 'Netflix',
                'category' => 'Video Streaming',
                'domain_pattern' => "netflix.com\nnflxvideo.net\nnflximg.net\nnflxext.com\nnflxso.net",
                'badge_color' => '#dc2626',
                'icon' => 'film',
                'is_active' => true,
            ],
            [
                'app_name' => 'Spotify',
                'category' => 'Audio Streaming',
                'domain_pattern' => "spotify.com\nscdn.co\nspoti.fi\npscdn.co",
                'badge_color' => '#10b981',
                'icon' => 'music',
                'is_active' => true,
            ],
            [
                'app_name' => 'Telegram',
                'category' => 'Instant Messaging',
                'domain_pattern' => "telegram.org\nt.me\ntelesco.pe\ntelegra.ph",
                'badge_color' => '#0ea5e9',
                'icon' => 'send',
                'is_active' => true,
            ],
            [
                'app_name' => 'Twitter / X',
                'category' => 'Social Media',
                'domain_pattern' => "x.com\ntwitter.com\ntwimg.com\nt.co",
                'badge_color' => '#475569',
                'icon' => 'hash',
                'is_active' => true,
            ],
            [
                'app_name' => 'Shopee',
                'category' => 'E-Commerce',
                'domain_pattern' => "shopee.co.id\nshopee.com\nshopeemobile.com\ns-msn.com",
                'badge_color' => '#f97316',
                'icon' => 'shopping-bag',
                'is_active' => true,
            ],
            [
                'app_name' => 'Tokopedia',
                'category' => 'E-Commerce',
                'domain_pattern' => "tokopedia.com\ntokopedia.net\ntokopedia-upload.akamaized.net",
                'badge_color' => '#16a34a',
                'icon' => 'shopping-cart',
                'is_active' => true,
            ],
            [
                'app_name' => 'Mobile Legends',
                'category' => 'Gaming',
                'domain_pattern' => "mobilelegends.com\nmoonton.com\nmlbb.com",
                'badge_color' => '#8b5cf6',
                'icon' => 'crosshair',
                'is_active' => true,
            ],
            [
                'app_name' => 'Roblox',
                'category' => 'Gaming',
                'domain_pattern' => "roblox.com\nrbxcdn.com",
                'badge_color' => '#e11d48',
                'icon' => 'box',
                'is_active' => true,
            ],
            [
                'app_name' => 'Zoom',
                'category' => 'Conferencing',
                'domain_pattern' => "zoom.us\nzoomgov.com",
                'badge_color' => '#2563eb',
                'icon' => 'video',
                'is_active' => true,
            ],
            [
                'app_name' => 'Google Services',
                'category' => 'Cloud & Search',
                'domain_pattern' => "google.com\ngoogleapis.com\ngstatic.com\ngoogledrive.com\ngmail.com",
                'badge_color' => '#4285f4',
                'icon' => 'search',
                'is_active' => true,
            ],
        ];

        foreach ($signatures as $sig) {
            AppSignature::query()->updateOrCreate(
                ['app_name' => $sig['app_name']],
                $sig
            );
        }

        // Initialize default settings if not exists
        NmsSetting::query()->firstOrCreate(['key' => 'mikrotik_host'], ['value' => '192.168.88.1']);
        NmsSetting::query()->firstOrCreate(['key' => 'mikrotik_port'], ['value' => '8728']);
        NmsSetting::query()->firstOrCreate(['key' => 'mikrotik_user'], ['value' => 'admin']);
        NmsSetting::query()->firstOrCreate(['key' => 'mikrotik_pass'], ['value' => '']);
        NmsSetting::query()->firstOrCreate(['key' => 'mikrotik_api_type'], ['value' => 'routeros_api']);
        NmsSetting::query()->firstOrCreate(['key' => 'mikrotik_use_ssl'], ['value' => '0']);
        NmsSetting::query()->firstOrCreate(['key' => 'syslog_port'], ['value' => '514']);
    }
}
