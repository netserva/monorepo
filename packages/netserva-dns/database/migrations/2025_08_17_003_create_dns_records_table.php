<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dns_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dns_zone_id')->constrained()->onDelete('cascade');
            $table->string('external_id')->nullable(); // Remote nameserver's record ID
            $table->string('name')->nullable(); // Record name (www, mail, @)
            $table->string('type', 10); // A, AAAA, CNAME, MX, TXT, etc.
            $table->text('content'); // Record content/value
            $table->integer('ttl')->default(3600); // Time to live
            $table->integer('priority')->default(0); // For MX, SRV records
            $table->boolean('disabled')->default(false);
            $table->boolean('auth')->default(true);
            $table->string('ordername')->nullable();
            $table->text('comment')->nullable();
            $table->json('provider_data')->nullable(); // Provider-specific metadata
            $table->timestamp('last_synced')->nullable(); // Last sync from remote to cache
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes(); // Soft delete for audit trail

            $table->index(['dns_zone_id', 'type']);
            $table->index(['disabled', 'auth']);
            $table->index('last_synced');
            $table->index('external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_records');
    }
};
