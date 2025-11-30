<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop orphaned tables from deleted packages:
 * - Setup* tables (dead code referencing non-existent Ns\Setup namespace)
 * - Config* tables (netserva-config package removed)
 * - migration_jobs (part of dead Setup system)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Disable foreign key checks for SQLite
        DB::statement('PRAGMA foreign_keys = OFF;');
        // Setup tables (dead code)
        Schema::dropIfExists('setup_jobs');
        Schema::dropIfExists('setup_components');
        Schema::dropIfExists('setup_templates');
        Schema::dropIfExists('migration_jobs');

        // Config tables (netserva-config package removed)
        Schema::dropIfExists('config_deployments');
        Schema::dropIfExists('config_profiles');
        Schema::dropIfExists('config_templates');
        Schema::dropIfExists('config_variables');
        Schema::dropIfExists('database_credentials');
        Schema::dropIfExists('database_connections');
        Schema::dropIfExists('databases');
        Schema::dropIfExists('secret_access');
        Schema::dropIfExists('secrets');

        // Re-enable foreign key checks
        DB::statement('PRAGMA foreign_keys = ON;');
    }

    public function down(): void
    {
        // These tables are orphaned from deleted packages
        // No rollback - they should not be recreated
    }
};
