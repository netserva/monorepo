<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // Remove enterprise bloat from ip_networks table
        Schema::table('ip_networks', function (Blueprint $table) {
            // Drop indexes and constraints that reference columns we're about to drop
            try {
                $table->dropIndex(['network_type', 'environment']);
            } catch (\Exception $e) {
                // Index might not exist, continue
            }

            try {
                $table->dropUnique(['cidr', 'environment']);
            } catch (\Exception $e) {
                // Unique constraint might not exist, continue
            }

            // Remove complex network categorization
            $table->dropColumn(['environment', 'location', 'vlan_id']);

            // Remove enterprise analytics (use calculated values instead)
            $table->dropColumn(['used_addresses', 'reserved_addresses', 'utilization_percentage']);

            // Remove enterprise configurations
            $table->dropColumn(['ntp_servers', 'dhcp_config', 'routing_config']);

            // Remove enterprise metadata
            $table->dropColumn(['tags', 'metadata']);
        });

        // Remove enterprise bloat from ip_addresses table
        Schema::table('ip_addresses', function (Blueprint $table) {
            // Drop foreign key constraints first
            try {
                $table->dropForeign(['allocated_by']);
            } catch (\Exception $e) {
                // Foreign key might not exist, continue
            }

            try {
                $table->dropForeign(['updated_by']);
            } catch (\Exception $e) {
                // Foreign key might not exist, continue
            }

            // Drop indexes that reference columns we're about to drop
            try {
                $table->dropIndex(['is_pingable', 'last_ping_at']);
            } catch (\Exception $e) {
                // Index might not exist, continue
            }

            try {
                $table->dropIndex(['status', 'assignment_type']);
            } catch (\Exception $e) {
                // Index might not exist, continue
            }

            // Remove complex monitoring and tracking
            $table->dropColumn([
                'ping_count', 'failed_pings', 'last_ping_at', 'is_pingable',
                'last_seen_at', 'monitoring_config',
            ]);

            // Remove enterprise lease management (keep basic allocated_at)
            $table->dropColumn(['lease_expires_at', 'assignment_type']);

            // Remove complex metadata and configurations
            $table->dropColumn(['ports', 'dns_records', 'auto_dns', 'tags', 'metadata', 'notes']);

            // Remove enterprise audit tracking (keep basic)
            $table->dropColumn(['allocated_by', 'updated_by']);
        });

        // Remove enterprise bloat from ip_reservations table
        Schema::table('ip_reservations', function (Blueprint $table) {
            // Drop foreign key constraints first
            try {
                $table->dropForeign(['created_by']);
            } catch (\Exception $e) {
                // Foreign key might not exist, continue
            }

            try {
                $table->dropForeign(['updated_by']);
            } catch (\Exception $e) {
                // Foreign key might not exist, continue
            }

            // Drop indexes that reference columns we're about to drop
            try {
                $table->dropIndex(['valid_from', 'valid_until']);
            } catch (\Exception $e) {
                // Index might not exist, continue
            }

            try {
                $table->dropIndex(['allow_auto_allocation']);
            } catch (\Exception $e) {
                // Index might not exist, continue
            }

            // Remove enterprise workflow fields
            $table->dropColumn(['project', 'contact', 'allocation_rules', 'allow_auto_allocation']);

            // Remove complex validity tracking (keep basic is_active)
            $table->dropColumn(['valid_from', 'valid_until']);

            // Remove enterprise metadata
            $table->dropColumn(['tags', 'metadata', 'notes']);

            // Remove enterprise audit
            $table->dropColumn(['created_by', 'updated_by']);
        });
    }

    public function down(): void
    {
        // Restore ip_networks enterprise fields
        Schema::table('ip_networks', function (Blueprint $table) {
            $table->string('environment')->default('production');
            $table->string('location')->nullable();
            $table->string('vlan_id')->nullable();
            $table->integer('used_addresses')->default(0);
            $table->integer('reserved_addresses')->default(0);
            $table->decimal('utilization_percentage', 5, 2)->default(0);
            $table->json('ntp_servers')->nullable();
            $table->json('dhcp_config')->nullable();
            $table->json('routing_config')->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
        });

        // Restore ip_addresses enterprise fields
        Schema::table('ip_addresses', function (Blueprint $table) {
            $table->integer('ping_count')->default(0);
            $table->integer('failed_pings')->default(0);
            $table->timestamp('last_ping_at')->nullable();
            $table->boolean('is_pingable')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('monitoring_config')->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->enum('assignment_type', ['static', 'dhcp', 'auto', 'manual'])->default('static');
            $table->json('ports')->nullable();
            $table->json('dns_records')->nullable();
            $table->boolean('auto_dns')->default(false);
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // Restore ip_reservations enterprise fields
        Schema::table('ip_reservations', function (Blueprint $table) {
            $table->string('project')->nullable();
            $table->string('contact')->nullable();
            $table->json('allocation_rules')->nullable();
            $table->boolean('allow_auto_allocation')->default(false);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }
};
