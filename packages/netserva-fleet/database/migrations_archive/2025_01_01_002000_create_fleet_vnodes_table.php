<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_vnodes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., 'mgo', 'nsorg', 'haproxy'
            $table->string('slug')->unique();
            $table->foreignId('vsite_id')->constrained('fleet_vsites')->onDelete('cascade');

            // Link to existing SSH hosts table
            $table->foreignId('ssh_host_id')->nullable()->constrained('ssh_hosts')->onDelete('set null');

            $table->enum('role', ['compute', 'network', 'storage', 'mixed'])->default('compute');
            $table->enum('environment', ['development', 'staging', 'production'])->default('production');

            // System information
            $table->string('ip_address')->nullable();
            $table->string('operating_system')->nullable();
            $table->string('kernel_version')->nullable();
            $table->integer('cpu_cores')->nullable();
            $table->integer('memory_mb')->nullable();
            $table->integer('disk_gb')->nullable();
            $table->json('services')->nullable(); // Running services

            // Discovery tracking
            $table->enum('discovery_method', ['ssh', 'api', 'manual'])->default('ssh');
            $table->timestamp('last_discovered_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('next_scan_at')->nullable();
            $table->integer('scan_frequency_hours')->default(24);

            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive', 'maintenance', 'error'])->default('active');
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes(); // For tracking decommissioned nodes

            $table->index(['vsite_id', 'role']);
            $table->index(['environment', 'status']);
            $table->index('next_scan_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_vnodes');
    }
};
