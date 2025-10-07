<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('config_variables', function (Blueprint $table) {
            $table->id();

            // Variable identification
            $table->string('name'); // Variable name (e.g., database_host)
            $table->string('key')->index(); // Full key path (e.g., database.host)
            $table->text('description')->nullable();

            // Scope and targeting
            $table->enum('scope', ['global', 'environment', 'node', 'profile', 'template'])
                ->default('global');
            $table->string('environment')->nullable(); // production, staging, development
            $table->foreignId('infrastructure_node_id')
                ->nullable()
                ->constrained('infrastructure_nodes')
                ->cascadeOnDelete();
            $table->foreignId('config_profile_id')
                ->nullable()
                ->constrained('config_profiles')
                ->cascadeOnDelete();
            $table->foreignId('config_template_id')
                ->nullable()
                ->constrained('config_templates')
                ->cascadeOnDelete();

            // Variable value and type
            $table->longText('value');
            $table->enum('value_type', [
                'string', 'integer', 'float', 'boolean', 'array', 'object', 'json',
            ])->default('string');
            $table->longText('default_value')->nullable();
            $table->boolean('is_required')->default(false);

            // Security and encryption
            $table->boolean('is_sensitive')->default(false);
            $table->boolean('is_encrypted')->default(false);
            $table->string('encryption_key_id')->nullable(); // Key used for encryption
            $table->enum('sensitivity_level', ['public', 'internal', 'confidential', 'secret'])
                ->default('internal');

            // Variable properties
            $table->boolean('is_active')->default(true);
            $table->boolean('is_readonly')->default(false);
            $table->boolean('is_system_variable')->default(false); // System-generated variable
            $table->integer('priority')->default(50); // Override priority

            // Validation rules
            $table->json('validation_rules')->nullable(); // Custom validation rules
            $table->string('regex_pattern')->nullable(); // Regex validation
            $table->text('allowed_values')->nullable(); // Comma-separated allowed values
            $table->decimal('min_value', 20, 6)->nullable(); // For numeric values
            $table->decimal('max_value', 20, 6)->nullable(); // For numeric values
            $table->integer('min_length')->nullable(); // For string values
            $table->integer('max_length')->nullable(); // For string values

            // Source and provenance
            $table->enum('source_type', [
                'manual', 'environment', 'file', 'database', 'vault',
                'secrets_manager', 'api', 'calculated', 'imported',
            ])->default('manual');
            $table->string('source_location')->nullable(); // Where value comes from
            $table->json('source_metadata')->nullable(); // Additional source info
            $table->timestamp('source_updated_at')->nullable();

            // Value history and versioning
            $table->string('version', 20)->default('1.0');
            $table->json('value_history')->nullable(); // Previous values with timestamps
            $table->text('change_reason')->nullable(); // Why value was changed
            $table->timestamp('last_changed_at')->nullable();
            $table->string('last_changed_by')->nullable();

            // Usage tracking
            $table->json('used_by_templates')->nullable(); // Templates that use this variable
            $table->json('used_by_profiles')->nullable(); // Profiles that use this variable
            $table->integer('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();

            // Inheritance and overrides
            $table->foreignId('parent_variable_id')
                ->nullable()
                ->constrained('config_variables')
                ->nullOnDelete();
            $table->boolean('inherits_from_parent')->default(false);
            $table->boolean('can_be_overridden')->default(true);
            $table->json('override_rules')->nullable(); // Rules for overriding

            // Dependencies and relationships
            $table->json('depends_on_variables')->nullable(); // Variables this depends on
            $table->json('affects_variables')->nullable(); // Variables affected by this one
            $table->text('dependency_expression')->nullable(); // Complex dependency logic

            // Computed variables
            $table->boolean('is_computed')->default(false);
            $table->text('computation_expression')->nullable(); // How to compute value
            $table->json('computation_dependencies')->nullable(); // Variables used in computation
            $table->boolean('auto_recompute')->default(true);
            $table->timestamp('last_computed_at')->nullable();

            // Deployment tracking
            $table->integer('deployment_count')->default(0);
            $table->timestamp('last_deployed_at')->nullable();
            $table->json('deployment_errors')->nullable(); // Recent deployment errors

            // Validation and testing
            $table->boolean('validation_enabled')->default(true);
            $table->json('validation_results')->nullable();
            $table->boolean('last_validation_passed')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->text('validation_error_message')->nullable();

            // Documentation and help
            $table->text('help_text')->nullable();
            $table->json('examples')->nullable(); // Example values
            $table->string('documentation_url')->nullable();
            $table->json('related_variables')->nullable(); // Related variable suggestions

            // Environment-specific overrides
            $table->json('environment_overrides')->nullable(); // Value overrides per environment
            $table->json('node_overrides')->nullable(); // Value overrides per node
            $table->boolean('has_overrides')->default(false);

            // Expiration and lifecycle
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_expired')->default(false);
            $table->enum('lifecycle_status', ['active', 'deprecated', 'archived', 'deleted'])
                ->default('active');
            $table->text('deprecation_notice')->nullable();

            // Import/export tracking
            $table->string('import_source')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->json('export_history')->nullable();
            $table->timestamp('last_exported_at')->nullable();

            // Compliance and governance
            $table->json('compliance_tags')->nullable(); // GDPR, HIPAA, etc.
            $table->boolean('requires_approval')->default(false);
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            // Monitoring and alerting
            $table->boolean('monitor_changes')->default(false);
            $table->json('alert_thresholds')->nullable(); // Alert on value changes
            $table->timestamp('last_alert_sent_at')->nullable();

            // Custom fields and metadata
            $table->json('custom_attributes')->nullable();
            $table->json('metadata')->nullable();
            $table->json('tags')->nullable();

            // Audit information
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index(['scope', 'is_active']);
            $table->index(['environment', 'is_active']);
            $table->index(['infrastructure_node_id', 'is_active']);
            $table->index(['config_profile_id', 'is_active']);
            $table->index(['config_template_id', 'is_active']);
            $table->index(['is_sensitive', 'is_encrypted']);
            $table->index(['source_type', 'is_active']);
            $table->index(['parent_variable_id']);
            $table->index(['is_computed', 'auto_recompute']);
            $table->index(['lifecycle_status', 'is_active']);
            $table->index(['expires_at', 'is_expired']);
            $table->index(['last_used_at']);
            $table->index(['created_at']);
            $table->index('priority');

            // Composite indexes
            $table->index(['scope', 'environment', 'is_active']);
            $table->index(['key', 'scope', 'environment']);

            // Unique constraints
            $table->unique(['key', 'scope', 'environment', 'infrastructure_node_id', 'config_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_variables');
    }
};
