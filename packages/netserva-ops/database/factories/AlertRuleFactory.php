<?php

namespace NetServa\Ops\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Ops\Models\AlertRule;
use NetServa\Ops\Models\MetricCollector;
use NetServa\Ops\Models\MonitoringCheck;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\NetServa\Ops\Models\AlertRule>
 */
class AlertRuleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AlertRule::class;

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
            'rule_type' => $this->faker->randomElement(['metric', 'check', 'composite']),
            'monitoring_check_id' => null, // Don't create foreign key references in Unit tests
            'metric_collector_id' => null, // Don't create foreign key references in Unit tests
            'is_active' => $this->faker->boolean(80),
            'conditions' => [],
            'condition_logic' => $this->faker->randomElement(['all', 'any', 'custom']),
            'metric_name' => $this->faker->optional()->words(2, true),
            'comparison_operator' => $this->faker->randomElement(['>', '<', '>=', '<=', '==', '!=', 'between']),
            'threshold_value' => $this->faker->randomFloat(2, 0, 1000),
            'threshold_min' => $this->faker->optional()->randomFloat(2, 0, 500),
            'threshold_max' => $this->faker->optional()->randomFloat(2, 500, 1000),
            'threshold_unit' => $this->faker->randomElement(['%', 'ms', 'MB', 'GB', 'count']),
            'evaluation_window_minutes' => $this->faker->numberBetween(1, 60),
            'datapoints_required' => $this->faker->numberBetween(1, 10),
            'aggregation_method' => $this->faker->randomElement(['avg', 'sum', 'min', 'max', 'count']),
            'missing_data_points_as' => $this->faker->randomElement(['missing', 'ignore', 'zero']),
            'severity' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'alert_delay_minutes' => $this->faker->numberBetween(0, 30),
            'repeat_interval_minutes' => $this->faker->numberBetween(5, 1440),
            'max_alerts_per_hour' => $this->faker->numberBetween(1, 60),
            'auto_resolve' => $this->faker->boolean(60),
            'auto_resolve_after_minutes' => $this->faker->optional()->numberBetween(5, 120),
            'notification_channels' => [],
            'notification_contacts' => [],
            'alert_title_template' => $this->faker->optional()->sentence(),
            'alert_message_template' => $this->faker->optional()->text(),
            'recovery_message_template' => $this->faker->optional()->text(),
            'notification_metadata' => [],
            'enable_escalation' => $this->faker->boolean(30),
            'escalation_rules' => [],
            'escalation_level' => $this->faker->numberBetween(1, 3),
            'state' => $this->faker->randomElement(['normal', 'pending', 'alerting', 'resolved', 'suppressed']),
            'consecutive_breaches' => $this->faker->numberBetween(0, 10),
            'current_values' => [],
            'state_reason' => $this->faker->optional()->sentence(),
            'total_evaluations' => $this->faker->numberBetween(0, 10000),
            'total_breaches' => $this->faker->numberBetween(0, 1000),
            'total_alerts_sent' => $this->faker->numberBetween(0, 100),
            'false_positives' => $this->faker->numberBetween(0, 50),
            'breach_percentage' => $this->faker->randomFloat(2, 0, 100),
            'average_resolution_time_minutes' => $this->faker->optional()->randomFloat(2, 1, 1440),
            'suppress_alerts' => $this->faker->boolean(10),
            'suppression_reason' => $this->faker->optional()->sentence(),
            'suppression_schedule' => [],
            'depends_on_rule_ids' => [],
            'rule_group' => $this->faker->optional()->word(),
            'tags' => [],
            'priority' => $this->faker->numberBetween(1, 100),
            'use_machine_learning' => $this->faker->boolean(20),
            'ml_config' => [],
            'anomaly_threshold' => $this->faker->optional()->randomFloat(2, 0, 1),
            'baseline_config' => [],
            'seasonal_config' => [],
            'automated_actions' => [],
            'runbook_url' => [],
            'dashboard_url' => [],
            'metadata' => [],
            'created_by' => $this->faker->optional()->name(),
            'modified_by' => $this->faker->optional()->name(),
            'approved_by' => $this->faker->optional()->name(),
        ];
    }

    /**
     * Create an active alert rule
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Create an inactive alert rule
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a critical severity alert rule
     */
    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => 'critical',
        ]);
    }

    /**
     * Create an alerting alert rule
     */
    public function alerting(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => 'alerting',
            'consecutive_breaches' => $this->faker->numberBetween(1, 5),
        ]);
    }

    /**
     * Create a suppressed alert rule
     */
    public function suppressed(): static
    {
        return $this->state(fn (array $attributes) => [
            'suppress_alerts' => true,
            'suppression_reason' => $this->faker->sentence(),
            'suppression_start_at' => now(),
            'suppression_end_at' => now()->addHours($this->faker->numberBetween(1, 24)),
        ]);
    }

    /**
     * Create an alert rule with monitoring check
     */
    public function withMonitoringCheck(): static
    {
        return $this->state(fn (array $attributes) => [
            'monitoring_check_id' => MonitoringCheck::factory(),
            'rule_type' => 'check',
        ]);
    }

    /**
     * Create an alert rule with metric collector
     */
    public function withMetricCollector(): static
    {
        return $this->state(fn (array $attributes) => [
            'metric_collector_id' => MetricCollector::factory(),
            'rule_type' => 'metric',
        ]);
    }
}
