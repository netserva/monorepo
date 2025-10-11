<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add DNS provider association to FleetVHost.
     * Enables per-vhost DNS provider override (inherits from vnode if null).
     */
    public function up(): void
    {
        Schema::table('fleet_vhosts', function (Blueprint $table) {
            $table->foreignId('dns_provider_id')
                ->nullable()
                ->after('domain')
                ->constrained('dns_providers')
                ->nullOnDelete()
                ->comment('DNS provider for this vhost (null = inherit from vnode)');

            $table->index('dns_provider_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fleet_vhosts', function (Blueprint $table) {
            $table->dropForeign(['dns_provider_id']);
            $table->dropIndex(['dns_provider_id']);
            $table->dropColumn('dns_provider_id');
        });
    }
};
