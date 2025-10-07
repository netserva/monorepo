<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('config_deployments', function (Blueprint $table) {
            $table->id();

            // Deployment identification
            $table->string('deployment_id')->unique(); // UUID for this deployment
            $table->string('deployment_name')->nullable(); // Human readable name
            $table->text('description')->nullable();

            // Source configuration
            $table->foreignId('config_profile_id')
                ->constrained('config_profiles')
                ->cascadeOnDelete();
            $table->foreignId('infrastructure_node_id')
                ->constrained('infrastructure_nodes')
                ->cascadeOnDelete();

            // Deployment trigger
            $table->enum('trigger_type', [
                'manual', 'scheduled', 'template_change', 'profile_change',
                'rollback', 'emergency', 'api', 'webhook',
            ])->default('manual');
            $table->string('triggered_by')->nullable(); // User or system that triggered
            $table->text('trigger_reason')->nullable();
            $table->json('trigger_metadata')->nullable();

            // Deployment configuration
            $table->enum('deployment_method', ['ssh', 'rsync', 'local', 'ansible']);
            $table->json('deployment_config'); // Method-specific configuration
            $table->json('templates_to_deploy'); // Templates included in this deployment
            $table->json('variables_used'); // Variables at time of deployment

            // Deployment status and progress
            $table->enum('status', [
                'pending', 'preparing', 'validating', 'backing_up', 'deploying',
                'restarting_services', 'verifying', 'completed', 'failed',
                'cancelled', 'rolling_back', 'rolled_back',
            ])->default('pending');
            $table->enum('priority', ['low', 'normal', 'high', 'critical'])->default('normal');
            $table->integer('progress_percentage')->default(0);
            $table->text('status_message')->nullable();
            $table->json('progress_steps')->nullable(); // Detailed progress tracking

            // Timing information
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('preparation_time_seconds')->nullable();
            $table->integer('validation_time_seconds')->nullable();
            $table->integer('backup_time_seconds')->nullable();
            $table->integer('deployment_time_seconds')->nullable();
            $table->integer('verification_time_seconds')->nullable();
            $table->integer('total_time_seconds')->nullable();

            // Deployment results
            $table->boolean('success')->default(false);
            $table->integer('templates_deployed')->default(0);
            $table->integer('templates_failed')->default(0);
            $table->integer('files_created')->default(0);
            $table->integer('files_updated')->default(0);
            $table->integer('files_deleted')->default(0);
            $table->integer('total_services_restarted')->default(0);

            // Error handling
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable();
            $table->text('error_stack_trace')->nullable();
            $table->enum('error_severity', ['low', 'medium', 'high', 'critical'])->nullable();
            $table->boolean('rollback_required')->default(false);
            $table->boolean('rollback_completed')->default(false);

            // Validation results
            $table->boolean('pre_validation_passed')->nullable();
            $table->boolean('post_validation_passed')->nullable();
            $table->json('validation_results')->nullable();
            $table->text('validation_errors')->nullable();
            $table->integer('validation_warnings_count')->default(0);

            // Backup information
            $table->boolean('backup_created')->default(false);
            $table->string('backup_location')->nullable();
            $table->integer('backup_size_bytes')->nullable();
            $table->timestamp('backup_created_at')->nullable();
            $table->boolean('backup_verified')->default(false);
            $table->text('backup_notes')->nullable();

            // Service management
            $table->json('services_to_restart')->nullable();
            $table->json('services_restart_details')->nullable();
            $table->json('service_restart_results')->nullable();
            $table->boolean('all_services_started')->nullable();
            $table->integer('service_restart_time_seconds')->nullable();

            // File deployment details
            $table->json('deployed_files'); // Files that were deployed
            $table->json('file_permissions_applied')->nullable();
            $table->json('file_checksums')->nullable(); // Verify file integrity
            $table->json('file_deployment_errors')->nullable();

            // Change tracking
            $table->json('configuration_changes'); // What actually changed
            $table->json('variable_changes')->nullable(); // Variable value changes
            $table->json('template_changes')->nullable(); // Template content changes
            $table->boolean('has_breaking_changes')->default(false);
            $table->json('breaking_change_details')->nullable();

            // Health checks and verification
            $table->boolean('health_checks_enabled')->default(false);
            $table->json('health_check_results')->nullable();
            $table->boolean('health_checks_passed')->nullable();
            $table->timestamp('health_check_completed_at')->nullable();

            // Rollback information
            $table->foreignId('rollback_deployment_id')->nullable()->constrained('config_deployments');
            $table->boolean('can_rollback')->default(false);
            $table->timestamp('rollback_deadline')->nullable();
            $table->text('rollback_notes')->nullable();
            $table->json('rollback_plan')->nullable();

            // Approval and authorization
            $table->boolean('requires_approval')->default(false);
            $table->enum('approval_status', ['pending', 'approved', 'rejected', 'not_required'])
                ->default('not_required');
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            // Deployment logs and output
            $table->longText('deployment_log')->nullable(); // Full deployment log
            $table->json('command_outputs')->nullable(); // Command outputs by step
            $table->json('debug_information')->nullable(); // Additional debug data
            $table->boolean('logs_archived')->default(false);
            $table->string('log_archive_location')->nullable();

            // Change management integration
            $table->string('change_request_id')->nullable();
            $table->string('incident_id')->nullable(); // If deployed due to incident
            $table->json('related_deployments')->nullable(); // Related deployment IDs

            // Deployment statistics
            $table->integer('retry_count')->default(0);
            $table->json('retry_history')->nullable();
            $table->decimal('cpu_usage_peak', 5, 2)->nullable();
            $table->bigInteger('memory_usage_peak_bytes')->nullable();
            $table->bigInteger('network_bytes_transferred')->nullable();

            // Environment and context
            $table->string('deployment_environment');
            $table->json('environment_snapshot')->nullable(); // System state at deployment
            $table->string('deployment_host')->nullable(); // Host that performed deployment
            $table->string('deployment_version')->nullable(); // NS version used

            // Notifications and communication
            $table->boolean('notifications_sent')->default(false);
            $table->json('notification_results')->nullable();
            $table->timestamp('notifications_sent_at')->nullable();

            // Compliance and audit
            $table->json('compliance_checks')->nullable();
            $table->boolean('compliance_passed')->nullable();
            $table->json('audit_trail')->nullable(); // Detailed audit information

            // Custom fields and metadata
            $table->json('custom_fields')->nullable();
            $table->json('metadata')->nullable();
            $table->json('tags')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index(['config_profile_id', 'status']);
            $table->index(['infrastructure_node_id', 'status']);
            $table->index(['status', 'started_at']);
            $table->index(['success', 'completed_at']);
            $table->index(['trigger_type', 'started_at']);
            $table->index(['scheduled_at']);
            $table->index(['created_at']);
            $table->index(['rollback_deployment_id']);
            $table->index(['approval_status', 'requires_approval']);
            $table->index(['deployment_environment']);

            // Composite indexes for common queries
            $table->index(['config_profile_id', 'success', 'completed_at']);
            $table->index(['infrastructure_node_id', 'status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_deployments');
    }
};
