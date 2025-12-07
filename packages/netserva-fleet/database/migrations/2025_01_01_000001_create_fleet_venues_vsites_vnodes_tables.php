<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fleet core tables: vsites (sites) and vnodes (servers)
 *
 * Note: fleet_venues has been removed as enterprise bloat.
 * Location/owner are now string fields on vsites.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Fleet VSites (logical groupings / sites)
        Schema::create('fleet_vsites', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('provider')->nullable();  // binarylane, proxmox, incus, digitalocean, vultr, bare-metal
            $table->string('technology')->nullable();  // proxmox, incus, docker, kvm
            $table->string('location')->nullable();  // sydney, brisbane, melbourne, goldcoast, vpc
            $table->string('owner')->default('self');  // self, customer-1, etc.
            $table->string('api_endpoint')->nullable();
            $table->text('api_credentials')->nullable();
            $table->string('network_cidr')->nullable();  // e.g., 10.0.0.0/24
            $table->json('capabilities')->nullable();  // ['virtualization', 'storage', 'networking']
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_active')->default(true);
            $table->foreignId('dns_provider_id')->nullable()->constrained('dns_providers')->nullOnDelete();
            $table->foreignId('palette_id')->nullable()->constrained('palettes')->nullOnDelete();
            $table->timestamps();

            $table->index(['provider', 'technology']);
            $table->index('status');
        });

        // Fleet VNodes (servers / virtual machines)
        Schema::create('fleet_vnodes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();

            // Three-level hostname system
            $table->string('ssh_host')->unique()->nullable();  // CLI identifier for sx (e.g., "mrn", "gw")
            $table->string('hostname')->nullable();  // Actual server hostname (e.g., "mail", "gw")
            $table->string('fqdn')->nullable();  // Public FQDN with PTR (e.g., "mail.renta.net")
            $table->string('fqdn_internal')->nullable();  // For dual-homed servers (e.g., "gw.goldcoast.org")

            $table->foreignId('vsite_id')->constrained('fleet_vsites')->cascadeOnDelete();
            $table->foreignId('ssh_host_id')->nullable()->constrained('ssh_hosts')->nullOnDelete();
            $table->foreignId('dns_provider_id')->nullable()->constrained('dns_providers')->nullOnDelete();
            $table->foreignId('palette_id')->nullable()->constrained('palettes')->nullOnDelete();

            // Role and environment
            $table->string('role')->default('compute');  // compute, storage, database, web, mail, gateway
            $table->string('environment')->default('production');  // production, staging, development

            // IP addresses
            $table->string('ipv4_public')->nullable();  // Public IP
            $table->string('ipv4_private')->nullable();  // Internal/VPC IP
            $table->string('ipv6_address')->nullable();

            // System info
            $table->string('operating_system')->nullable();  // Ubuntu 24.04, Debian 12, Rocky Linux 9
            $table->string('kernel_version')->nullable();
            $table->unsignedSmallInteger('cpu_cores')->nullable();
            $table->unsignedInteger('memory_mb')->nullable();
            $table->unsignedInteger('disk_gb')->nullable();

            // Services (comma-separated)
            $table->text('services')->nullable();  // "nginx,postfix,dovecot,php-fpm"

            // SSH configuration
            $table->string('ssh_user')->default('root');
            $table->unsignedSmallInteger('ssh_port')->default(22);

            // Discovery/scanning
            $table->string('discovery_method')->default('ssh');  // ssh, api, manual
            $table->unsignedSmallInteger('scan_frequency_hours')->default(24);
            $table->timestamp('last_discovered_at')->nullable();
            $table->timestamp('next_scan_at')->nullable();

            // Status flags
            $table->string('status')->default('unknown');  // active, inactive, maintenance, unknown
            $table->boolean('is_active')->default(true);
            $table->boolean('is_critical')->default(false);  // For alerting
            $table->boolean('email_capable')->default(false);

            // Database configuration
            $table->string('database_type')->default('mysql');
            $table->string('mail_db_path')->nullable();  // Path to mail database (SQLite) or MySQL connection

            // BinaryLane specific
            $table->unsignedBigInteger('binarylane_id')->nullable();
            $table->string('binarylane_region')->nullable();
            $table->string('binarylane_size')->nullable();
            $table->string('binarylane_image')->nullable();
            $table->string('binarylane_status')->nullable();
            $table->unsignedBigInteger('binarylane_vpc_id')->nullable();
            $table->timestamp('binarylane_created_at')->nullable();
            $table->json('binarylane_data')->nullable();

            // Metadata
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['vsite_id', 'status']);
            $table->index('role');
            $table->index('binarylane_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_vnodes');
        Schema::dropIfExists('fleet_vsites');
    }
};
