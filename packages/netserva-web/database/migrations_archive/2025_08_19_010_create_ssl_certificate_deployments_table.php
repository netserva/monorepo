<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ssl_certificate_deployments', function (Blueprint $table) {
            $table->id();

            // Certificate and server relationship
            $table->foreignId('ssl_certificate_id')
                ->constrained('ssl_certificates')
                ->cascadeOnDelete();
            $table->foreignId('infrastructure_node_id')
                ->nullable()
                ->constrained('infrastructure_nodes') // From infrastructure manager plugin
                ->nullOnDelete();
            $table->string('server_hostname')->nullable(); // Fallback if no infrastructure node

            // Deployment configuration
            $table->string('service_type', 50); // nginx, apache, haproxy, postfix, dovecot
            $table->string('service_config_path')->nullable(); // Path to service config file
            $table->string('certificate_path'); // Where certificate was deployed
            $table->string('private_key_path'); // Where private key was deployed
            $table->string('certificate_chain_path')->nullable(); // Where chain was deployed

            // Deployment process
            $table->enum('deployment_type', ['new', 'renewal', 'rollback', 'update'])->default('new');
            $table->enum('status', ['pending', 'deploying', 'deployed', 'failed', 'rolled_back'])->default('pending');
            $table->timestamp('deployment_started_at')->nullable();
            $table->timestamp('deployment_completed_at')->nullable();
            $table->integer('deployment_duration_seconds')->nullable();

            // Deployment details
            $table->string('deployment_method', 50)->default('ssh'); // ssh, api, local
            $table->json('deployment_config')->nullable(); // SSH details, API endpoints, etc.
            $table->text('deployment_script')->nullable(); // Script used for deployment
            $table->text('deployment_log')->nullable(); // Full deployment log
            $table->text('deployment_output')->nullable(); // Command output
            $table->text('deployment_errors')->nullable(); // Any errors encountered
            $table->integer('exit_code')->nullable(); // Deployment script exit code

            // Pre/post deployment actions
            $table->json('pre_deployment_commands')->nullable(); // Commands run before deployment
            $table->json('post_deployment_commands')->nullable(); // Commands run after deployment (reload, restart)
            $table->boolean('service_restarted')->default(false);
            $table->timestamp('service_restart_at')->nullable();
            $table->text('service_restart_output')->nullable();

            // Validation and verification
            $table->boolean('validated_after_deployment')->default(false);
            $table->timestamp('validation_completed_at')->nullable();
            $table->json('validation_results')->nullable(); // SSL test results
            $table->json('health_check_results')->nullable(); // Service health after deployment

            // Backup and rollback
            $table->string('backup_path')->nullable(); // Location of backed up files
            $table->json('backup_files')->nullable(); // List of files backed up
            $table->boolean('can_rollback')->default(false);
            $table->timestamp('rollback_deadline')->nullable(); // Auto-rollback after this time if validation fails

            // User and audit information
            $table->string('deployed_by')->nullable(); // User or system that triggered deployment
            $table->string('deployment_reason')->nullable(); // new_cert, renewal, manual, etc.
            $table->string('deployment_source', 50)->default('manual'); // manual, automatic, scheduled

            // Monitoring and alerting
            $table->boolean('send_notifications')->default(true);
            $table->json('notification_channels')->nullable(); // email, slack, etc.
            $table->timestamp('notifications_sent_at')->nullable();

            // Performance and optimization
            $table->boolean('optimized_for_performance')->default(false);
            $table->json('optimization_settings')->nullable(); // OCSP stapling, session resumption, etc.

            // Certificate file permissions and ownership
            $table->string('file_owner', 50)->default('root');
            $table->string('file_group', 50)->default('root');
            $table->string('certificate_permissions', 4)->default('0644');
            $table->string('private_key_permissions', 4)->default('0600');

            $table->timestamps();

            // Indexes
            $table->index(['ssl_certificate_id', 'status']);
            $table->index(['infrastructure_node_id', 'status']);
            $table->index(['service_type', 'status']);
            $table->index(['deployment_completed_at', 'status']);
            $table->index(['deployed_by', 'created_at']);
            $table->index(['deployment_source', 'created_at']);
            $table->index(['can_rollback', 'rollback_deadline']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssl_certificate_deployments');
    }
};
