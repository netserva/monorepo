<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add BinaryLane-specific fields to fleet_vnodes table
     *
     * These fields track the relationship between VNodes and BinaryLane servers,
     * enabling automatic sync and management through the BinaryLane API.
     */
    public function up(): void
    {
        Schema::table('fleet_vnodes', function (Blueprint $table) {
            // BinaryLane server ID for API operations
            $table->unsignedBigInteger('bl_server_id')->nullable()->after('ssh_host_id');

            // BinaryLane server configuration
            $table->string('bl_size_slug')->nullable()->after('bl_server_id');
            $table->string('bl_region')->nullable()->after('bl_size_slug');
            $table->string('bl_image')->nullable()->after('bl_region');

            // Last sync timestamp
            $table->timestamp('bl_synced_at')->nullable()->after('bl_image');

            // Index for efficient lookups
            $table->index('bl_server_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fleet_vnodes', function (Blueprint $table) {
            $table->dropIndex(['bl_server_id']);
            $table->dropColumn([
                'bl_server_id',
                'bl_size_slug',
                'bl_region',
                'bl_image',
                'bl_synced_at',
            ]);
        });
    }
};
