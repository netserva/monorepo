<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wireguard_peers', function (Blueprint $table) {
            $table->id();

            // Basic identification
            $table->string('name');
            $table->foreignId('wireguard_server_id')
                ->constrained('wireguard_servers')
                ->cascadeOnDelete();

            // Network configuration
            $table->ipAddress('allocated_ip'); // 10.100.0.5
            $table->json('allowed_ips')->default('["0.0.0.0/0"]'); // Routes

            // Security keys
            $table->text('public_key');
            $table->text('private_key_encrypted');

            // Connection status
            $table->enum('status', ['disconnected', 'connected', 'error'])
                ->default('disconnected');
            $table->timestamp('last_handshake')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Indexes
            $table->index(['wireguard_server_id', 'is_active']);
            $table->index(['status', 'last_handshake']);
            $table->unique(['wireguard_server_id', 'allocated_ip']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wireguard_peers');
    }
};
