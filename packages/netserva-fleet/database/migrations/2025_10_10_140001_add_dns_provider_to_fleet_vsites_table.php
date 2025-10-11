<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add DNS provider association to FleetVSite.
     * Enables site-level DNS provider override (inherits from venue if null).
     */
    public function up(): void
    {
        Schema::table('fleet_vsites', function (Blueprint $table) {
            $table->foreignId('dns_provider_id')
                ->nullable()
                ->after('location')
                ->constrained('dns_providers')
                ->nullOnDelete()
                ->comment('DNS provider for this vsite (null = inherit from venue)');

            $table->index('dns_provider_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fleet_vsites', function (Blueprint $table) {
            $table->dropForeign(['dns_provider_id']);
            $table->dropIndex(['dns_provider_id']);
            $table->dropColumn('dns_provider_id');
        });
    }
};
