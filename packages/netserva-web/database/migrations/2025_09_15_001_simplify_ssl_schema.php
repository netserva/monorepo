<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // Drop the monitoring table entirely
        Schema::dropIfExists('ssl_certificate_monitors');

        // Simplify ssl_certificates table - only remove columns that exist
        if (Schema::hasTable('ssl_certificates')) {
            // First drop any indexes that contain columns we're about to remove
            try {
                DB::statement('DROP INDEX IF EXISTS ssl_certificates_fingerprint_sha256_index');
            } catch (\Exception $e) {
                // Index might not exist, continue
            }

            $columnsToRemove = [];
            $enterpriseColumns = [
                'fingerprint_sha1',
                'fingerprint_sha256',
                'csr_pem',
                'signature_algorithm',
                'validity_days',
                'renewal_attempts',
                'last_renewal_attempt_at',
                'last_renewal_error',
                'acme_order_url',
                'acme_challenges',
                'acme_status',
                'deployed_to_servers',
                'last_deployed_at',
                'deployment_count',
                'status_checked_at',
                'health_check_results',
                'status_notes',
                'used_by_services',
                'is_self_signed',
                'monitor_expiry',
                'alert_on_expiry',
                'alert_days_before_expiry',
                'last_alert_sent_at',
                'validation_methods',
                'last_validated_at',
                'validation_results',
                'is_revoked',
                'revoked_at',
                'revocation_reason',
                'requested_by',
                'tags',
            ];

            foreach ($enterpriseColumns as $column) {
                if (Schema::hasColumn('ssl_certificates', $column)) {
                    $columnsToRemove[] = $column;
                }
            }

            if (! empty($columnsToRemove)) {
                Schema::table('ssl_certificates', function (Blueprint $table) use ($columnsToRemove) {
                    $table->dropColumn($columnsToRemove);
                });
            }
        }

        // Simplify ssl_certificate_authorities table - only remove columns that exist
        if (Schema::hasTable('ssl_certificate_authorities')) {
            // First drop any indexes that contain columns we're about to remove
            try {
                DB::statement('DROP INDEX IF EXISTS ssl_certificate_authorities_slug_unique');
                DB::statement('DROP INDEX IF EXISTS ssl_certificate_authorities_is_default_priority_index');
                DB::statement('DROP INDEX IF EXISTS ssl_certificate_authorities_last_certificate_issued_at_index');
            } catch (\Exception $e) {
                // Index might not exist, continue
            }

            $columnsToRemove = [];
            $enterpriseColumns = [
                'slug',
                'description',
                'acme_tos_url',
                'supports_ecc',
                'rate_limit_per_week',
                'rate_limit_per_domain',
                'rate_limit_notes',
                'intermediate_certificates',
                'priority',
                'last_validated_at',
                'validation_results',
                'validation_errors',
                'certificates_issued',
                'certificates_renewed',
                'certificates_failed',
                'last_certificate_issued_at',
            ];

            foreach ($enterpriseColumns as $column) {
                if (Schema::hasColumn('ssl_certificate_authorities', $column)) {
                    $columnsToRemove[] = $column;
                }
            }

            if (! empty($columnsToRemove)) {
                Schema::table('ssl_certificate_authorities', function (Blueprint $table) use ($columnsToRemove) {
                    $table->dropColumn($columnsToRemove);
                });
            }
        }

        // Simplify ssl_certificate_deployments table - only remove columns that exist
        if (Schema::hasTable('ssl_certificate_deployments')) {
            // First drop any indexes that contain columns we're about to remove
            try {
                DB::statement('DROP INDEX IF EXISTS ssl_certificate_deployments_deployment_source_created_at_index');
                DB::statement('DROP INDEX IF EXISTS ssl_certificate_deployments_can_rollback_rollback_deadline_index');
            } catch (\Exception $e) {
                // Index might not exist, continue
            }

            $columnsToRemove = [];
            $enterpriseColumns = [
                'service_config_path',
                'deployment_duration_seconds',
                'deployment_method',
                'deployment_script',
                'deployment_log',
                'deployment_output',
                'exit_code',
                'pre_deployment_commands',
                'post_deployment_commands',
                'service_restarted',
                'service_restart_at',
                'service_restart_output',
                'validated_after_deployment',
                'validation_completed_at',
                'validation_results',
                'health_check_results',
                'backup_path',
                'backup_files',
                'can_rollback',
                'rollback_deadline',
                'deployment_reason',
                'deployment_source',
                'send_notifications',
                'notification_channels',
                'notifications_sent_at',
                'optimized_for_performance',
                'optimization_settings',
                'file_owner',
                'file_group',
                'certificate_permissions',
                'private_key_permissions',
            ];

            foreach ($enterpriseColumns as $column) {
                if (Schema::hasColumn('ssl_certificate_deployments', $column)) {
                    $columnsToRemove[] = $column;
                }
            }

            if (! empty($columnsToRemove)) {
                Schema::table('ssl_certificate_deployments', function (Blueprint $table) use ($columnsToRemove) {
                    $table->dropColumn($columnsToRemove);
                });
            }
        }
    }

    public function down(): void
    {
        // Note: This down method is simplified - in production you might want
        // to recreate columns with appropriate defaults

        // Recreate ssl_certificates fields
        Schema::table('ssl_certificates', function (Blueprint $table) {
            $table->string('fingerprint_sha1', 40)->nullable();
            $table->string('fingerprint_sha256', 64)->nullable();
            $table->text('csr_pem')->nullable();
            $table->string('signature_algorithm', 50)->nullable();
            $table->integer('validity_days')->default(90);
            $table->integer('renewal_attempts')->default(0);
            $table->timestamp('last_renewal_attempt_at')->nullable();
            $table->text('last_renewal_error')->nullable();
            $table->string('acme_order_url')->nullable();
            $table->json('acme_challenges')->nullable();
            $table->enum('acme_status', ['pending', 'processing', 'valid', 'invalid', 'expired', 'revoked'])->nullable();
            $table->json('deployed_to_servers')->nullable();
            $table->timestamp('last_deployed_at')->nullable();
            $table->integer('deployment_count')->default(0);
            $table->timestamp('status_checked_at')->nullable();
            $table->json('health_check_results')->nullable();
            $table->text('status_notes')->nullable();
            $table->json('used_by_services')->nullable();
            $table->boolean('is_self_signed')->default(false);
            $table->boolean('monitor_expiry')->default(true);
            $table->boolean('alert_on_expiry')->default(true);
            $table->integer('alert_days_before_expiry')->default(7);
            $table->timestamp('last_alert_sent_at')->nullable();
            $table->json('validation_methods')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->json('validation_results')->nullable();
            $table->boolean('is_revoked')->default(false);
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason')->nullable();
            $table->string('requested_by')->nullable();
            $table->json('tags')->nullable();
        });

        // Recreate ssl_certificate_authorities fields
        Schema::table('ssl_certificate_authorities', function (Blueprint $table) {
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->string('acme_tos_url')->nullable();
            $table->boolean('supports_ecc')->default(false);
            $table->integer('rate_limit_per_week')->nullable();
            $table->integer('rate_limit_per_domain')->nullable();
            $table->text('rate_limit_notes')->nullable();
            $table->json('intermediate_certificates')->nullable();
            $table->integer('priority')->default(0);
            $table->timestamp('last_validated_at')->nullable();
            $table->json('validation_results')->nullable();
            $table->text('validation_errors')->nullable();
            $table->integer('certificates_issued')->default(0);
            $table->integer('certificates_renewed')->default(0);
            $table->integer('certificates_failed')->default(0);
            $table->timestamp('last_certificate_issued_at')->nullable();
        });

        // Recreate ssl_certificate_deployments fields
        Schema::table('ssl_certificate_deployments', function (Blueprint $table) {
            $table->string('service_config_path')->nullable();
            $table->integer('deployment_duration_seconds')->nullable();
            $table->string('deployment_method', 20)->default('ssh');
            $table->text('deployment_script')->nullable();
            $table->text('deployment_log')->nullable();
            $table->text('deployment_output')->nullable();
            $table->integer('exit_code')->nullable();
            $table->json('pre_deployment_commands')->nullable();
            $table->json('post_deployment_commands')->nullable();
            $table->boolean('service_restarted')->default(false);
            $table->timestamp('service_restart_at')->nullable();
            $table->text('service_restart_output')->nullable();
            $table->boolean('validated_after_deployment')->default(false);
            $table->timestamp('validation_completed_at')->nullable();
            $table->json('validation_results')->nullable();
            $table->json('health_check_results')->nullable();
            $table->string('backup_path')->nullable();
            $table->json('backup_files')->nullable();
            $table->boolean('can_rollback')->default(false);
            $table->timestamp('rollback_deadline')->nullable();
            $table->string('deployment_reason')->nullable();
            $table->enum('deployment_source', ['manual', 'automatic', 'scheduled', 'renewal'])->default('manual');
            $table->boolean('send_notifications')->default(false);
            $table->json('notification_channels')->nullable();
            $table->timestamp('notifications_sent_at')->nullable();
            $table->boolean('optimized_for_performance')->default(false);
            $table->json('optimization_settings')->nullable();
            $table->string('file_owner', 50)->default('www-data');
            $table->string('file_group', 50)->default('www-data');
            $table->string('certificate_permissions', 4)->default('0644');
            $table->string('private_key_permissions', 4)->default('0600');
        });

        // Recreate monitoring table
        Schema::create('ssl_certificate_monitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ssl_certificate_id')
                ->constrained('ssl_certificates')
                ->cascadeOnDelete();
            $table->string('monitor_url');
            $table->string('hostname');
            $table->integer('port')->default(443);
            $table->string('protocol', 10)->default('https');
            $table->boolean('is_active')->default(true);
            $table->integer('check_interval_minutes')->default(60);
            $table->integer('timeout_seconds')->default(30);
            $table->boolean('verify_chain')->default(true);
            $table->boolean('check_revocation')->default(false);
            $table->boolean('check_sct')->default(false);
            $table->integer('expiry_warning_days')->default(30);
            $table->integer('expiry_critical_days')->default(7);
            $table->boolean('alert_on_chain_issues')->default(true);
            $table->boolean('alert_on_revocation')->default(true);
            $table->boolean('alert_on_connection_failure')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->enum('last_check_status', ['success', 'warning', 'critical', 'unknown', 'failed'])->nullable();
            $table->text('last_check_message')->nullable();
            $table->json('last_check_details')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->string('detected_common_name')->nullable();
            $table->json('detected_san_list')->nullable();
            $table->string('detected_issuer')->nullable();
            $table->timestamp('detected_not_valid_before')->nullable();
            $table->timestamp('detected_not_valid_after')->nullable();
            $table->string('detected_signature_algorithm')->nullable();
            $table->string('detected_fingerprint_sha256', 64)->nullable();
            $table->integer('detected_days_until_expiry')->nullable();
            $table->boolean('chain_valid')->nullable();
            $table->json('chain_issues')->nullable();
            $table->boolean('self_signed_detected')->default(false);
            $table->json('trust_store_validation')->nullable();
            $table->enum('revocation_status', ['good', 'revoked', 'unknown', 'no_check'])->default('no_check');
            $table->timestamp('revocation_checked_at')->nullable();
            $table->json('ocsp_response')->nullable();
            $table->boolean('ocsp_stapling_enabled')->default(false);
            $table->json('supported_protocols')->nullable();
            $table->json('cipher_suites')->nullable();
            $table->json('security_headers')->nullable();
            $table->string('tls_version_negotiated')->nullable();
            $table->string('cipher_suite_negotiated')->nullable();
            $table->boolean('forward_secrecy_supported')->default(false);
            $table->json('sct_list')->nullable();
            $table->integer('ct_logs_count')->nullable();
            $table->timestamp('ct_last_checked_at')->nullable();
            $table->integer('total_checks_performed')->default(0);
            $table->integer('successful_checks')->default(0);
            $table->integer('failed_checks')->default(0);
            $table->integer('consecutive_failures')->default(0);
            $table->timestamp('first_failure_at')->nullable();
            $table->decimal('uptime_percentage', 5, 2)->default(100.00);
            $table->timestamp('last_expiry_warning_sent_at')->nullable();
            $table->timestamp('last_critical_alert_sent_at')->nullable();
            $table->timestamp('last_failure_alert_sent_at')->nullable();
            $table->json('alert_recipients')->nullable();
            $table->string('check_location')->nullable();
            $table->json('ip_addresses_resolved')->nullable();
            $table->boolean('cdn_detected')->default(false);
            $table->string('cdn_provider')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'last_checked_at']);
            $table->index(['hostname', 'port']);
            $table->index(['last_check_status', 'is_active']);
            $table->index(['detected_not_valid_after', 'is_active']);
        });
    }
};
