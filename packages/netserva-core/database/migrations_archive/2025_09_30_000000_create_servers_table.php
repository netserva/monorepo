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
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('hostname')->unique();
            $table->string('ip_address');
            $table->string('status')->default('active')->index();
            $table->text('description')->nullable();

            // System information
            $table->string('os')->nullable();
            $table->string('kernel')->nullable();
            $table->integer('memory_gb')->nullable();
            $table->integer('cpu_count')->default(1);
            $table->float('disk_total_gb')->nullable();
            $table->float('disk_used_gb')->nullable();

            // Monitoring
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('hostname');
            $table->index('ip_address');
            $table->index('last_seen_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
