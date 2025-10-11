<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add DNS provider association to FleetVenue.
     * Enables venue-level DNS policy enforcement (e.g., homelab uses local PowerDNS).
     */
    public function up(): void
    {
        Schema::table('fleet_venues', function (Blueprint $table) {
            $table->foreignId('dns_provider_id')
                ->nullable()
                ->after('location')
                ->constrained('dns_providers')
                ->nullOnDelete()
                ->comment('DNS provider for this venue (null = use default provider)');

            $table->index('dns_provider_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fleet_venues', function (Blueprint $table) {
            $table->dropForeign(['dns_provider_id']);
            $table->dropIndex(['dns_provider_id']);
            $table->dropColumn('dns_provider_id');
        });
    }
};
