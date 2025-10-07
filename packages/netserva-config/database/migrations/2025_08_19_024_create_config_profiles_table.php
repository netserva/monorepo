<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('config_profiles', function (Blueprint $table) {
            $table->id();

            // Profile identification
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Infrastructure targeting
            $table->foreignId('infrastructure_node_id')
                ->constrained('infrastructure_nodes')
                ->cascadeOnDelete();

            // Environment classification
            $table->enum('environment', ['production', 'staging', 'development', 'testing', 'sandbox'])
                ->default('development');
            $table->string('environment_tier')->nullable(); // web-tier, db-tier, cache-tier
            $table->json('environment_tags')->nullable(); // Additional environment metadata

            // Profile configuration
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false); // Default profile for this node
            $table->integer('priority')->default(50); // Profile priority (higher = more important)

            // Template associations
            $table->json('template_assignments'); // Which templates to deploy
            $table->json('template_order')->nullable(); // Deployment order for templates
            $table->json('template_conditions')->nullable(); // Conditions for template deployment

            // Variable configuration
            $table->json('global_variables'); // Variables available to all templates
            $table->json('template_variables')->nullable(); // Variables specific to each template
            $table->json('variable_overrides')->nullable(); // Override template defaults
            $table->json('encrypted_variables')->nullable(); // Encrypted sensitive variables

            // Deployment configuration
            $table->enum('deployment_method', ['ssh', 'rsync', 'local', 'ansible'])->default('ssh');
            $table->json('deployment_config')->nullable(); // Method-specific configuration
            $table->string('deployment_user')->nullable(); // User for remote deployments
            $table->string('deployment_key_id')->nullable(); // SSH key for authentication

            // Deployment behavior
            $table->boolean('enable_atomic_deployment')->default(true);
            $table->boolean('enable_rollback_on_failure')->default(true);
            $table->boolean('require_approval')->default(false);
            $table->integer('deployment_timeout_minutes')->default(30);
            $table->integer('max_retry_attempts')->default(3);

            // Service management
            $table->json('services_to_restart')->nullable(); // Services that should be restarted
            $table->json('service_restart_order')->nullable(); // Order for service restarts
            $table->boolean('verify_services_after_restart')->default(true);
            $table->integer('service_startup_timeout')->default(60);

            // Backup and recovery
            $table->boolean('create_backup_before_deploy')->default(true);
            $table->string('backup_location')->nullable(); // Custom backup location
            $table->integer('backup_retention_days')->default(7);
            $table->boolean('enable_automatic_rollback')->default(false);
            $table->json('rollback_conditions')->nullable(); // When to auto-rollback

            // Validation settings
            $table->boolean('validate_before_deploy')->default(true);
            $table->boolean('validate_after_deploy')->default(true);
            $table->json('custom_validation_commands')->nullable();
            $table->boolean('skip_validation_on_errors')->default(false);

            // Scheduling and automation
            $table->boolean('enable_scheduled_deployment')->default(false);
            $table->string('deployment_schedule')->nullable(); // Cron expression
            $table->json('maintenance_windows')->nullable(); // When deployments are allowed
            $table->boolean('auto_deploy_on_template_change')->default(false);

            // Profile state tracking
            $table->enum('status', ['inactive', 'active', 'deploying', 'failed', 'maintenance'])
                ->default('inactive');
            $table->timestamp('last_deployment_at')->nullable();
            $table->timestamp('next_scheduled_deployment_at')->nullable();
            $table->integer('deployment_count')->default(0);
            $table->integer('successful_deployments')->default(0);
            $table->integer('failed_deployments')->default(0);
            $table->decimal('success_rate', 5, 2)->default(0);

            // Current deployment tracking
            $table->string('current_deployment_id')->nullable();
            $table->enum('deployment_status', [
                'idle', 'pending', 'validating', 'backing_up', 'deploying',
                'restarting_services', 'verifying', 'completed', 'failed', 'rolling_back',
            ])->default('idle');
            $table->text('deployment_message')->nullable();
            $table->timestamp('deployment_started_at')->nullable();
            $table->timestamp('deployment_completed_at')->nullable();

            // Error handling and debugging
            $table->json('last_deployment_errors')->nullable();
            $table->json('deployment_logs')->nullable(); // Recent deployment logs
            $table->text('last_error_message')->nullable();
            $table->timestamp('last_error_at')->nullable();

            // Health and monitoring
            $table->boolean('enable_health_checks')->default(true);
            $table->json('health_check_commands')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->enum('health_status', ['healthy', 'unhealthy', 'unknown'])->default('unknown');
            $table->text('health_check_message')->nullable();

            // Configuration drift detection
            $table->boolean('monitor_configuration_drift')->default(false);
            $table->timestamp('last_drift_check_at')->nullable();
            $table->boolean('configuration_drift_detected')->default(false);
            $table->json('drift_details')->nullable();

            // Notification settings
            $table->boolean('enable_notifications')->default(true);
            $table->json('notification_channels')->nullable(); // Override global channels
            $table->json('notification_events')->nullable(); // Which events to notify on
            $table->json('notification_contacts')->nullable(); // Specific contacts for this profile

            // Change management
            $table->json('change_requests')->nullable(); // Associated change requests
            $table->boolean('requires_change_approval')->default(false);
            $table->string('change_approval_workflow')->nullable();

            // Security and compliance
            $table->enum('security_classification', ['public', 'internal', 'confidential', 'restricted'])
                ->default('internal');
            $table->json('compliance_requirements')->nullable(); // SOC2, HIPAA, etc.
            $table->boolean('audit_all_deployments')->default(true);

            // Profile inheritance
            $table->foreignId('parent_profile_id')->nullable()->constrained('config_profiles');
            $table->boolean('inherit_parent_variables')->default(false);
            $table->boolean('inherit_parent_templates')->default(false);
            $table->json('inheritance_overrides')->nullable();

            // Documentation and metadata
            $table->text('deployment_notes')->nullable();
            $table->text('configuration_documentation')->nullable();
            $table->json('custom_metadata')->nullable();
            $table->json('tags')->nullable();

            // Audit information
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['infrastructure_node_id', 'is_active']);
            $table->index(['environment', 'is_active']);
            $table->index(['status', 'deployment_status']);
            $table->index(['is_default', 'infrastructure_node_id']);
            $table->index(['last_deployment_at']);
            $table->index(['next_scheduled_deployment_at']);
            $table->index(['enable_scheduled_deployment', 'next_scheduled_deployment_at']);
            $table->index(['parent_profile_id']);
            $table->index(['created_at']);
            $table->index('priority');

            // Unique constraint
            $table->unique(['infrastructure_node_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_profiles');
    }
};
