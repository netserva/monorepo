<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Phase 4: Migration Execution
     */
    public function up(): void
    {
        Schema::table('fleet_vhosts', function (Blueprint $table) {
            // Phase 4: Migration execution tracking
            $table->string('migration_backup_path')->nullable()->after('migrated_at')
                ->comment('Path to .archive backup for rollback');

            $table->boolean('rollback_available')->default(false)->after('migration_backup_path')
                ->comment('Can this vhost be rolled back?');

            $table->integer('migration_attempts')->default(0)->after('rollback_available')
                ->comment('Number of migration attempts (for retry tracking)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fleet_vhosts', function (Blueprint $table) {
            $table->dropColumn([
                'migration_backup_path',
                'rollback_available',
                'migration_attempts',
            ]);
        });
    }
};
