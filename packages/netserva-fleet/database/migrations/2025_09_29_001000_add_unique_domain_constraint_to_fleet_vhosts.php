<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_vhosts', function (Blueprint $table) {
            // First, remove the existing unique constraint on vnode_id + domain
            $table->dropUnique('fleet_vhosts_vnode_domain_unique');

            // Add global unique constraint on domain
            // This ensures domains are unique across the entire system
            $table->unique('domain', 'fleet_vhosts_domain_unique');

            // Keep the composite index for performance but not unique
            $table->index(['vnode_id', 'domain'], 'fleet_vhosts_vnode_domain_index');
        });
    }

    public function down(): void
    {
        Schema::table('fleet_vhosts', function (Blueprint $table) {
            // Remove the global unique constraint
            $table->dropUnique('fleet_vhosts_domain_unique');

            // Remove the composite index
            $table->dropIndex('fleet_vhosts_vnode_domain_index');

            // Restore the original unique constraint
            $table->unique(['vnode_id', 'domain'], 'fleet_vhosts_vnode_domain_unique');
        });
    }
};
