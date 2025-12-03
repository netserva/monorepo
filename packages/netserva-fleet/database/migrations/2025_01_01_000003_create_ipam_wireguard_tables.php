<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // IP Networks (subnets)
        Schema::create('ip_networks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('cidr');
            $table->string('network_address');
            $table->integer('prefix_length');
            $table->enum('ip_version', ['4', '6'])->default('4');
            $table->string('gateway')->nullable();
            $table->json('dns_servers')->nullable();
            $table->json('ntp_servers')->nullable();
            $table->enum('network_type', ['public', 'private', 'dmz', 'management', 'storage', 'cluster', 'container', 'vpn', 'other'])->default('private');
            $table->string('environment')->default('production');
            $table->string('location')->nullable();
            $table->string('vlan_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('total_addresses')->default(0);
            $table->integer('used_addresses')->default(0);
            $table->integer('reserved_addresses')->default(0);
            $table->decimal('utilization_percentage', 5, 2)->default(0);
            $table->foreignId('parent_network_id')->nullable()->constrained('ip_networks')->nullOnDelete();
            $table->foreignId('fleet_vnode_id')->nullable()->constrained()->nullOnDelete();
            $table->json('dhcp_config')->nullable();
            $table->json('routing_config')->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cidr', 'environment']);
            $table->index(['network_type', 'environment']);
            $table->index(['is_active', 'network_type']);
            $table->index('cidr');
            $table->index('ip_version');
        });

        // IP Addresses
        Schema::create('ip_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ip_network_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address');
            $table->string('hostname')->nullable();
            $table->string('fqdn')->nullable();
            $table->enum('status', ['available', 'allocated', 'reserved', 'discovered', 'dhcp_pool', 'network', 'broadcast', 'gateway', 'dns', 'ntp', 'blacklisted'])->default('available');
            $table->enum('assignment_type', ['static', 'dhcp', 'auto', 'manual', 'unknown'])->default('static');
            $table->string('mac_address')->nullable();
            $table->text('description')->nullable();
            $table->json('ports')->nullable();
            $table->foreignId('fleet_vnode_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ssh_host_reference')->nullable();
            $table->timestamp('allocated_at')->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_ping_at')->nullable();
            $table->boolean('is_pingable')->nullable();
            $table->integer('ping_count')->default(0);
            $table->integer('failed_pings')->default(0);
            $table->json('monitoring_config')->nullable();
            $table->boolean('auto_dns')->default(false);
            $table->json('dns_records')->nullable();
            $table->integer('sort_order')->default(0);
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['ip_address', 'ip_network_id']);
            $table->index(['status', 'assignment_type']);
            $table->index('hostname');
            $table->index('mac_address');
            $table->index(['is_pingable', 'last_ping_at']);
            $table->index('ssh_host_reference');
        });

        // IP Reservations
        Schema::create('ip_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ip_network_id')->constrained()->cascadeOnDelete();
            $table->string('start_ip');
            $table->string('end_ip');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('contact')->nullable();
            $table->string('project')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->integer('address_count')->default(0);
            $table->boolean('allow_auto_allocation')->default(false);
            $table->json('allocation_rules')->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['valid_from', 'valid_until']);
            $table->index('allow_auto_allocation');
        });

        // WireGuard Servers
        Schema::create('wireguard_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('fleet_vnode_id')->constrained()->cascadeOnDelete();
            $table->string('interface_name')->default('wg0');
            $table->string('listen_port')->default('51820');
            $table->text('private_key');
            $table->text('public_key');
            $table->string('address');
            $table->string('dns')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['fleet_vnode_id', 'interface_name']);
        });

        // WireGuard Peers
        Schema::create('wireguard_peers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('wireguard_server_id')->constrained()->cascadeOnDelete();
            $table->text('public_key');
            $table->text('preshared_key')->nullable();
            $table->string('allowed_ips');
            $table->string('endpoint')->nullable();
            $table->integer('persistent_keepalive')->default(25);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_handshake_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['wireguard_server_id', 'public_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wireguard_peers');
        Schema::dropIfExists('wireguard_servers');
        Schema::dropIfExists('ip_reservations');
        Schema::dropIfExists('ip_addresses');
        Schema::dropIfExists('ip_networks');
    }
};
