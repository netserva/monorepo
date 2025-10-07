<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_id')->unique();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('backup_repository_id')->constrained()->cascadeOnDelete();
            $table->string('target_host');
            $table->json('source_paths');
            $table->string('destination_path');
            $table->enum('backup_type', ['full', 'incremental', 'differential'])->default('full');
            $table->json('exclude_patterns')->nullable();
            $table->enum('compression', ['none', 'gzip', 'bzip2', 'xz'])->default('gzip');
            $table->boolean('enabled')->default(true);
            $table->string('schedule')->nullable(); // Cron expression
            $table->enum('status', ['pending', 'active', 'paused', 'completed', 'failed'])->default('pending');
            $table->integer('progress_percentage')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->bigInteger('backup_size_bytes')->nullable();
            $table->text('output_log')->nullable();
            $table->text('error_log')->nullable();
            $table->string('backup_filename')->nullable();
            $table->boolean('retention_enabled')->default(true);
            $table->integer('retention_days')->default(30);
            $table->string('initiated_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'enabled']);
            $table->index(['target_host', 'enabled']);
            $table->index(['backup_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_jobs');
    }
};
