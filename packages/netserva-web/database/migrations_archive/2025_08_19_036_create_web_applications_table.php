<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_applications', function (Blueprint $table) {
            $table->id();

            // Application identification
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();

            // Virtual host association
            $table->foreignId('virtual_host_id')
                ->nullable()
                ->constrained('virtual_hosts')
                ->cascadeOnDelete();

            // Application type and framework
            $table->enum('application_type', [
                'wordpress', 'laravel', 'symfony', 'drupal', 'joomla', 'magento',
                'nodejs', 'react', 'vue', 'angular', 'nextjs', 'nuxtjs',
                'static', 'custom',
            ])->default('static');
            $table->string('framework_version')->nullable();
            $table->json('requirements')->nullable(); // System requirements

            // Installation and deployment
            $table->enum('installation_method', ['manual', 'auto', 'git', 'composer', 'npm'])
                ->default('manual');
            $table->string('repository_url')->nullable();
            $table->string('repository_branch')->default('main');
            $table->json('repository_credentials')->nullable(); // Encrypted
            $table->string('installation_path');
            $table->enum('installation_status', ['pending', 'installing', 'installed', 'failed', 'updating'])
                ->default('pending');
            $table->timestamp('installed_at')->nullable();
            $table->text('installation_log')->nullable();

            // Version management
            $table->string('current_version')->nullable();
            $table->string('available_version')->nullable();
            $table->boolean('auto_update_enabled')->default(false);
            $table->enum('update_channel', ['stable', 'beta', 'nightly'])->default('stable');
            $table->timestamp('last_update_check')->nullable();
            $table->timestamp('last_updated_at')->nullable();
            $table->json('update_history')->nullable();

            // Configuration management
            $table->json('configuration')->nullable(); // Application-specific config
            $table->json('environment_variables')->nullable();
            $table->json('config_files')->nullable(); // List of config file paths
            $table->boolean('config_managed')->default(true);
            $table->string('config_template')->nullable();
            $table->timestamp('config_last_updated')->nullable();

            // Database configuration
            $table->boolean('database_required')->default(false);
            $table->enum('database_type', ['mysql', 'postgresql', 'sqlite', 'mongodb'])
                ->nullable();
            $table->string('database_host')->nullable();
            $table->integer('database_port')->nullable();
            $table->string('database_name')->nullable();
            $table->string('database_user')->nullable();
            $table->string('database_password')->nullable(); // Encrypted
            $table->json('database_config')->nullable();
            $table->boolean('database_migrations_enabled')->default(true);

            // PHP configuration (for PHP applications)
            $table->string('php_version')->nullable();
            $table->json('php_extensions')->nullable(); // Required PHP extensions
            $table->json('php_settings')->nullable(); // Custom PHP.ini settings
            $table->string('composer_version')->nullable();
            $table->boolean('composer_install_dev')->default(false);

            // Node.js configuration (for Node applications)
            $table->string('nodejs_version')->nullable();
            $table->string('npm_version')->nullable();
            $table->json('npm_scripts')->nullable(); // Custom npm scripts
            $table->string('process_manager')->nullable(); // pm2, forever, etc.
            $table->json('process_config')->nullable();

            // Build and compilation
            $table->boolean('build_required')->default(false);
            $table->string('build_command')->nullable();
            $table->string('build_directory')->nullable();
            $table->json('build_artifacts')->nullable(); // Generated files/directories
            $table->enum('build_status', ['pending', 'building', 'success', 'failed'])
                ->default('pending');
            $table->timestamp('last_build_at')->nullable();
            $table->text('build_log')->nullable();

            // Asset management
            $table->boolean('asset_compilation')->default(false);
            $table->string('asset_compiler')->nullable(); // webpack, vite, gulp, etc.
            $table->json('asset_config')->nullable();
            $table->boolean('minification_enabled')->default(true);
            $table->boolean('css_preprocessing')->default(false);
            $table->string('css_preprocessor')->nullable(); // sass, less, stylus

            // Performance optimization
            $table->boolean('caching_enabled')->default(true);
            $table->json('cache_config')->nullable();
            $table->boolean('compression_enabled')->default(true);
            $table->boolean('cdn_enabled')->default(false);
            $table->json('cdn_config')->nullable();
            $table->boolean('lazy_loading_enabled')->default(false);

            // Security configuration
            $table->boolean('security_headers_enabled')->default(true);
            $table->json('security_config')->nullable();
            $table->boolean('csrf_protection')->default(true);
            $table->boolean('xss_protection')->default(true);
            $table->boolean('sql_injection_protection')->default(true);
            $table->json('security_rules')->nullable();

            // Backup and recovery
            $table->boolean('backup_enabled')->default(true);
            $table->string('backup_schedule')->default('0 4 * * *'); // Daily at 4 AM
            $table->integer('backup_retention_days')->default(14);
            $table->boolean('backup_database')->default(true);
            $table->boolean('backup_files')->default(true);
            $table->json('backup_excludes')->nullable();
            $table->timestamp('last_backup_at')->nullable();

            // Monitoring and logging
            $table->boolean('monitoring_enabled')->default(true);
            $table->json('monitoring_config')->nullable();
            $table->boolean('error_tracking_enabled')->default(true);
            $table->string('error_tracking_service')->nullable(); // sentry, bugsnag, etc.
            $table->json('logging_config')->nullable();
            $table->string('log_level')->default('error');

            // Performance metrics
            $table->decimal('average_response_time', 8, 2)->nullable();
            $table->decimal('average_memory_usage', 8, 2)->nullable(); // MB
            $table->decimal('average_cpu_usage', 5, 2)->nullable(); // Percentage
            $table->integer('daily_requests')->default(0);
            $table->integer('monthly_requests')->default(0);
            $table->bigInteger('storage_used_bytes')->default(0);

            // Health and status
            $table->enum('health_status', ['healthy', 'warning', 'error', 'maintenance'])
                ->default('healthy');
            $table->text('health_message')->nullable();
            $table->timestamp('last_health_check')->nullable();
            $table->json('health_checks')->nullable(); // Individual check results
            $table->boolean('is_responding')->default(true);
            $table->integer('uptime_percentage')->default(100);

            // Application-specific settings
            $table->json('wordpress_config')->nullable(); // WordPress-specific settings
            $table->json('laravel_config')->nullable(); // Laravel-specific settings
            $table->json('nodejs_config')->nullable(); // Node.js-specific settings
            $table->json('static_config')->nullable(); // Static site settings

            // Deployment hooks
            $table->json('pre_deployment_hooks')->nullable();
            $table->json('post_deployment_hooks')->nullable();
            $table->json('rollback_hooks')->nullable();
            $table->boolean('zero_downtime_deployment')->default(false);
            $table->integer('deployment_timeout')->default(300); // Seconds

            // Testing and QA
            $table->boolean('testing_enabled')->default(false);
            $table->string('test_command')->nullable();
            $table->enum('test_framework', ['phpunit', 'jest', 'mocha', 'cypress', 'custom'])
                ->nullable();
            $table->json('test_config')->nullable();
            $table->timestamp('last_test_run')->nullable();
            $table->enum('last_test_result', ['passed', 'failed', 'pending'])->nullable();

            // Staging and environments
            $table->boolean('staging_enabled')->default(false);
            $table->string('staging_url')->nullable();
            $table->json('environment_config')->nullable(); // Different env settings
            $table->enum('current_environment', ['development', 'staging', 'production'])
                ->default('production');

            // License and compliance
            $table->string('license')->nullable();
            $table->json('compliance_requirements')->nullable();
            $table->boolean('gdpr_compliant')->default(false);
            $table->json('privacy_config')->nullable();
            $table->timestamp('license_expires_at')->nullable();

            // Custom plugins/modules
            $table->json('plugins_installed')->nullable();
            $table->json('modules_enabled')->nullable();
            $table->json('themes_available')->nullable();
            $table->string('active_theme')->nullable();
            $table->boolean('plugin_auto_updates')->default(false);

            // API and integrations
            $table->boolean('api_enabled')->default(false);
            $table->string('api_version')->nullable();
            $table->json('api_config')->nullable();
            $table->json('webhooks')->nullable();
            $table->json('external_integrations')->nullable();
            $table->json('api_keys')->nullable(); // Encrypted

            // Maintenance and lifecycle
            $table->boolean('maintenance_mode')->default(false);
            $table->text('maintenance_message')->nullable();
            $table->timestamp('maintenance_start')->nullable();
            $table->timestamp('maintenance_end')->nullable();
            $table->boolean('scheduled_maintenance')->default(false);
            $table->string('maintenance_window')->nullable();

            // Metadata and audit
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->string('owner')->nullable();
            $table->string('developer')->nullable();
            $table->string('maintainer')->nullable();
            $table->text('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['virtual_host_id', 'application_type']);
            $table->index(['slug', 'virtual_host_id']);
            $table->index(['installation_status', 'installed_at']);
            $table->index(['health_status', 'last_health_check']);
            $table->index(['auto_update_enabled', 'last_update_check']);
            $table->index(['backup_enabled', 'last_backup_at']);
            $table->index(['maintenance_mode', 'maintenance_end']);
            $table->index(['owner', 'application_type']);
            $table->index(['current_environment', 'health_status']);
            $table->index(['created_at']);

            // Unique constraints
            $table->unique(['slug', 'virtual_host_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_applications');
    }
};
