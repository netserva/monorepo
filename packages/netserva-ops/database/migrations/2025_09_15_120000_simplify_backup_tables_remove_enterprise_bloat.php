<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // Simplify backup_repositories table
        Schema::table('backup_repositories', function (Blueprint $table) {
            // Drop indexes before dropping columns
            try {
                $table->dropIndex(['storage_driver', 'health_status']);
            } catch (\Exception $e) {
                // Index might not exist, continue
            }

            try {
                $table->dropIndex(['health_status']);
            } catch (\Exception $e) {
                // Index might not exist, continue
            }

            // Drop enterprise storage configuration
            $table->dropColumn([
                'storage_config',
                'max_parallel_operations',
                'storage_quota_bytes',

                // Drop complex encryption/compression settings
                'encryption_method',
                'encryption_key_name',
                'encryption_config',
                'compression_enabled',
                'compression_method',
                'compression_level',

                // Drop enterprise health monitoring
                'health_status',
                'health_message',
                'health_details',
                'last_health_check_at',

                // Drop connection testing features
                'connection_verified',
                'last_connection_test_at',
                'connection_failures',
                'last_connection_failure_at',
                'last_connection_error',

                // Drop enterprise statistics
                'successful_backups',
                'failed_backups',
                'last_successful_backup_at',

                // Drop enterprise metadata
                'tags',
                'metadata',
                'verified_at',
                'cleaned_up_at',
            ]);
        });

        // Simplify backup_snapshots table
        Schema::table('backup_snapshots', function (Blueprint $table) {
            // Drop indexes before dropping columns
            try {
                $table->dropIndex(['verified', 'last_verified_at']);
            } catch (\Exception $e) {
                // Index might not exist, continue
            }

            try {
                $table->dropIndex(['expires_at', 'is_protected']);
            } catch (\Exception $e) {
                // Index might not exist, continue
            }

            try {
                $table->dropIndex(['is_protected', 'expires_at']);
            } catch (\Exception $e) {
                // Index might not exist, continue
            }

            // Drop compression analytics
            $table->dropColumn([
                'compressed_size_bytes',
                'compression_ratio',

                // Drop file analytics
                'files_count',
                'directories_count',
                'included_paths',
                'excluded_paths',
                'file_types_summary',

                // Drop complex verification system
                'checksum_algorithm',
                'verified',
                'last_verified_at',
                'verification_results',

                // Drop enterprise encryption details
                'encryption_method',
                'encryption_key_fingerprint',

                // Drop incremental backup complexity
                'incremental_metadata',

                // Drop enterprise error handling
                'error_details',
                'warnings',
                'execution_log',

                // Drop performance analytics
                'transfer_rate_mbps',
                'cpu_usage_percent',
                'memory_usage_bytes',
                'network_io_bytes',
                'disk_io_bytes',

                // Drop enterprise retention management
                'is_protected',
                'protection_reason',
                'retention_tags',

                // Drop restore tracking analytics
                'restore_count',
                'last_restored_at',
                'restore_history',

                // Drop deduplication analytics
                'is_deduplicated',
                'deduplication_ratio',
                'unique_data_bytes',

                // Drop enterprise metadata
                'tags',
                'metadata',
                'trigger_metadata',

                // Drop external system integration
                'external_snapshot_id',
                'external_metadata',
            ]);
        });

        // Drop complex retention policies and schedules tables entirely
        Schema::dropIfExists('backup_retention_policies');
        Schema::dropIfExists('backup_schedules');
    }

    public function down(): void
    {
        // Restore backup_repositories columns
        Schema::table('backup_repositories', function (Blueprint $table) {
            $table->json('storage_config')->nullable();
            $table->integer('max_parallel_operations')->default(3);
            $table->bigInteger('storage_quota_bytes')->nullable();

            $table->string('encryption_method', 50)->default('aes-256-cbc');
            $table->string('encryption_key_name')->nullable();
            $table->json('encryption_config')->nullable();
            $table->boolean('compression_enabled')->default(true);
            $table->string('compression_method', 20)->default('gzip');
            $table->integer('compression_level')->default(6);

            $table->enum('health_status', ['healthy', 'warning', 'critical', 'unknown'])->default('unknown');
            $table->text('health_message')->nullable();
            $table->json('health_details')->nullable();
            $table->timestamp('last_health_check_at')->nullable();

            $table->boolean('connection_verified')->default(false);
            $table->timestamp('last_connection_test_at')->nullable();
            $table->integer('connection_failures')->default(0);
            $table->timestamp('last_connection_failure_at')->nullable();
            $table->text('last_connection_error')->nullable();

            $table->integer('successful_backups')->default(0);
            $table->integer('failed_backups')->default(0);
            $table->timestamp('last_successful_backup_at')->nullable();

            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('cleaned_up_at')->nullable();
        });

        // Restore backup_snapshots columns
        Schema::table('backup_snapshots', function (Blueprint $table) {
            $table->bigInteger('compressed_size_bytes')->nullable();
            $table->decimal('compression_ratio', 5, 2)->nullable();

            $table->integer('files_count')->nullable();
            $table->integer('directories_count')->nullable();
            $table->json('included_paths')->nullable();
            $table->json('excluded_paths')->nullable();
            $table->json('file_types_summary')->nullable();

            $table->string('checksum_algorithm', 20)->nullable();
            $table->boolean('verified')->default(false);
            $table->timestamp('last_verified_at')->nullable();
            $table->json('verification_results')->nullable();

            $table->string('encryption_method', 50)->nullable();
            $table->string('encryption_key_fingerprint')->nullable();

            $table->json('incremental_metadata')->nullable();

            $table->json('error_details')->nullable();
            $table->text('warnings')->nullable();
            $table->json('execution_log')->nullable();

            $table->decimal('transfer_rate_mbps', 8, 2)->nullable();
            $table->integer('cpu_usage_percent')->nullable();
            $table->bigInteger('memory_usage_bytes')->nullable();
            $table->integer('network_io_bytes')->nullable();
            $table->integer('disk_io_bytes')->nullable();

            $table->boolean('is_protected')->default(false);
            $table->text('protection_reason')->nullable();
            $table->json('retention_tags')->nullable();

            $table->integer('restore_count')->default(0);
            $table->timestamp('last_restored_at')->nullable();
            $table->json('restore_history')->nullable();

            $table->boolean('is_deduplicated')->default(false);
            $table->decimal('deduplication_ratio', 5, 2)->nullable();
            $table->bigInteger('unique_data_bytes')->nullable();

            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->json('trigger_metadata')->nullable();

            $table->string('external_snapshot_id')->nullable();
            $table->json('external_metadata')->nullable();
        });

        // Recreate retention policies table
        Schema::create('backup_retention_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('backup_repository_id')->constrained()->cascadeOnDelete();
            $table->enum('scope', ['global', 'repository', 'job']);
            $table->integer('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->integer('keep_daily')->default(7);
            $table->integer('keep_weekly')->default(4);
            $table->integer('keep_monthly')->default(12);
            $table->integer('keep_yearly')->default(2);
            $table->json('advanced_rules')->nullable();
            $table->timestamps();
        });

        // Recreate schedules table
        Schema::create('backup_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('backup_job_id')->constrained()->cascadeOnDelete();
            $table->string('cron_expression');
            $table->string('timezone')->default('UTC');
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }
};
