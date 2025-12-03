<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stores child host (glue record) data for domains
     * Caches data from Synergy Wholesale API for fast access
     */
    public function up(): void
    {
        Schema::create('glue_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sw_domain_id')->constrained('sw_domains')->onDelete('cascade');
            $table->string('hostname'); // e.g., "ns1.renta.net"
            $table->json('ip_addresses'); // ["175.45.183.189", "2001:db8::1"]
            $table->boolean('is_synced')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->text('sync_error')->nullable(); // Store any sync errors
            $table->timestamps();

            $table->unique(['sw_domain_id', 'hostname']);
            $table->index('hostname'); // For looking up by nameserver name
            $table->index('is_synced');
            $table->index('last_synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('glue_records');
    }
};
