<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_venues', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // 'aws-us-east-1', 'homelab-garage'
            $table->string('slug')->unique();
            $table->string('provider')->nullable(); // 'aws', 'hetzner', 'homelab', 'digitalocean'
            $table->string('location')->nullable(); // 'US East (N. Virginia)', 'Garage'
            $table->string('region')->nullable(); // 'us-east-1', 'fsn1', 'nyc3'
            $table->json('credentials')->nullable(); // Provider API keys (encrypted)
            $table->json('metadata')->nullable(); // Additional venue-specific data
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes for common queries
            $table->index(['provider', 'region']);
            $table->index(['is_active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_venues');
    }
};
