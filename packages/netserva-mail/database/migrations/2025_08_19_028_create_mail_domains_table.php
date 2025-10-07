<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_domains', function (Blueprint $table) {
            $table->id();

            // Basic identification (~14 fields total)
            $table->string('name');
            $table->string('domain')->unique();
            $table->foreignId('mail_server_id')
                ->constrained('mail_servers')
                ->cascadeOnDelete();
            $table->boolean('is_active')->default(true);

            // Security features
            $table->boolean('enable_dkim')->default(true);
            $table->boolean('enable_spf')->default(true);
            $table->boolean('enable_dmarc')->default(true);

            // Relay configuration
            $table->boolean('relay_enabled')->default(false);
            $table->string('relay_host')->nullable();
            $table->integer('relay_port')->nullable();

            // Basic info
            $table->text('description')->nullable();

            // Metadata
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->string('created_by')->nullable();

            $table->timestamps();

            // Essential indexes only
            $table->index(['mail_server_id', 'is_active']);
            $table->index(['domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_domains');
    }
};
