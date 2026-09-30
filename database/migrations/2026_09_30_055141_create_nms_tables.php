<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Devices table
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->unique();
            $table->string('mac_address', 32)->nullable()->index();
            $table->string('hostname')->nullable();
            $table->string('comment')->nullable();
            $table->string('interface')->nullable();
            $table->string('status', 30)->default('bound'); // bound, dynamic, static, etc.
            $table->string('source', 20)->default('dhcp'); // dhcp, arp, both
            $table->boolean('is_online')->default(true);
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();
        });

        // Application signatures table
        Schema::create('app_signatures', function (Blueprint $table) {
            $table->id();
            $table->string('app_name')->index();
            $table->string('category')->default('General'); // Video, Messaging, Social, Gaming, etc.
            $table->text('domain_pattern'); // comma or newline separated domain patterns, e.g. "youtube.com, googlevideo.com"
            $table->string('badge_color', 20)->default('#3b82f6');
            $table->string('icon', 40)->default('globe');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // Activity logs table
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('ip_address', 45)->index();
            $table->string('hostname')->nullable();
            $table->string('app_name')->index();
            $table->string('category')->nullable()->index();
            $table->string('domain', 255)->index();
            $table->string('query_type', 10)->default('A');
            $table->text('raw_log')->nullable();
            $table->timestamp('logged_at')->index();
            $table->timestamps();

            // Composite indexes for fast timeline queries
            $table->index(['ip_address', 'logged_at']);
            $table->index(['app_name', 'logged_at']);
        });

        // Settings table
        Schema::create('nms_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nms_settings');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('app_signatures');
        Schema::dropIfExists('devices');
    }
};
