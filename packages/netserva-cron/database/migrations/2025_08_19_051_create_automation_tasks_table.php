<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_tasks', function (Blueprint $table) {
            $table->id();

            // Task identification
            $table->string('name');
            $table->text('description')->nullable();

            // Task configuration
            $table->enum('task_type', ['shell', 'ssh', 'script'])->default('shell');
            $table->text('command'); // Command to execute

            // Target configuration
            $table->string('target_host')->default('localhost');
            $table->string('target_user')->default('root');

            // Execution configuration
            $table->integer('timeout_seconds')->default(300);
            $table->integer('max_retries')->default(3);
            $table->integer('retry_delay_seconds')->default(30);

            // Status
            $table->boolean('is_active')->default(true);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->integer('priority')->default(2); // 1=low, 2=normal, 3=high

            // Performance tracking
            $table->decimal('success_rate', 5, 2)->default(0);

            // Metadata
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->string('created_by')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['task_type', 'is_active']);
            $table->index(['status', 'is_active']);
            $table->index(['target_host']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_tasks');
    }
};
