<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dns_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dns_provider_id')->constrained()->onDelete('cascade');
            $table->string('external_id')->nullable(); // Remote nameserver's zone ID
            $table->string('name'); // Domain name (example.com)
            $table->enum('kind', ['Primary', 'Secondary', 'Native', 'Forwarded'])->default('Primary');
            $table->json('masters')->nullable(); // For slave zones
            $table->bigInteger('serial')->nullable(); // SOA serial number from remote
            $table->timestamp('last_check')->nullable(); // Last check from remote
            $table->bigInteger('notified_serial')->nullable();
            $table->string('account')->nullable();
            $table->boolean('active')->default(true);
            $table->json('provider_data')->nullable(); // Provider-specific metadata
            $table->timestamp('last_synced')->nullable(); // Last sync from remote to cache
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->integer('ttl')->default(3600);
            $table->boolean('auto_dnssec')->default(false);
            $table->json('nameservers')->nullable();
            $table->integer('records_count')->default(0);
            $table->boolean('dnssec_enabled')->default(false);
            $table->timestamps();
            $table->softDeletes(); // Soft delete for audit trail

            $table->unique(['dns_provider_id', 'name']); // One zone per provider
            $table->index(['active', 'kind']);
            $table->index('last_synced');
            $table->index('external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_zones');
    }
};
