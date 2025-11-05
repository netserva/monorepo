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
        // Note: VHost model uses 'vhosts' table name but migration creates 'virtual_hosts'
        if (Schema::hasTable('vhosts')) {
            Schema::table('vhosts', function (Blueprint $table) {
                $table->foreignId('server_id')->nullable()->after('id')->constrained('servers')->onDelete('cascade');
                $table->index('server_id');
            });
        } elseif (Schema::hasTable('virtual_hosts')) {
            Schema::table('virtual_hosts', function (Blueprint $table) {
                $table->foreignId('server_id')->nullable()->after('id')->constrained('servers')->onDelete('cascade');
                $table->index('server_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('vhosts')) {
            Schema::table('vhosts', function (Blueprint $table) {
                $table->dropForeign(['server_id']);
                $table->dropColumn('server_id');
            });
        } elseif (Schema::hasTable('virtual_hosts')) {
            Schema::table('virtual_hosts', function (Blueprint $table) {
                $table->dropForeign(['server_id']);
                $table->dropColumn('server_id');
            });
        }
    }
};
