<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_servers', function (Blueprint $table) {
            $table->id();

            // Server identification
            $table->string('name');
            $table->string('hostname');
            $table->text('description')->nullable();

            // Infrastructure association
            $table->foreignId('infrastructure_node_id')
                ->constrained('infrastructure_nodes')
                ->cascadeOnDelete();

            // Server type and configuration
            $table->enum('server_type', ['nginx', 'apache', 'lighttpd', 'caddy', 'custom'])
                ->default('nginx');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_primary')->default(false);
            $table->string('version')->nullable();

            // Network configuration
            $table->string('public_ip')->nullable();
            $table->json('listen_addresses')->nullable(); // IP addresses to bind to
            $table->json('port_config'); // HTTP, HTTPS, custom ports
            $table->string('server_name')->nullable(); // Default server name

            // Path configuration
            $table->string('config_path'); // Main config directory
            $table->string('sites_path'); // Sites configuration directory
            $table->string('enabled_path'); // Enabled sites directory
            $table->string('document_root')->default('/var/www');
            $table->string('log_path')->default('/var/log');

            // SSL/TLS configuration
            $table->boolean('enable_ssl')->default(true);
            $table->string('ssl_cert_path')->nullable();
            $table->string('ssl_key_path')->nullable();
            $table->json('ssl_protocols')->nullable(); // Supported TLS versions
            $table->string('ssl_ciphers')->nullable();
            $table->boolean('ssl_redirect')->default(true);
            $table->boolean('hsts_enabled')->default(true);
            $table->integer('hsts_max_age')->default(31536000);

            // Performance settings
            $table->integer('worker_processes')->default(4);
            $table->integer('worker_connections')->default(1024);
            $table->integer('keepalive_timeout')->default(65);
            $table->string('client_max_body_size')->default('64M');
            $table->boolean('gzip_enabled')->default(true);
            $table->json('gzip_types')->nullable();

            // Security configuration
            $table->boolean('server_tokens')->default(false);
            $table->json('security_headers')->nullable();
            $table->boolean('rate_limiting_enabled')->default(true);
            $table->string('rate_limit_zone')->nullable();
            $table->string('rate_limit_rate')->default('10r/s');
            $table->integer('rate_limit_burst')->default(20);

            // PHP-FPM configuration
            $table->boolean('php_enabled')->default(true);
            $table->string('default_php_version')->default('8.3');
            $table->json('php_versions')->nullable(); // Available PHP versions
            $table->json('php_modules')->nullable(); // Installed PHP modules

            // Caching configuration
            $table->boolean('caching_enabled')->default(true);
            $table->enum('cache_type', ['fastcgi', 'proxy', 'redis', 'memcached'])->default('fastcgi');
            $table->json('cache_config')->nullable();
            $table->string('cache_path')->nullable();
            $table->string('cache_zone_size')->default('10m');

            // Load balancing
            $table->boolean('load_balancing_enabled')->default(false);
            $table->enum('lb_method', ['round_robin', 'least_conn', 'ip_hash', 'least_time', 'random'])
                ->default('round_robin');
            $table->boolean('health_checks_enabled')->default(true);
            $table->integer('health_check_interval')->default(30);
            $table->integer('fail_timeout')->default(30);
            $table->integer('max_fails')->default(3);

            // Logging configuration
            $table->boolean('access_log_enabled')->default(true);
            $table->string('access_log_format')->default('combined');
            $table->string('access_log_path')->nullable();
            $table->boolean('error_log_enabled')->default(true);
            $table->string('error_log_level')->default('error');
            $table->string('error_log_path')->nullable();
            $table->integer('log_retention_days')->default(30);

            // Statistics and monitoring
            $table->integer('total_virtual_hosts')->default(0);
            $table->integer('active_virtual_hosts')->default(0);
            $table->integer('total_requests_today')->default(0);
            $table->bigInteger('bandwidth_used_today')->default(0); // Bytes
            $table->decimal('average_response_time_ms', 8, 2)->nullable();
            $table->integer('current_connections')->default(0);
            $table->decimal('cpu_usage_percent', 5, 2)->default(0);
            $table->decimal('memory_usage_percent', 5, 2)->default(0);

            // Service status
            $table->enum('service_status', ['running', 'stopped', 'failed', 'reloading', 'unknown'])
                ->default('unknown');
            $table->timestamp('last_restart_at')->nullable();
            $table->timestamp('last_config_reload_at')->nullable();
            $table->text('last_error')->nullable();
            $table->integer('restart_count')->default(0);

            // Configuration management
            $table->text('main_config')->nullable(); // Main server configuration
            $table->json('modules_enabled')->nullable(); // Enabled modules/extensions
            $table->json('custom_directives')->nullable(); // Custom configuration directives
            $table->timestamp('config_last_modified')->nullable();
            $table->string('config_checksum')->nullable();

            // Backup configuration
            $table->boolean('backup_enabled')->default(true);
            $table->string('backup_schedule')->default('0 2 * * *'); // Daily at 2 AM
            $table->integer('backup_retention_days')->default(30);
            $table->timestamp('last_backup_at')->nullable();
            $table->bigInteger('last_backup_size_bytes')->nullable();

            // Health and monitoring
            $table->enum('health_status', ['healthy', 'warning', 'error', 'maintenance', 'unknown'])
                ->default('unknown');
            $table->text('health_message')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->json('health_checks')->nullable(); // Individual check results
            $table->integer('uptime_percentage')->default(100);

            // Maintenance and updates
            $table->boolean('maintenance_mode')->default(false);
            $table->text('maintenance_message')->nullable();
            $table->timestamp('maintenance_started_at')->nullable();
            $table->timestamp('maintenance_ends_at')->nullable();
            $table->timestamp('last_update')->nullable();
            $table->string('update_available')->nullable();

            // Integration settings
            $table->json('webhook_endpoints')->nullable();
            $table->json('monitoring_endpoints')->nullable();
            $table->boolean('auto_ssl_enabled')->default(false);
            $table->string('ssl_provider')->nullable(); // letsencrypt, custom, etc.
            $table->json('ssl_config')->nullable();

            // Custom configuration
            $table->json('custom_config')->nullable(); // Additional custom settings
            $table->json('environment_variables')->nullable(); // Environment-specific variables
            $table->json('nginx_conf')->nullable(); // Nginx-specific configuration
            $table->json('apache_conf')->nullable(); // Apache-specific configuration

            // Metadata and audit
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['is_active', 'server_type']);
            $table->index(['infrastructure_node_id', 'is_active']);
            $table->index(['hostname', 'is_active']);
            $table->index(['service_status', 'last_health_check_at']);
            $table->index(['health_status', 'last_health_check_at']);
            $table->index(['maintenance_mode', 'maintenance_ends_at']);
            $table->index(['is_primary', 'server_type']);
            $table->index(['created_at']);

            // Unique constraints
            $table->unique(['hostname', 'infrastructure_node_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_servers');
    }
};
