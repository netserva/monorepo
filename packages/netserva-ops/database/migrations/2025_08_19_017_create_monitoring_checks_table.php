<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_checks', function (Blueprint $table) {
            $table->id();

            // Simple clean schema matching model fillable fields (~19 fields total)
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('infrastructure_node_id')
                ->nullable()
                ->constrained('infrastructure_nodes')
                ->cascadeOnDelete();
            $table->enum('check_type', ['ping', 'http', 'tcp', 'ssl', 'dns', 'service', 'custom']);
            $table->string('target'); // IP, URL, hostname, service name, etc.
            $table->json('check_config'); // Type-specific configuration
            $table->boolean('is_active')->default(true);
            $table->integer('check_interval_seconds')->default(60);
            $table->integer('timeout_seconds')->default(30);
            $table->enum('status', ['unknown', 'up', 'down', 'degraded', 'maintenance'])->default('unknown');
            $table->text('last_check_message')->nullable();
            $table->integer('last_response_time_ms')->nullable();
            $table->decimal('uptime_percentage', 5, 2)->default(100.00);
            $table->boolean('alert_enabled')->default(true);
            $table->json('alert_contacts')->nullable(); // Specific contacts for this check
            $table->boolean('in_maintenance')->default(false);
            $table->timestamp('last_check_at')->nullable();
            $table->timestamp('next_check_at')->nullable();

            $table->timestamps();

            // Essential indexes only
            $table->index(['is_active', 'next_check_at']);
            $table->index(['infrastructure_node_id', 'is_active']);
            $table->index(['check_type', 'is_active']);
            $table->index(['status', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_checks');
    }
};
