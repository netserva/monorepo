<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('config_templates', function (Blueprint $table) {
            $table->id();

            // Template identification
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('version', 20)->default('1.0.0');

            // Template categorization
            $table->enum('config_type', [
                'nginx', 'apache', 'php', 'mysql', 'postfix', 'dovecot',
                'systemd', 'yaml', 'json', 'ini', 'shell', 'custom',
            ]);
            $table->string('category')->nullable(); // web, mail, database, system, etc.
            $table->json('tags')->nullable();

            // Template content
            $table->enum('template_engine', ['twig', 'blade', 'mustache', 'simple'])->default('twig');
            $table->longText('template_content');
            $table->text('template_description')->nullable();
            $table->json('template_metadata')->nullable(); // Additional template info

            // File deployment settings
            $table->string('target_filename'); // Final filename when deployed
            $table->string('target_path'); // Path where file should be deployed
            $table->string('file_permissions', 4)->default('0644'); // Unix permissions
            $table->string('file_owner')->nullable(); // File owner
            $table->string('file_group')->nullable(); // File group

            // Template validation
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_validation')->default(true);
            $table->json('validation_rules')->nullable(); // Custom validation rules
            $table->text('syntax_check_command')->nullable(); // Command to validate syntax

            // Template requirements
            $table->json('required_variables'); // Variables that must be provided
            $table->json('optional_variables')->nullable(); // Variables that are optional
            $table->json('variable_defaults')->nullable(); // Default values for variables
            $table->json('variable_types')->nullable(); // Expected types for variables
            $table->json('variable_validation')->nullable(); // Validation rules per variable

            // Environment and compatibility
            $table->json('supported_environments')->nullable(); // prod, staging, dev, etc.
            $table->json('supported_os')->nullable(); // debian, ubuntu, centos, etc.
            $table->json('supported_versions')->nullable(); // nginx 1.18+, php 8.1+, etc.
            $table->json('dependencies')->nullable(); // Other templates this depends on

            // Deployment settings
            $table->enum('default_deployment_method', ['ssh', 'rsync', 'local', 'ansible'])->default('ssh');
            $table->boolean('requires_service_restart')->default(false);
            $table->json('restart_commands')->nullable(); // Commands to restart services
            $table->json('pre_deployment_commands')->nullable(); // Commands to run before deploy
            $table->json('post_deployment_commands')->nullable(); // Commands to run after deploy

            // Backup and rollback
            $table->boolean('enable_backup')->default(true);
            $table->string('backup_path')->nullable(); // Where to store backups
            $table->boolean('enable_rollback')->default(true);
            $table->integer('rollback_retention_count')->default(5); // Keep last N backups

            // Documentation and help
            $table->text('usage_instructions')->nullable();
            $table->text('configuration_notes')->nullable();
            $table->text('troubleshooting_guide')->nullable();
            $table->json('example_variables')->nullable(); // Example variable values
            $table->string('documentation_url')->nullable(); // Link to external docs

            // Template statistics
            $table->integer('deployment_count')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('failure_count')->default(0);
            $table->timestamp('last_deployed_at')->nullable();
            $table->decimal('success_rate', 5, 2)->default(100.00);

            // Quality and testing
            $table->enum('quality_status', ['draft', 'testing', 'stable', 'deprecated'])->default('draft');
            $table->text('quality_notes')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->json('test_results')->nullable();

            // Change management
            $table->text('changelog')->nullable();
            $table->string('created_by')->nullable();
            $table->string('maintained_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by')->nullable();

            // Template content management
            $table->longText('compiled_template')->nullable(); // Cached compiled version
            $table->timestamp('compiled_at')->nullable();
            $table->boolean('compilation_required')->default(true);
            $table->json('compilation_errors')->nullable();

            // Template relationships
            $table->foreignId('parent_template_id')->nullable()->constrained('config_templates');
            $table->boolean('is_base_template')->default(false); // Can be inherited from
            $table->json('child_templates')->nullable(); // Templates that inherit from this

            // Security settings
            $table->boolean('contains_sensitive_data')->default(false);
            $table->json('sensitive_variables')->nullable(); // Variables that should be encrypted
            $table->enum('security_level', ['public', 'internal', 'confidential', 'restricted'])
                ->default('internal');

            // Import/export
            $table->string('import_source')->nullable(); // Where template was imported from
            $table->json('export_formats')->nullable(); // Formats this template can be exported to
            $table->timestamp('imported_at')->nullable();
            $table->timestamp('exported_at')->nullable();

            // Metadata
            $table->json('custom_fields')->nullable();
            $table->json('metadata')->nullable();
            $table->integer('priority')->default(50);

            $table->timestamps();

            // Indexes
            $table->index(['config_type', 'is_active']);
            $table->index(['category', 'quality_status']);
            $table->index(['is_active', 'quality_status']);
            $table->index(['template_engine']);
            $table->index(['parent_template_id']);
            $table->index(['created_at']);
            $table->index(['last_deployed_at']);
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_templates');
    }
};
