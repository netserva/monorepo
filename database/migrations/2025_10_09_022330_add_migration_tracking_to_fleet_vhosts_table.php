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
        Schema::table('fleet_vhosts', function (Blueprint $table) {
            // Migration status tracking
            $table->enum('migration_status', [
                'native',      // Created with NetServa 3.0 (default for new vhosts)
                'discovered',  // Found on vnode via discovery, not yet imported
                'imported',    // Legacy data imported to vconfs table
                'validated',   // Passed compliance checks
                'migrated',    // Fully migrated to 3.0 format
                'failed',      // Migration encountered errors
            ])->default('native')->after('is_active');

            // Store original legacy config from /root/.vhosts/$VHOST
            $table->json('legacy_config')->nullable()->after('migration_status');

            // Store validation/migration issues found
            $table->json('migration_issues')->nullable()->after('legacy_config');

            // Timestamps for migration tracking
            $table->timestamp('discovered_at')->nullable()->after('migration_issues');
            $table->timestamp('migrated_at')->nullable()->after('discovered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fleet_vhosts', function (Blueprint $table) {
            $table->dropColumn([
                'migration_status',
                'legacy_config',
                'migration_issues',
                'discovered_at',
                'migrated_at',
            ]);
        });
    }
};
