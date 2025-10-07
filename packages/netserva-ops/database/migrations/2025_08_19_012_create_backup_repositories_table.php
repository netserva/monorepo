<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_repositories', function (Blueprint $table) {
            $table->id();

            // Repository identification
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Storage configuration
            $table->enum('storage_driver', ['local', 's3', 'sftp', 'restic', 'google', 'azure', 'backblaze'])
                ->default('local');
            $table->json('storage_config'); // Driver-specific configuration
            $table->string('storage_path')->nullable(); // Base path within storage

            // Repository settings
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('max_parallel_operations')->default(3);
            $table->bigInteger('storage_quota_bytes')->nullable(); // Optional storage limit
            $table->integer('retention_days')->default(30);

            // Encryption settings
            $table->boolean('encryption_enabled')->default(true);
            $table->string('encryption_method', 50)->default('aes-256-cbc');
            $table->string('encryption_key_name')->nullable(); // Reference to secrets manager
            $table->json('encryption_config')->nullable();

            // Compression settings
            $table->boolean('compression_enabled')->default(true);
            $table->string('compression_method', 20)->default('gzip');
            $table->integer('compression_level')->default(6);

            // Repository statistics
            $table->bigInteger('total_size_bytes')->default(0);
            $table->integer('total_snapshots')->default(0);
            $table->integer('successful_backups')->default(0);
            $table->integer('failed_backups')->default(0);
            $table->timestamp('last_backup_at')->nullable();
            $table->timestamp('last_successful_backup_at')->nullable();

            // Health monitoring
            $table->enum('health_status', ['healthy', 'warning', 'critical', 'unknown'])->default('unknown');
            $table->text('health_message')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->json('health_details')->nullable();

            // Connection testing
            $table->boolean('connection_verified')->default(false);
            $table->timestamp('last_connection_test_at')->nullable();
            $table->integer('connection_failures')->default(0);
            $table->timestamp('last_connection_failure_at')->nullable();
            $table->text('last_connection_error')->nullable();

            // Repository lifecycle
            $table->json('tags')->nullable(); // For categorization
            $table->json('metadata')->nullable(); // Additional repository metadata
            $table->timestamp('verified_at')->nullable(); // Last integrity verification
            $table->timestamp('cleaned_up_at')->nullable(); // Last cleanup operation

            $table->timestamps();

            // Indexes for performance
            $table->index(['is_active', 'is_default']);
            $table->index(['storage_driver', 'health_status']);
            $table->index(['last_backup_at', 'is_active']);
            $table->index('health_status');
            $table->index(['created_at', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_repositories');
    }
};
