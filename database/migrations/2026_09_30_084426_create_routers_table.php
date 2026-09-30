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
        Schema::create('routers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('ip_address', 45)->unique();
            $table->unsignedInteger('port')->default(8728);
            $table->string('username', 100)->default('admin');
            $table->text('password')->nullable();
            $table->string('api_type', 30)->default('routeros_api');
            $table->boolean('use_ssl')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_sync_status', 50)->nullable();
            $table->unsignedInteger('device_count')->default(0);
            $table->timestamps();
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('router_id')->nullable()->after('id')->constrained('routers')->nullOnDelete();
            $table->string('router_name', 100)->nullable()->after('router_id');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('router_id')->nullable()->after('device_id')->constrained('routers')->nullOnDelete();
            $table->string('router_name', 100)->nullable()->after('router_id');
            $table->index(['router_id', 'logged_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['router_id', 'logged_at']);
            $table->dropConstrainedForeignId('router_id');
            $table->dropColumn('router_name');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('router_id');
            $table->dropColumn('router_name');
        });

        Schema::dropIfExists('routers');
    }
};
