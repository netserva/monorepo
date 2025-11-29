<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setup_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_id')->unique(); // UUID for tracking
            $table->foreignId('setup_template_id')->constrained()->onDelete('cascade');
            $table->string('target_host'); // SSH host identifier
            $table->string('target_hostname')->nullable(); // Actual hostname/IP
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->json('configuration'); // Custom configuration for this job
            $table->json('components_status')->nullable(); // Status of each component
            $table->text('output_log')->nullable(); // Command output log
            $table->text('error_log')->nullable(); // Error messages
            $table->integer('progress_percentage')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->string('initiated_by')->nullable(); // User/system that started
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_jobs');
    }
};
