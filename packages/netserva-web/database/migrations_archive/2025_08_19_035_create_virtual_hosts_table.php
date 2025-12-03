<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_hosts', function (Blueprint $table) {
            $table->id();

            // Virtual host identification
            $table->string('name');
            $table->json('server_names'); // Primary and alias domains
            $table->string('primary_domain');
            $table->text('description')->nullable();

            // Web server association
            $table->foreignId('web_server_id')
                ->constrained('web_servers')
                ->cascadeOnDelete();

            // Infrastructure targeting
            $table->foreignId('infrastructure_node_id')
                ->nullable()
                ->constrained('infrastructure_nodes')
                ->cascadeOnDelete();

            // Basic configuration
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->enum('status', ['active', 'inactive', 'maintenance', 'error'])->default('active');
            $table->string('document_root');
            $table->string('index_files')->default('index.php index.html index.htm');

            // HTTP configuration
            $table->boolean('http_enabled')->default(true);
            $table->integer('http_port')->default(80);
            $table->boolean('https_enabled')->default(true);
            $table->integer('https_port')->default(443);
            $table->boolean('force_https')->default(true);

            // SSL/TLS configuration
            $table->boolean('ssl_enabled')->default(true);
            $table->string('ssl_certificate_path')->nullable();
            $table->string('ssl_private_key_path')->nullable();
            $table->string('ssl_chain_path')->nullable();
            $table->timestamp('ssl_expires_at')->nullable();
            $table->boolean('ssl_auto_renew')->default(true);
            $table->string('ssl_provider')->nullable(); // letsencrypt, custom, etc.
            $table->json('ssl_config')->nullable();

            // PHP configuration
            $table->boolean('php_enabled')->default(true);
            $table->string('php_version')->default('8.3');
            $table->string('php_handler')->default('fpm'); // fpm, cgi, mod_php
            $table->string('php_pool_name')->nullable();
            $table->json('php_settings')->nullable(); // Custom PHP settings
            $table->json('php_modules')->nullable(); // Required PHP modules

            // Directory and file handling
            $table->boolean('directory_listing')->default(false);
            $table->boolean('follow_symlinks')->default(true);
            $table->string('charset')->default('utf-8');
            $table->json('mime_types')->nullable();
            $table->string('client_max_body_size')->default('64M');

            // Caching configuration
            $table->boolean('caching_enabled')->default(true);
            $table->enum('cache_type', ['fastcgi', 'proxy', 'static', 'none'])->default('fastcgi');
            $table->json('cache_rules')->nullable(); // Caching rules and TTL
            $table->boolean('browser_caching')->default(true);
            $table->json('expires_headers')->nullable();

            // Security settings
            $table->json('security_headers')->nullable();
            $table->boolean('hotlink_protection')->default(false);
            $table->json('access_restrictions')->nullable(); // IP allow/deny lists
            $table->boolean('rate_limiting')->default(true);
            $table->string('rate_limit_rule')->nullable();
            $table->boolean('ddos_protection')->default(true);

            // Authentication and authorization
            $table->boolean('auth_required')->default(false);
            $table->enum('auth_type', ['basic', 'digest', 'oauth', 'jwt'])->nullable();
            $table->json('auth_config')->nullable();
            $table->string('auth_realm')->nullable();
            $table->json('protected_paths')->nullable();

            // Logging configuration
            $table->boolean('access_log_enabled')->default(true);
            $table->string('access_log_path')->nullable();
            $table->string('access_log_format')->default('combined');
            $table->boolean('error_log_enabled')->default(true);
            $table->string('error_log_path')->nullable();
            $table->string('error_log_level')->default('error');

            // URL rewriting and redirects
            $table->json('rewrite_rules')->nullable(); // URL rewrite rules
            $table->json('redirect_rules')->nullable(); // Redirect rules
            $table->boolean('www_redirect')->default(false);
            $table->enum('www_redirect_type', ['add_www', 'remove_www'])->nullable();
            $table->boolean('trailing_slash')->default(false);

            // Error handling
            $table->json('error_pages')->nullable(); // Custom error pages
            $table->boolean('show_server_info')->default(false);
            $table->string('maintenance_page_path')->nullable();

            // Proxy and load balancing
            $table->boolean('proxy_enabled')->default(false);
            $table->json('proxy_backends')->nullable(); // Backend servers for proxying
            $table->string('proxy_method')->nullable(); // round_robin, least_conn, etc.
            $table->boolean('sticky_sessions')->default(false);
            $table->json('proxy_headers')->nullable(); // Custom proxy headers

            // Static file handling
            $table->boolean('static_compression')->default(true);
            $table->json('compression_types')->nullable();
            $table->boolean('etag_enabled')->default(true);
            $table->integer('static_file_cache_ttl')->default(86400); // 1 day

            // CDN configuration
            $table->boolean('cdn_enabled')->default(false);
            $table->string('cdn_provider')->nullable();
            $table->string('cdn_endpoint')->nullable();
            $table->json('cdn_config')->nullable();
            $table->json('cdn_rules')->nullable(); // What to serve via CDN

            // Performance metrics
            $table->bigInteger('requests_today')->default(0);
            $table->bigInteger('requests_total')->default(0);
            $table->bigInteger('bandwidth_today')->default(0); // Bytes
            $table->bigInteger('bandwidth_total')->default(0); // Bytes
            $table->decimal('average_response_time', 8, 2)->nullable();
            $table->integer('concurrent_connections')->default(0);
            $table->integer('peak_connections_today')->default(0);

            // Status and health metrics
            $table->integer('http_2xx_count')->default(0); // Success responses
            $table->integer('http_3xx_count')->default(0); // Redirect responses
            $table->integer('http_4xx_count')->default(0); // Client error responses
            $table->integer('http_5xx_count')->default(0); // Server error responses
            $table->decimal('error_rate_percent', 5, 2)->default(0);
            $table->decimal('uptime_percentage', 5, 2)->default(100);

            // Content and deployment
            $table->enum('deployment_method', ['git', 'ftp', 'rsync', 'manual'])->nullable();
            $table->json('deployment_config')->nullable();
            $table->timestamp('last_deployment_at')->nullable();
            $table->string('deployment_status')->nullable();
            $table->json('deployment_log')->nullable();
            $table->boolean('auto_deployment')->default(false);

            // Backup configuration
            $table->boolean('backup_enabled')->default(true);
            $table->string('backup_schedule')->default('0 3 * * *'); // Daily at 3 AM
            $table->integer('backup_retention_days')->default(7);
            $table->timestamp('last_backup_at')->nullable();
            $table->bigInteger('last_backup_size')->nullable();
            $table->json('backup_includes')->nullable();
            $table->json('backup_excludes')->nullable();

            // Monitoring and alerting
            $table->boolean('monitoring_enabled')->default(true);
            $table->integer('check_interval_minutes')->default(5);
            $table->json('alert_thresholds')->nullable();
            $table->json('alert_recipients')->nullable();
            $table->timestamp('last_check_at')->nullable();
            $table->boolean('is_responding')->default(true);
            $table->integer('response_time_ms')->nullable();

            // Health status
            $table->enum('health_status', ['healthy', 'warning', 'error', 'maintenance'])
                ->default('healthy');
            $table->text('health_message')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->json('health_details')->nullable();

            // Application integration
            $table->string('application_type')->nullable(); // wordpress, laravel, etc.
            $table->string('application_version')->nullable();
            $table->json('application_config')->nullable();
            $table->boolean('auto_updates_enabled')->default(false);
            $table->timestamp('last_update_check')->nullable();

            // Custom configuration
            $table->text('custom_nginx_config')->nullable(); // Custom Nginx directives
            $table->text('custom_apache_config')->nullable(); // Custom Apache directives
            $table->json('environment_variables')->nullable();
            $table->json('custom_headers')->nullable();

            // Maintenance and lifecycle
            $table->boolean('maintenance_mode')->default(false);
            $table->text('maintenance_message')->nullable();
            $table->timestamp('maintenance_start')->nullable();
            $table->timestamp('maintenance_end')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('auto_renew')->default(true);

            // Metadata and audit
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->string('owner')->nullable(); // Client or user who owns this vhost
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index(['web_server_id', 'is_active']);
            $table->index(['infrastructure_node_id', 'is_active']);
            $table->index(['primary_domain', 'is_active']);
            $table->index(['status', 'health_status']);
            $table->index(['ssl_enabled', 'ssl_expires_at']);
            $table->index(['is_default', 'web_server_id']);
            $table->index(['maintenance_mode', 'maintenance_end']);
            $table->index(['monitoring_enabled', 'last_check_at']);
            $table->index(['expires_at']);
            $table->index(['created_at']);
            $table->index(['owner', 'is_active']);

            // Unique constraints
            $table->unique(['primary_domain', 'web_server_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_hosts');
    }
};
