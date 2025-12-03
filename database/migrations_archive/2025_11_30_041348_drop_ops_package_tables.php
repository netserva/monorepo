<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop tables from removed netserva-ops package
 * (Enterprise monitoring/analytics/backup - unused, 0 rows in all tables)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Disable foreign key checks for SQLite
        DB::statement('PRAGMA foreign_keys = OFF;');

        // Ops package tables (all empty - enterprise bloat)
        Schema::dropIfExists('alert_rules');
        Schema::dropIfExists('analytics_alerts');
        Schema::dropIfExists('analytics_dashboards');
        Schema::dropIfExists('analytics_data_sources');
        Schema::dropIfExists('analytics_metrics');
        Schema::dropIfExists('analytics_visualizations');
        Schema::dropIfExists('automation_jobs');
        Schema::dropIfExists('automation_tasks');
        Schema::dropIfExists('backup_jobs');
        Schema::dropIfExists('backup_repositories');
        Schema::dropIfExists('backup_snapshots');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('monitoring_checks');
        Schema::dropIfExists('status_pages');

        // Re-enable foreign key checks
        DB::statement('PRAGMA foreign_keys = ON;');

        // Remove from installed_plugins
        DB::table('installed_plugins')
            ->where('name', 'netserva-ops')
            ->delete();
    }

    public function down(): void
    {
        // These tables are from a removed package
        // No rollback - they should not be recreated
    }
};
