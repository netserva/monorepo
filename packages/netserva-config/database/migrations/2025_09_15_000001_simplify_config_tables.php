<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the entire config_changes table - enterprise bloat
        Schema::dropIfExists('config_changes');

        // Check if this is a fresh installation - if config_templates has no enterprise columns, skip this migration
        if (! Schema::hasColumn('config_templates', 'version')) {
            return; // Fresh installation, nothing to simplify
        }

        // Simplify config_templates table by removing enterprise fields
        $this->dropColumnsIfExist('config_templates', [
            'version',
            'template_engine',
            'template_description',
            'template_metadata',
            'requires_validation',
            'validation_rules',
            'optional_variables',
            'variable_types',
            'variable_validation',
            'supported_environments',
            'supported_os',
            'supported_versions',
            'dependencies',
            'default_deployment_method',
            'pre_deployment_commands',
            'post_deployment_commands',
            'backup_path',
            'enable_rollback',
            'rollback_retention_count',
            'usage_instructions',
            'configuration_notes',
            'troubleshooting_guide',
            'example_variables',
            'documentation_url',
            'deployment_count',
            'success_count',
            'failure_count',
            'last_deployed_at',
            'success_rate',
            'quality_status',
            'quality_notes',
            'last_tested_at',
            'test_results',
            'changelog',
            'created_by',
            'maintained_by',
            'approved_at',
            'approved_by',
            'compiled_template',
            'compiled_at',
            'compilation_required',
            'compilation_errors',
            'parent_template_id',
            'is_base_template',
            'child_templates',
            'contains_sensitive_data',
            'sensitive_variables',
            'security_level',
            'import_source',
            'export_formats',
            'imported_at',
            'exported_at',
            'custom_fields',
            'priority',
        ]);

        // Simplify config_profiles table by removing enterprise deployment orchestration
        $this->dropColumnsIfExist('config_profiles', [
            'environment_tier',
            'environment_tags',
            'priority',
            'template_order',
            'template_conditions',
            'template_variables',
            'variable_overrides',
            'encrypted_variables',
            'deployment_config',
            'deployment_user',
            'deployment_key_id',
            'enable_atomic_deployment',
            'enable_rollback_on_failure',
            'require_approval',
            'deployment_timeout_minutes',
            'max_retry_attempts',
            'service_restart_order',
            'verify_services_after_restart',
            'service_startup_timeout',
            'backup_location',
            'backup_retention_days',
            'enable_automatic_rollback',
            'rollback_conditions',
            'created_by',
            'approved_by',
            'approved_at',
            'custom_metadata',
        ]);

        // Simplify config_deployments table by removing enterprise tracking
        $this->dropColumnsIfExist('config_deployments', [
            'trigger_type',
            'triggered_by',
            'trigger_reason',
            'trigger_metadata',
            'deployment_config',
            'priority',
            'progress_percentage',
            'status_message',
            'progress_steps',
            'scheduled_at',
            'deployment_time_seconds',
            'total_time_seconds',
            'templates_deployed',
            'templates_failed',
            'files_created',
            'files_updated',
            'files_deleted',
            'total_services_restarted',
            'error_details',
            'rollback_required',
            'rollback_completed',
            'file_permissions_applied',
            'file_checksums',
            'file_deployment_errors',
            'configuration_changes',
            'variable_changes',
            'template_changes',
            'deployment_host',
            'deployment_version',
            'approval_status',
            'approved_by',
            'approved_at',
            'custom_fields',
            'metadata',
            'tags',
        ]);
    }

    private function dropColumnsIfExist(string $table, array $columns): void
    {
        Schema::table($table, function (Blueprint $tableBlueprint) use ($table, $columns) {
            // Drop foreign keys first if they exist
            if ($table === 'config_templates') {
                try {
                    $tableBlueprint->dropForeign(['parent_template_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist, continue
                }

                // Drop ALL indexes from the original config_templates table
                $indexesToDrop = [
                    ['config_type', 'is_active'],
                    ['category', 'quality_status'],
                    ['is_active', 'quality_status'],
                    ['template_engine'],
                    ['parent_template_id'],
                    ['created_at'],
                    ['last_deployed_at'],
                    ['priority'],
                ];

                foreach ($indexesToDrop as $index) {
                    try {
                        $tableBlueprint->dropIndex($index);
                    } catch (\Exception $e) {
                        // Index might not exist, continue
                    }
                }
            }

            if ($table === 'config_profiles') {
                // Drop foreign key first
                try {
                    $tableBlueprint->dropForeign(['parent_profile_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist, continue
                }

                // Drop indexes that reference priority column
                try {
                    $tableBlueprint->dropIndex(['priority']);
                } catch (\Exception $e) {
                    // Index might not exist, continue
                }
            }

            if ($table === 'config_deployments') {
                // Drop self-referencing foreign key first
                try {
                    $tableBlueprint->dropForeign(['rollback_deployment_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist, continue
                }

                // Drop indexes that reference columns we're dropping
                $indexesToDrop = [
                    ['trigger_type', 'started_at'],
                    ['scheduled_at'],
                    ['rollback_deployment_id'],
                    ['approval_status', 'requires_approval'],
                    ['deployment_environment'],
                    ['config_profile_id', 'success', 'completed_at'],
                    ['infrastructure_node_id', 'status', 'started_at'],
                ];

                foreach ($indexesToDrop as $index) {
                    try {
                        $tableBlueprint->dropIndex($index);
                    } catch (\Exception $e) {
                        // Index might not exist, continue
                    }
                }
            }

            $existingColumns = [];
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $existingColumns[] = $column;
                }
            }

            if (! empty($existingColumns)) {
                $tableBlueprint->dropColumn($existingColumns);
            }
        });
    }

    public function down(): void
    {
        // This migration removes enterprise fields - rollback would be complex
        // and not recommended. Keep core functionality only.
        throw new \Exception('Cannot rollback config simplification migration');
    }
};
