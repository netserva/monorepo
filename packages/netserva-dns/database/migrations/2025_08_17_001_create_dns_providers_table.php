<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dns_providers', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('powerdns'); // powerdns, cloudflare, route53, etc.
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('connection_config'); // API keys, endpoints, SSH tunnels
            $table->boolean('active')->default(true);
            $table->string('version')->nullable(); // Provider API version
            $table->timestamp('last_sync')->nullable(); // Last cache sync from remote
            $table->json('sync_config')->nullable(); // Sync preferences and schedules
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['active', 'type']);
            $table->index('last_sync');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_providers');
    }
};
