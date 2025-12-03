<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // First, ensure we have a default venue for existing data
        $defaultVenueId = DB::table('fleet_venues')->insertGetId([
            'name' => 'default',
            'slug' => 'default',
            'provider' => 'local',
            'location' => 'Default Location',
            'description' => 'Auto-created default venue for existing VSites',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('fleet_vsites', function (Blueprint $table) {
            // Add venue_id column as nullable first
            $table->unsignedBigInteger('venue_id')->after('id')->nullable();
        });

        // Update all existing VSites to use the default venue
        DB::table('fleet_vsites')->update(['venue_id' => $defaultVenueId]);

        Schema::table('fleet_vsites', function (Blueprint $table) {
            // Now make it non-nullable and add constraints
            $table->unsignedBigInteger('venue_id')->change();
            $table->foreign('venue_id')->references('id')->on('fleet_venues')->onDelete('cascade');

            // Add unique constraint for venue_id + name combination
            $table->unique(['venue_id', 'name'], 'fleet_vsites_venue_name_unique');

            // Add index for common queries
            $table->index(['venue_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('fleet_vsites', function (Blueprint $table) {
            // Drop indexes and constraints first
            $table->dropUnique('fleet_vsites_venue_name_unique');
            $table->dropIndex(['venue_id', 'is_active']);

            // Drop foreign key constraint and column
            $table->dropForeign(['venue_id']);
            $table->dropColumn('venue_id');
        });
    }
};
