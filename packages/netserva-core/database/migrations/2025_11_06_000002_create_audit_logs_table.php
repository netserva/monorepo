<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type')->index(); // created, updated, deleted, etc.
            $table->string('event_category')->default('model')->index(); // model, system, security, user
            $table->string('severity_level')->default('low')->index(); // low, medium, high, critical
            $table->string('resource_type')->nullable()->index(); // Model class name
            $table->unsignedBigInteger('resource_id')->nullable()->index();
            $table->string('resource_name')->nullable(); // Human-readable resource identifier
            $table->text('description')->nullable();
            $table->json('old_values')->nullable(); // Before state
            $table->json('new_values')->nullable(); // After state
            $table->json('metadata')->nullable(); // Additional context
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            // Composite indexes for common queries
            $table->index(['resource_type', 'resource_id'], 'audit_logs_resource_index');
            $table->index(['user_id', 'occurred_at'], 'audit_logs_user_time_index');
            $table->index(['event_category', 'severity_level'], 'audit_logs_category_severity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
