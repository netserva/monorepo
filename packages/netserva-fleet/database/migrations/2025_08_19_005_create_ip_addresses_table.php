<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ip_network_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45); // IPv4 or IPv6 address
            $table->string('hostname')->nullable(); // Associated hostname
            $table->string('fqdn')->nullable(); // Fully Qualified Domain Name

            // Address allocation details
            $table->enum('status', [
                'available',
                'allocated',
                'reserved',
                'dhcp_pool',
                'network',
                'broadcast',
                'gateway',
                'dns',
                'ntp',
                'blacklisted',
            ])->default('available');

            $table->enum('assignment_type', [
                'static',
                'dhcp',
                'auto',
                'manual',
            ])->default('static');

            // Assignment details
            $table->string('mac_address', 17)->nullable(); // MAC address if known
            $table->text('description')->nullable();
            $table->string('owner')->nullable(); // Owner or responsible party
            $table->string('service')->nullable(); // Service using this IP
            $table->json('ports')->nullable(); // Array of open/used ports

            // Infrastructure relationships
            $table->foreignId('infrastructure_node_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ssh_host_reference')->nullable(); // Reference to SSH host

            // Lease and lifecycle management
            $table->timestamp('allocated_at')->nullable(); // When IP was allocated
            $table->timestamp('lease_expires_at')->nullable(); // DHCP lease expiration
            $table->timestamp('last_seen_at')->nullable(); // Last network activity
            $table->timestamp('last_ping_at')->nullable(); // Last successful ping
            $table->boolean('is_pingable')->nullable(); // Last ping result

            // Monitoring and tracking
            $table->integer('ping_count')->default(0); // Number of pings performed
            $table->integer('failed_pings')->default(0); // Failed ping attempts
            $table->json('monitoring_config')->nullable(); // Monitoring settings

            // DNS integration
            $table->boolean('auto_dns')->default(false); // Auto-create DNS records
            $table->json('dns_records')->nullable(); // Associated DNS records

            // Metadata and tagging
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable(); // Additional metadata
            $table->text('notes')->nullable(); // Admin notes

            // Audit fields
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes for efficient queries
            $table->unique(['ip_address', 'ip_network_id']); // Prevent duplicate IPs in same network
            $table->index(['status', 'assignment_type']);
            $table->index(['hostname']);
            $table->index(['mac_address']);
            $table->index(['is_pingable', 'last_ping_at']);
            $table->index(['ssh_host_reference']);
            $table->index(['service']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_addresses');
    }
};
