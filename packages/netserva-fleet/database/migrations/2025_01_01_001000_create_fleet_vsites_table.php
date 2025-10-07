<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_vsites', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., 'local-incus', 'binarylane-sydney'
            $table->string('slug')->unique();

            // Separate provider and technology to avoid unique conflicts
            $table->string('provider'); // 'local', 'binarylane', 'customer'
            $table->string('technology'); // 'incus', 'proxmox', 'vps', 'hardware', 'router'
            $table->string('location')->nullable(); // 'workstation', 'sydney', 'melbourne'

            $table->string('api_endpoint')->nullable();
            $table->text('api_credentials')->nullable(); // Encrypted JSON
            $table->json('capabilities')->nullable(); // Features available
            $table->text('description')->nullable();

            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Compound unique to handle same provider with different tech/locations
            $table->unique(['provider', 'technology', 'location'], 'vsites_provider_tech_location_unique');
            $table->index(['provider', 'technology']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_vsites');
    }
};
