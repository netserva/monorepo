<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop duplicate and orphaned tables from codebase cleanup
 *
 * VHost duplicates: fleet_vhosts is the canonical table (15 rows)
 * Theme duplicates: cms_themes is the canonical table (3 rows)
 * Orphaned: from removed Config package and legacy migrations
 */
return new class extends Migration
{
    public function up(): void
    {
        // Disable foreign key checks for SQLite
        DB::statement('PRAGMA foreign_keys = OFF;');

        // VHost duplicates (fleet_vhosts is canonical with 15 rows)
        Schema::dropIfExists('vhosts');
        Schema::dropIfExists('virtual_hosts');
        Schema::dropIfExists('vhost_configurations');

        // Theme duplicates (cms_themes is canonical with 3 rows)
        Schema::dropIfExists('themes');
        Schema::dropIfExists('theme_settings');

        // Orphaned from removed Config package
        Schema::dropIfExists('secret_accesses');
        Schema::dropIfExists('secret_categories');

        // Legacy/orphaned tables
        Schema::dropIfExists('servers');
        Schema::dropIfExists('platform_profiles');
        Schema::dropIfExists('glue_records');
        Schema::dropIfExists('domain_additional_fields');
        Schema::dropIfExists('domain_metadata');

        // Re-enable foreign key checks
        DB::statement('PRAGMA foreign_keys = ON;');
    }

    public function down(): void
    {
        // These tables are duplicates/orphans - no rollback needed
    }
};
