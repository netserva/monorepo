<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_snapshots', function (Blueprint $table) {
            $table->id();

            // Snapshot identification
            $table->string('snapshot_id')->unique(); // UUID or unique identifier
            $table->string('name')->nullable(); // Optional human-readable name

            // Job relationship
            $table->foreignId('backup_job_id')
                ->constrained('backup_jobs')
                ->cascadeOnDelete();

            // Repository relationship
            $table->foreignId('backup_repository_id')
                ->constrained('backup_repositories')
                ->cascadeOnDelete();

            // Backup execution details
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'partial', 'cancelled'])
                ->default('pending');
            $table->enum('backup_type', ['full', 'incremental', 'differential'])
                ->default('full');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('runtime_seconds')->nullable();

            // Storage information
            $table->string('storage_path'); // Full path within repository
            $table->string('storage_filename')->nullable(); // Actual filename
            $table->bigInteger('original_size_bytes')->nullable(); // Size before compression
            $table->bigInteger('compressed_size_bytes')->nullable(); // Size after compression
            $table->bigInteger('stored_size_bytes')->nullable(); // Actual storage used
            $table->decimal('compression_ratio', 5, 2)->nullable(); // Compression achieved

            // Backup content details
            $table->integer('files_count')->nullable();
            $table->integer('directories_count')->nullable();
            $table->json('included_paths')->nullable(); // Paths that were backed up
            $table->json('excluded_paths')->nullable(); // Paths that were excluded
            $table->json('file_types_summary')->nullable(); // Summary of file types

            // Integrity and verification
            $table->string('checksum_algorithm', 20)->nullable(); // md5, sha256, etc.
            $table->string('checksum_value')->nullable();
            $table->boolean('verified')->default(false);
            $table->timestamp('last_verified_at')->nullable();
            $table->json('verification_results')->nullable();

            // Encryption details
            $table->boolean('is_encrypted')->default(false);
            $table->string('encryption_method', 50)->nullable();
            $table->string('encryption_key_fingerprint')->nullable(); // For key identification

            // Parent relationship (for incremental backups)
            $table->foreignId('parent_snapshot_id')
                ->nullable()
                ->constrained('backup_snapshots')
                ->nullOnDelete();
            $table->json('incremental_metadata')->nullable(); // Metadata for incremental logic

            // Error handling and logging
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable(); // Detailed error information
            $table->text('warnings')->nullable(); // Non-fatal warnings
            $table->json('execution_log')->nullable(); // Detailed execution log

            // Performance metrics
            $table->decimal('transfer_rate_mbps', 8, 2)->nullable(); // Data transfer rate
            $table->integer('cpu_usage_percent')->nullable();
            $table->bigInteger('memory_usage_bytes')->nullable();
            $table->integer('network_io_bytes')->nullable();
            $table->integer('disk_io_bytes')->nullable();

            // Retention management
            $table->timestamp('expires_at')->nullable(); // When this snapshot should be deleted
            $table->boolean('is_protected')->default(false); // Prevent automatic deletion
            $table->text('protection_reason')->nullable();
            $table->json('retention_tags')->nullable(); // daily, weekly, monthly, yearly

            // Restore tracking
            $table->integer('restore_count')->default(0);
            $table->timestamp('last_restored_at')->nullable();
            $table->json('restore_history')->nullable(); // History of restore operations

            // Deduplication (if supported by backend)
            $table->boolean('is_deduplicated')->default(false);
            $table->decimal('deduplication_ratio', 5, 2)->nullable();
            $table->bigInteger('unique_data_bytes')->nullable();

            // Snapshot lifecycle and metadata
            $table->json('tags')->nullable(); // For categorization
            $table->json('metadata')->nullable(); // Additional snapshot-specific data
            $table->string('created_by')->nullable(); // User or system that triggered backup
            $table->string('trigger_type', 50)->nullable(); // scheduled, manual, api, webhook
            $table->json('trigger_metadata')->nullable(); // Details about what triggered backup

            // External references
            $table->string('external_snapshot_id')->nullable(); // ID in external system (restic, etc.)
            $table->json('external_metadata')->nullable(); // Metadata from external backup system

            $table->timestamps();

            // Indexes for performance
            $table->index(['backup_job_id', 'status']);
            $table->index(['backup_repository_id', 'status']);
            $table->index(['status', 'started_at']);
            $table->index(['expires_at', 'is_protected']);
            $table->index(['backup_type', 'started_at']);
            $table->index(['parent_snapshot_id', 'backup_type']);
            $table->index(['verified', 'last_verified_at']);
            $table->index(['is_protected', 'expires_at']);
            $table->index(['created_at', 'status']);
            $table->index('trigger_type');

            // Composite indexes for common queries
            $table->index(['backup_job_id', 'backup_type', 'started_at']);
            $table->index(['status', 'backup_type', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_snapshots');
    }
};
