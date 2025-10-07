<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_jobs', function (Blueprint $table) {
            $table->id();

            // Job identification
            $table->string('job_name');

            // Task association
            $table->foreignId('automation_task_id')
                ->constrained('automation_tasks')
                ->cascadeOnDelete();

            // Job status and priority
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'cancelled'])
                ->default('pending');
            $table->enum('priority', ['low', 'normal', 'high'])->default('normal');

            // Timing information
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('execution_time_seconds')->nullable();

            // Command and results
            $table->text('command_executed')->nullable();
            $table->longText('stdout')->nullable();
            $table->longText('stderr')->nullable();
            $table->integer('exit_code')->nullable();
            $table->text('error_message')->nullable();

            // Target information
            $table->string('target_host')->nullable();
            $table->string('target_user')->nullable();

            // Progress tracking
            $table->decimal('progress_percent', 5, 2)->default(0);

            // Metadata
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->string('created_by')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['automation_task_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['priority', 'status']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_jobs');
    }
};
