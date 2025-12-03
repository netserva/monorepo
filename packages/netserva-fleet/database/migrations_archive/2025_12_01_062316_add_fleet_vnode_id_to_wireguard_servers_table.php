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
        Schema::table('wireguard_servers', function (Blueprint $table) {
            $table->foreignId('fleet_vnode_id')->nullable()->after('ssh_host_id')->constrained('fleet_vnodes')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wireguard_servers', function (Blueprint $table) {
            $table->dropForeign(['fleet_vnode_id']);
            $table->dropColumn('fleet_vnode_id');
        });
    }
};
