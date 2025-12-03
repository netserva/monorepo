<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop fleet_vservs table - unused model with design flaw
 *
 * FleetVServ was designed to track services per VHost, but services
 * actually run at the VNode (server) level. DNS/Mail/Web plugins
 * handle service configuration directly.
 *
 * Stats: 0 rows, no Filament resource, no commands
 */
return new class extends Migration
{
    public function up(): void
    {
        // Disable foreign key checks for SQLite
        DB::statement('PRAGMA foreign_keys = OFF;');

        Schema::dropIfExists('fleet_vservs');

        // Re-enable foreign key checks
        DB::statement('PRAGMA foreign_keys = ON;');
    }

    public function down(): void
    {
        // Table removed intentionally - design flaw
    }
};
