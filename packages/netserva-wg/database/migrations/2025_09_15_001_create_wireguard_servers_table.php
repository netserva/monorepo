<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wireguard_servers', function (Blueprint $table) {
            $table->id();

            // Basic identification
            $table->string('name');
            $table->text('description')->nullable();

            // Network configuration
            $table->string('network_cidr'); // 10.100.0.0/24
            $table->ipAddress('server_ip'); // 10.100.0.1
            $table->integer('listen_port')->default(51820);

            // Security keys
            $table->text('public_key');
            $table->text('private_key_encrypted');

            // Server endpoint
            $table->string('endpoint'); // FQDN or IP address for clients

            // SSH deployment
            $table->string('ssh_host_id')->nullable(); // Link to SSH hosts

            // Status
            $table->enum('status', ['draft', 'active', 'maintenance', 'error'])
                ->default('draft');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Indexes
            $table->index(['status', 'is_active']);
            $table->index(['ssh_host_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wireguard_servers');
    }
};
