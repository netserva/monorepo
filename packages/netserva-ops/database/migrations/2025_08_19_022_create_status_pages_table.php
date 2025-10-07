<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_pages', function (Blueprint $table) {
            $table->id();

            // Page identification
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Configuration
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('url_path')->unique(); // /status, /health, etc.
            $table->string('custom_domain')->nullable(); // status.example.com

            // Appearance
            $table->string('title');
            $table->text('subtitle')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('favicon_url')->nullable();
            $table->json('theme_config')->nullable(); // Colors, fonts, etc.
            $table->string('primary_color', 7)->default('#3b82f6');
            $table->string('success_color', 7)->default('#10b981');
            $table->string('warning_color', 7)->default('#f59e0b');
            $table->string('error_color', 7)->default('#ef4444');

            // Content configuration
            $table->json('header_content')->nullable(); // Custom HTML/markdown for header
            $table->json('footer_content')->nullable(); // Custom HTML/markdown for footer
            $table->text('maintenance_message')->nullable();
            $table->boolean('show_powered_by')->default(true);
            $table->json('custom_css')->nullable();
            $table->json('custom_js')->nullable();

            // Display settings
            $table->boolean('show_overall_status')->default(true);
            $table->boolean('show_service_list')->default(true);
            $table->boolean('show_incidents')->default(true);
            $table->boolean('show_maintenance')->default(true);
            $table->boolean('show_metrics')->default(false);
            $table->boolean('show_uptime_stats')->default(true);
            $table->boolean('show_response_times')->default(false);
            $table->integer('incident_history_days')->default(7);
            $table->integer('refresh_interval_seconds')->default(30);

            // Service groups and organization
            $table->json('service_groups')->nullable(); // Logical grouping of services
            $table->json('service_order')->nullable(); // Custom ordering
            $table->boolean('group_by_category')->default(false);
            $table->boolean('show_service_descriptions')->default(true);

            // Uptime calculation settings
            $table->enum('uptime_calculation_method', ['percentage', 'sla', 'weighted'])->default('percentage');
            $table->integer('uptime_calculation_period_days')->default(30);
            $table->json('sla_targets')->nullable(); // SLA targets for different service tiers
            $table->boolean('exclude_maintenance_from_uptime')->default(true);

            // Notification and subscription settings
            $table->boolean('allow_subscriptions')->default(true);
            $table->json('subscription_channels')->nullable(); // email, sms, webhook, rss
            $table->boolean('require_subscription_confirmation')->default(true);
            $table->text('subscription_success_message')->nullable();
            $table->json('notification_templates')->nullable();

            // Status levels and mapping
            $table->json('status_levels')->nullable(); // Custom status level definitions
            $table->json('status_mapping')->nullable(); // Map check statuses to page statuses
            $table->enum('default_status', ['operational', 'degraded_performance', 'partial_outage', 'major_outage'])
                ->default('operational');

            // Maintenance window settings
            $table->boolean('show_scheduled_maintenance')->default(true);
            $table->boolean('auto_create_maintenance_incidents')->default(true);
            $table->integer('maintenance_advance_notice_hours')->default(24);
            $table->json('maintenance_notification_channels')->nullable();

            // Integration settings
            $table->json('monitoring_check_filters')->nullable(); // Which checks to include
            $table->json('incident_filters')->nullable(); // Which incidents to show
            $table->json('metric_filters')->nullable(); // Which metrics to display
            $table->boolean('sync_with_monitoring_checks')->default(true);
            $table->boolean('auto_resolve_incidents')->default(true);

            // Historical data settings
            $table->boolean('enable_historical_data')->default(true);
            $table->integer('data_retention_days')->default(90);
            $table->boolean('show_historical_incidents')->default(true);
            $table->integer('historical_incidents_limit')->default(50);

            // API and webhook settings
            $table->boolean('enable_api_access')->default(false);
            $table->string('api_key')->nullable();
            $table->json('webhook_urls')->nullable(); // External webhooks to notify
            $table->json('webhook_events')->nullable(); // Which events to send
            $table->boolean('enable_rss_feed')->default(true);
            $table->boolean('enable_json_feed')->default(true);

            // Access control
            $table->boolean('require_authentication')->default(false);
            $table->json('allowed_users')->nullable(); // User IDs or emails
            $table->json('allowed_ip_ranges')->nullable(); // IP whitelisting
            $table->string('access_password')->nullable(); // Simple password protection

            // SEO and social settings
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('meta_keywords')->nullable();
            $table->string('og_image_url')->nullable(); // Open Graph image
            $table->string('twitter_handle')->nullable();

            // Analytics and tracking
            $table->string('google_analytics_id')->nullable();
            $table->json('tracking_scripts')->nullable(); // Additional tracking codes
            $table->boolean('enable_visitor_tracking')->default(false);
            $table->integer('total_page_views')->default(0);
            $table->integer('unique_visitors')->default(0);
            $table->timestamp('last_visitor_at')->nullable();

            // Status calculation state
            $table->enum('current_status', ['operational', 'degraded_performance', 'partial_outage', 'major_outage'])
                ->default('operational');
            $table->text('current_status_message')->nullable();
            $table->timestamp('status_last_updated_at')->nullable();
            $table->json('service_statuses')->nullable(); // Current status of all services
            $table->decimal('overall_uptime_percentage', 5, 2)->default(100.00);

            // Performance metrics
            $table->integer('page_load_time_ms')->nullable();
            $table->timestamp('last_performance_check_at')->nullable();
            $table->json('performance_metrics')->nullable();

            // Localization
            $table->string('default_locale', 5)->default('en');
            $table->json('supported_locales')->nullable();
            $table->json('translations')->nullable(); // Custom translations
            $table->string('timezone')->default('UTC');
            $table->string('date_format')->default('M j, Y');
            $table->string('time_format')->default('g:i A');

            // Contact and support information
            $table->string('contact_email')->nullable();
            $table->string('support_url')->nullable();
            $table->string('company_name')->nullable();
            $table->text('company_address')->nullable();
            $table->json('social_links')->nullable(); // Twitter, LinkedIn, etc.

            // Legal and compliance
            $table->text('terms_of_service')->nullable();
            $table->text('privacy_policy')->nullable();
            $table->boolean('gdpr_compliant')->default(false);
            $table->json('compliance_settings')->nullable();

            // Cache and performance
            $table->boolean('enable_caching')->default(true);
            $table->integer('cache_duration_seconds')->default(60);
            $table->timestamp('cache_last_updated_at')->nullable();
            $table->boolean('enable_cdn')->default(false);
            $table->string('cdn_url')->nullable();

            // Backup and export
            $table->timestamp('last_backup_at')->nullable();
            $table->json('export_formats')->nullable(); // Supported export formats
            $table->boolean('auto_backup_enabled')->default(false);
            $table->integer('backup_retention_days')->default(30);

            // Statistics and reporting
            $table->json('monthly_uptime_stats')->nullable(); // Historical uptime by month
            $table->json('incident_summary_stats')->nullable(); // Incident statistics
            $table->integer('total_incidents')->default(0);
            $table->integer('total_maintenance_events')->default(0);
            $table->decimal('mttr_minutes', 10, 2)->nullable(); // Mean time to resolution
            $table->decimal('mtbf_hours', 10, 2)->nullable(); // Mean time between failures

            // Metadata and audit
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['is_public', 'is_active']);
            $table->index(['url_path']);
            $table->index(['custom_domain']);
            $table->index(['current_status', 'is_public']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_pages');
    }
};
