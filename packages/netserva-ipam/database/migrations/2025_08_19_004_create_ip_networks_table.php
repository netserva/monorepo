<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_networks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('cidr', 18); // e.g., "192.168.1.0/24"
            $table->string('network_address', 45); // IPv4 or IPv6
            $table->integer('prefix_length'); // CIDR prefix length
            $table->enum('ip_version', ['4', '6'])->default('4');
            $table->string('gateway', 45)->nullable(); // Gateway IP
            $table->json('dns_servers')->nullable(); // Array of DNS server IPs
            $table->json('ntp_servers')->nullable(); // Array of NTP server IPs

            // Network categorization
            $table->enum('network_type', [
                'public',
                'private',
                'dmz',
                'management',
                'storage',
                'cluster',
                'container',
                'vpn',
                'other',
            ])->default('private');
            $table->string('environment')->default('production'); // production, staging, development
            $table->string('location')->nullable(); // Physical or logical location
            $table->string('vlan_id')->nullable(); // VLAN identifier

            // Network status and allocation tracking
            $table->boolean('is_active')->default(true);
            $table->integer('total_addresses')->default(0); // Total available addresses
            $table->integer('used_addresses')->default(0); // Currently allocated addresses
            $table->integer('reserved_addresses')->default(0); // Reserved addresses
            $table->decimal('utilization_percentage', 5, 2)->default(0); // Usage percentage

            // Relationships
            $table->foreignId('parent_network_id')->nullable()->constrained('ip_networks')->nullOnDelete();
            $table->foreignId('infrastructure_node_id')->nullable()->constrained()->nullOnDelete();

            // IPAM metadata
            $table->json('dhcp_config')->nullable(); // DHCP configuration
            $table->json('routing_config')->nullable(); // Routing information
            $table->json('tags')->nullable(); // Flexible tagging
            $table->json('metadata')->nullable(); // Additional metadata

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes for efficient queries
            $table->index(['network_type', 'environment']);
            $table->index(['is_active', 'network_type']);
            $table->index(['cidr']);
            $table->index(['ip_version']);
            $table->unique(['cidr', 'environment']); // Prevent duplicate networks in same environment
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_networks');
    }
};
