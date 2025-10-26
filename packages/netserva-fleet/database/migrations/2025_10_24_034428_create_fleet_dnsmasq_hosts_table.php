<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fleet_dnsmasq_hosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_vnode_id')->constrained('fleet_vnodes')->onDelete('cascade');
            $table->string('hostname');
            $table->string('ip');
            $table->enum('type', ['A', 'AAAA', 'PTR', 'CNAME']);
            $table->string('mac')->nullable();
            $table->enum('source', ['uci', 'config'])->default('uci');
            $table->boolean('dns_enabled')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Ensure uniqueness per vnode
            $table->unique(['fleet_vnode_id', 'hostname', 'ip', 'type'], 'dnsmasq_host_unique');

            // Indexes for common queries
            $table->index(['fleet_vnode_id', 'hostname']);
            $table->index(['fleet_vnode_id', 'type']);
            $table->index('mac');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fleet_dnsmasq_hosts');
    }
};
