<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_vhosts', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->index(); // Primary domain/identifier e.g., 'goldcoast.org'
            $table->string('slug')->unique();
            $table->foreignId('vnode_id')->constrained('fleet_vnodes')->onDelete('cascade');

            // Instance details
            $table->string('instance_type')->nullable(); // 'vm', 'ct', 'lxc', 'docker'
            $table->string('instance_id')->nullable(); // Provider-specific ID
            $table->integer('cpu_cores')->nullable();
            $table->integer('memory_mb')->nullable();
            $table->integer('disk_gb')->nullable();

            // Network and services
            $table->json('ip_addresses')->nullable(); // Array of IPs
            $table->json('services')->nullable(); // Running services

            // NetServa environment variables (54 NS variables)
            $table->json('environment_vars')->nullable();

            // Discovery tracking
            $table->timestamp('last_discovered_at')->nullable();
            $table->text('last_error')->nullable();

            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive', 'maintenance', 'error'])->default('active');
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes(); // For tracking decommissioned instances

            $table->index(['vnode_id', 'status']);
            $table->index(['domain', 'instance_type']);
            $table->unique(['vnode_id', 'domain'], 'fleet_vhosts_vnode_domain_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_vhosts');
    }
};
