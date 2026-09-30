<?php

namespace Tests\Feature;

use App\Models\AppSignature;
use App\Models\Device;
use App\Services\DnsLogParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DnsLogParserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AppSignature::create([
            'app_name' => 'YouTube',
            'category' => 'Video Streaming',
            'domain_pattern' => "youtube.com\ngooglevideo.com\nytimg.com",
            'badge_color' => '#ef4444',
            'is_active' => true,
        ]);

        AppSignature::create([
            'app_name' => 'WhatsApp',
            'category' => 'Instant Messaging',
            'domain_pattern' => "whatsapp.com\nwhatsapp.net",
            'badge_color' => '#22c55e',
            'is_active' => true,
        ]);
    }

    public function test_it_parses_mikrotik_dns_query_format_and_identifies_youtube(): void
    {
        $parser = new DnsLogParser;
        $parser->loadSignatures();

        $logLine = '<13>sep/30 12:45:00 dns,packet dns: query from 192.168.88.100:54321: #1234 A www.youtube.com';
        $result = $parser->parse($logLine);

        $this->assertEquals('192.168.88.100', $result['ip']);
        $this->assertEquals('www.youtube.com', $result['domain']);
        $this->assertTrue($result['matched']);
        $this->assertEquals('YouTube', $result['app']);
    }

    public function test_it_parses_whatsapp_query_and_records_activity(): void
    {
        $parser = new DnsLogParser;
        $parser->loadSignatures();

        // Create initial device
        Device::create([
            'ip_address' => '192.168.88.102',
            'hostname' => 'Budi-iPhone',
            'is_online' => true,
        ]);

        $logLine = 'dns: 192.168.88.102:51234 question: web.whatsapp.net A';
        $parsed = $parser->parse($logLine);

        $this->assertEquals('192.168.88.102', $parsed['ip']);
        $this->assertEquals('web.whatsapp.net', $parsed['domain']);
        $this->assertEquals('WhatsApp', $parsed['app']);

        $activity = $parser->recordActivity($parsed);

        $this->assertNotNull($activity);
        $this->assertEquals('WhatsApp', $activity->app_name);
        $this->assertEquals('192.168.88.102', $activity->ip_address);
        $this->assertEquals('Budi-iPhone', $activity->hostname);

        $this->assertDatabaseHas('activity_logs', [
            'ip_address' => '192.168.88.102',
            'app_name' => 'WhatsApp',
            'domain' => 'web.whatsapp.net',
        ]);
    }

    public function test_it_ignores_non_dns_syslog_messages(): void
    {
        $parser = new DnsLogParser;
        $parser->loadSignatures();

        $logLine = '<13>sep/30 12:45:00 system,info user admin logged in from 192.168.88.50 via winbox';
        $result = $parser->parse($logLine);

        $this->assertFalse($result['matched']);
        $this->assertNull($result['ip']);
        $this->assertNull($result['domain']);
    }
}
