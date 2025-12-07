<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conditional Migration: Add customer_id to Fleet and Domain tables
 *
 * This migration only runs if the target tables exist.
 * It enables optional integration with NetServa Fleet and Domain packages.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add customer_id to fleet_vsites if table exists and column doesn't
        if (Schema::hasTable('fleet_vsites') && ! Schema::hasColumn('fleet_vsites', 'customer_id')) {
            Schema::table('fleet_vsites', function (Blueprint $table) {
                $table->foreignId('customer_id')
                    ->nullable()
                    ->after('owner')
                    ->constrained('crm_customers')
                    ->nullOnDelete();

                $table->index('customer_id');
            });
        }

        // Add customer_id to sw_domains if table exists and column doesn't
        if (Schema::hasTable('sw_domains') && ! Schema::hasColumn('sw_domains', 'customer_id')) {
            Schema::table('sw_domains', function (Blueprint $table) {
                $table->foreignId('customer_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('crm_customers')
                    ->nullOnDelete();

                $table->index('customer_id');
            });
        }
    }

    public function down(): void
    {
        // Remove customer_id from fleet_vsites
        if (Schema::hasTable('fleet_vsites') && Schema::hasColumn('fleet_vsites', 'customer_id')) {
            Schema::table('fleet_vsites', function (Blueprint $table) {
                $table->dropForeign(['customer_id']);
                $table->dropColumn('customer_id');
            });
        }

        // Remove customer_id from sw_domains
        if (Schema::hasTable('sw_domains') && Schema::hasColumn('sw_domains', 'customer_id')) {
            Schema::table('sw_domains', function (Blueprint $table) {
                $table->dropForeign(['customer_id']);
                $table->dropColumn('customer_id');
            });
        }
    }
};
