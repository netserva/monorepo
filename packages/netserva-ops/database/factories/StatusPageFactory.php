<?php

namespace NetServa\Ops\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Ops\Models\StatusPage;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\NetServa\Ops\Models\StatusPage>
 */
class StatusPageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = StatusPage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'slug' => $this->faker->unique()->slug(),
            'description' => $this->faker->optional()->sentence(),
            'is_public' => $this->faker->boolean(80),
            'is_active' => $this->faker->boolean(90),
            'url_path' => '/'.$this->faker->unique()->slug(),
            'custom_domain' => $this->faker->optional(30)->domainName(),
            'title' => $this->faker->company().' Status',
            'subtitle' => $this->faker->optional()->catchPhrase(),
            'logo_url' => $this->faker->optional()->imageUrl(200, 100),
            'favicon_url' => $this->faker->optional()->imageUrl(32, 32),
            'theme_config' => [
                'primary_color' => $this->faker->hexColor(),
                'accent_color' => $this->faker->hexColor(),
                'background_color' => '#ffffff',
            ],
            'primary_color' => $this->faker->hexColor(),
            'success_color' => '#28a745',
            'warning_color' => '#ffc107',
            'error_color' => '#dc3545',
            'header_content' => [],
            'footer_content' => [],
            'maintenance_message' => $this->faker->optional()->sentence(),
            'show_powered_by' => $this->faker->boolean(50),
            'custom_css' => [],
            'custom_js' => [],
            'show_overall_status' => $this->faker->boolean(90),
            'show_service_list' => $this->faker->boolean(90),
            'show_incidents' => $this->faker->boolean(80),
            'show_maintenance' => $this->faker->boolean(70),
            'show_metrics' => $this->faker->boolean(60),
            'show_uptime_stats' => $this->faker->boolean(80),
            'show_response_times' => $this->faker->boolean(70),
            'incident_history_days' => $this->faker->numberBetween(7, 90),
            'refresh_interval_seconds' => $this->faker->randomElement([30, 60, 300]),
            'service_groups' => [],
            'service_order' => [],
            'group_by_category' => $this->faker->boolean(40),
            'show_service_descriptions' => $this->faker->boolean(70),
            'uptime_calculation_method' => $this->faker->randomElement(['percentage', 'sla', 'weighted']),
            'uptime_calculation_period_days' => $this->faker->numberBetween(30, 365),
            'sla_targets' => [
                'monthly' => 99.9,
                'quarterly' => 99.95,
                'yearly' => 99.99,
            ],
            'exclude_maintenance_from_uptime' => $this->faker->boolean(80),
            'allow_subscriptions' => $this->faker->boolean(60),
            'subscription_channels' => [],
            'require_subscription_confirmation' => $this->faker->boolean(70),
            'subscription_success_message' => $this->faker->optional()->sentence(),
            'notification_templates' => [],
            'status_levels' => [
                'operational' => 'All systems operational',
                'degraded_performance' => 'Degraded performance',
                'partial_outage' => 'Partial outage',
                'major_outage' => 'Major outage',
            ],
            'status_mapping' => [],
            'default_status' => 'operational',
            'show_scheduled_maintenance' => $this->faker->boolean(80),
            'auto_create_maintenance_incidents' => $this->faker->boolean(60),
            'maintenance_advance_notice_hours' => $this->faker->numberBetween(4, 48),
            'maintenance_notification_channels' => [],
            'monitoring_check_filters' => [],
            'incident_filters' => [],
            'metric_filters' => [],
            'sync_with_monitoring_checks' => $this->faker->boolean(70),
            'auto_resolve_incidents' => $this->faker->boolean(50),
            'enable_historical_data' => $this->faker->boolean(80),
            'data_retention_days' => $this->faker->numberBetween(90, 365),
            'show_historical_incidents' => $this->faker->boolean(70),
            'historical_incidents_limit' => $this->faker->numberBetween(10, 100),
            'enable_api_access' => $this->faker->boolean(40),
            'api_key' => $this->faker->optional()->sha256(),
            'webhook_urls' => [],
            'webhook_events' => [],
            'enable_rss_feed' => $this->faker->boolean(60),
            'enable_json_feed' => $this->faker->boolean(50),
            'require_authentication' => $this->faker->boolean(20),
            'allowed_users' => [],
            'allowed_ip_ranges' => [],
            'access_password' => $this->faker->optional()->password(),
            'meta_title' => $this->faker->optional()->sentence(),
            'meta_description' => $this->faker->optional()->text(160),
            'meta_keywords' => [],
            'og_image_url' => $this->faker->optional()->imageUrl(1200, 630),
            'twitter_handle' => $this->faker->optional()->userName(),
            'google_analytics_id' => $this->faker->optional()->regexify('UA-[0-9]{8}-[0-9]'),
            'tracking_scripts' => [],
            'enable_visitor_tracking' => $this->faker->boolean(50),
            'total_page_views' => $this->faker->numberBetween(0, 100000),
            'unique_visitors' => $this->faker->numberBetween(0, 50000),
            'current_status' => 'operational',
            'current_status_message' => $this->faker->sentence(),
            'service_statuses' => [],
            'overall_uptime_percentage' => $this->faker->randomFloat(2, 95, 100),
            'page_load_time_ms' => $this->faker->randomFloat(2, 100, 3000),
            'performance_metrics' => [],
            'default_locale' => 'en',
            'supported_locales' => ['en'],
            'translations' => [],
            'timezone' => $this->faker->timezone(),
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i:s',
            'contact_email' => $this->faker->optional()->safeEmail(),
            'support_url' => $this->faker->optional()->url(),
            'company_name' => $this->faker->optional()->company(),
            'company_address' => $this->faker->optional()->address(),
            'social_links' => [],
            'terms_of_service' => $this->faker->optional()->url(),
            'privacy_policy' => $this->faker->optional()->url(),
            'gdpr_compliant' => $this->faker->boolean(70),
            'compliance_settings' => [],
            'enable_caching' => $this->faker->boolean(80),
            'cache_duration_seconds' => $this->faker->randomElement([300, 600, 1800, 3600]),
            'enable_cdn' => $this->faker->boolean(30),
            'cdn_url' => $this->faker->optional()->url(),
            'export_formats' => [],
            'auto_backup_enabled' => $this->faker->boolean(40),
            'backup_retention_days' => $this->faker->numberBetween(30, 365),
            'monthly_uptime_stats' => [],
            'incident_summary_stats' => [],
            'total_incidents' => $this->faker->numberBetween(0, 1000),
            'total_maintenance_events' => $this->faker->numberBetween(0, 100),
            'mttr_minutes' => $this->faker->randomFloat(2, 5, 240),
            'mtbf_hours' => $this->faker->randomFloat(2, 24, 8760),
            'tags' => [],
            'metadata' => [],
            'created_by' => $this->faker->optional()->name(),
            'updated_by' => $this->faker->optional()->name(),
        ];
    }

    /**
     * Create a public status page
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
            'is_active' => true,
            'require_authentication' => false,
        ]);
    }

    /**
     * Create a private status page
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
            'require_authentication' => true,
            'allowed_users' => [$this->faker->safeEmail(), $this->faker->safeEmail()],
        ]);
    }

    /**
     * Create an active status page
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Create an inactive status page
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a status page with custom domain
     */
    public function withCustomDomain(): static
    {
        return $this->state(fn (array $attributes) => [
            'custom_domain' => 'status.'.$this->faker->domainName(),
        ]);
    }

    /**
     * Create a status page with API access
     */
    public function withApiAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'enable_api_access' => true,
            'api_key' => 'sp_'.$this->faker->sha256(),
        ]);
    }

    /**
     * Create a status page with subscriptions
     */
    public function withSubscriptions(): static
    {
        return $this->state(fn (array $attributes) => [
            'allow_subscriptions' => true,
            'subscription_channels' => ['email', 'sms', 'webhook'],
            'require_subscription_confirmation' => true,
        ]);
    }

    /**
     * Create a status page with analytics
     */
    public function withAnalytics(): static
    {
        return $this->state(fn (array $attributes) => [
            'enable_visitor_tracking' => true,
            'google_analytics_id' => 'UA-'.$this->faker->randomNumber(8).'-'.$this->faker->randomNumber(1),
            'total_page_views' => $this->faker->numberBetween(1000, 100000),
            'unique_visitors' => $this->faker->numberBetween(500, 50000),
        ]);
    }

    /**
     * Create an operational status page
     */
    public function operational(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_status' => 'operational',
            'current_status_message' => 'All systems operational',
            'overall_uptime_percentage' => $this->faker->randomFloat(2, 99, 100),
        ]);
    }

    /**
     * Create a status page with degraded performance
     */
    public function degradedPerformance(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_status' => 'degraded_performance',
            'current_status_message' => 'Some services experiencing degraded performance',
            'overall_uptime_percentage' => $this->faker->randomFloat(2, 90, 99),
        ]);
    }

    /**
     * Create a status page with partial outage
     */
    public function partialOutage(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_status' => 'partial_outage',
            'current_status_message' => 'Some services are currently unavailable',
            'overall_uptime_percentage' => $this->faker->randomFloat(2, 70, 90),
        ]);
    }

    /**
     * Create a status page with major outage
     */
    public function majorOutage(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_status' => 'major_outage',
            'current_status_message' => 'Multiple services are experiencing issues',
            'overall_uptime_percentage' => $this->faker->randomFloat(2, 0, 70),
        ]);
    }

    /**
     * Create a status page with GDPR compliance
     */
    public function gdprCompliant(): static
    {
        return $this->state(fn (array $attributes) => [
            'gdpr_compliant' => true,
            'privacy_policy' => $this->faker->url(),
            'terms_of_service' => $this->faker->url(),
            'compliance_settings' => [
                'cookie_consent' => true,
                'data_retention_policy' => true,
                'user_data_export' => true,
            ],
        ]);
    }

    /**
     * Create a status page with caching enabled
     */
    public function withCaching(): static
    {
        return $this->state(fn (array $attributes) => [
            'enable_caching' => true,
            'cache_duration_seconds' => 600,
            'cache_last_updated_at' => now(),
        ]);
    }

    /**
     * Create a status page with CDN enabled
     */
    public function withCdn(): static
    {
        return $this->state(fn (array $attributes) => [
            'enable_cdn' => true,
            'cdn_url' => 'https://cdn.'.$this->faker->domainName(),
        ]);
    }

    /**
     * Create a status page with backup enabled
     */
    public function withBackup(): static
    {
        return $this->state(fn (array $attributes) => [
            'auto_backup_enabled' => true,
            'backup_retention_days' => 90,
            'last_backup_at' => now()->subDays($this->faker->numberBetween(1, 7)),
        ]);
    }
}
