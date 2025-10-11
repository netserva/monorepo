<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NetServa\Dns\Models\DnsProvider;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Restructure name/description:
     * - name: simple slug-like identifier (e.g., "homelab", "cloudflare-prod")
     * - description: full descriptive text (e.g., "Homelab PowerDNS on GW")
     */
    public function up(): void
    {
        // Add unique index to name for slug-like lookups
        Schema::table('dns_providers', function (Blueprint $table) {
            $table->unique('name');
        });

        // Migrate existing providers: swap name/description
        $providers = DnsProvider::all();
        foreach ($providers as $provider) {
            $oldName = $provider->name;
            $oldDescription = $provider->description;

            // Generate slug from old name
            $slug = strtolower(str_replace(' ', '-', preg_replace('/[^A-Za-z0-9\s-]/', '', $oldName)));

            // Update: name becomes slug, description gets old name + old description
            $provider->name = $slug;
            $provider->description = $oldName . ($oldDescription ? " - {$oldDescription}" : '');
            $provider->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dns_providers', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }
};
