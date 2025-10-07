<?php

namespace NetServa\Ops\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Core\Models\InfrastructureNode;
use NetServa\Ops\Models\AlertRule;
use NetServa\Ops\Models\Incident;
use NetServa\Ops\Models\MonitoringCheck;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\NetServa\Ops\Models\Incident>
 */
class IncidentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Incident::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $detectedAt = $this->faker->dateTimeBetween('-1 month', 'now');

        return [
            'incident_number' => $this->faker->unique()->regexify('INC-[0-9]{4}-[0-9]{3}'),
            'title' => $this->faker->sentence(),
            'description' => $this->faker->optional()->text(),
            'alert_rule_id' => null, // Don't create foreign key references in Unit tests
            'monitoring_check_id' => null, // Don't create foreign key references in Unit tests
            'infrastructure_node_id' => null, // Don't create foreign key references in Unit tests
            'incident_type' => $this->faker->randomElement(['outage', 'degradation', 'maintenance', 'security', 'capacity', 'other']),
            'severity' => $this->faker->randomElement(['critical', 'high', 'medium', 'low']),
            'priority' => $this->faker->randomElement(['p1', 'p2', 'p3', 'p4', 'p5']),
            'category' => $this->faker->randomElement(['infrastructure', 'application', 'network', 'security', 'data', 'user_error', 'external']),
            'status' => $this->faker->randomElement(['open', 'investigating', 'identified', 'monitoring', 'resolved', 'closed']),
            'detected_at' => $detectedAt,
            'impact_description' => $this->faker->optional()->sentence(),
            'affected_services' => [],
            'affected_customers' => [],
            'estimated_affected_users' => $this->faker->optional()->numberBetween(1, 10000),
            'business_impact_score' => $this->faker->randomFloat(2, 0, 10),
            'business_impact_description' => $this->faker->optional()->sentence(),
            'detection_time_minutes' => $this->faker->numberBetween(1, 60),
            'response_time_minutes' => $this->faker->optional()->numberBetween(1, 120),
            'acknowledgment_time_minutes' => $this->faker->optional()->numberBetween(1, 30),
            'resolution_time_minutes' => $this->faker->optional()->numberBetween(5, 1440),
            'total_downtime_minutes' => $this->faker->optional()->numberBetween(1, 2880),
            'assigned_to' => $this->faker->optional()->name(),
            'assigned_team' => [],
            'incident_commander' => $this->faker->optional()->name(),
            'participants' => [],
            'customer_notification_sent' => $this->faker->boolean(30),
            'customer_message' => $this->faker->optional()->text(),
            'notification_channels_used' => [],
            'status_page_updated' => $this->faker->boolean(40),
            'status_page_message' => $this->faker->optional()->text(),
            'root_cause' => $this->faker->optional()->sentence(),
            'root_cause_category' => $this->faker->optional()->randomElement(['code_bug', 'configuration_error', 'infrastructure_failure', 'capacity_issue', 'human_error', 'third_party', 'unknown']),
            'contributing_factors' => $this->faker->optional()->text(),
            'timeline' => [],
            'resolution_summary' => $this->faker->optional()->text(),
            'resolution_steps' => [],
            'temporary_fix' => $this->faker->optional()->text(),
            'permanent_fix' => $this->faker->optional()->text(),
            'requires_follow_up' => $this->faker->boolean(20),
            'lessons_learned' => [],
            'action_items' => [],
            'preventive_measures' => [],
            'prevention_confidence' => $this->faker->optional()->randomFloat(2, 0, 100),
            'estimated_cost' => $this->faker->optional()->randomFloat(2, 0, 100000),
            'cost_currency' => $this->faker->currencyCode(),
            'cost_breakdown' => $this->faker->optional()->text(),
            'engineering_hours_spent' => $this->faker->optional()->randomFloat(2, 0, 100),
            'escalated' => $this->faker->boolean(15),
            'escalation_reason' => $this->faker->optional()->sentence(),
            'escalation_level' => $this->faker->optional()->randomElement(['l1', 'l2', 'l3', 'executive']),
            'external_dependencies' => [],
            'third_party_providers' => [],
            'caused_by_external_service' => $this->faker->boolean(20),
            'external_service_details' => $this->faker->optional()->text(),
            'key_metrics' => [],
            'error_rates' => [],
            'performance_metrics' => [],
            'system_state_snapshot' => [],
            'log_excerpts' => [],
            'screenshots' => [],
            'graphs_charts' => [],
            'evidence_files' => [],
            'post_mortem_required' => $this->faker->boolean(30),
            'post_mortem_completed' => $this->faker->boolean(10),
            'incident_review_status' => $this->faker->randomElement(['pending', 'completed', 'not_required']),
            'post_mortem_document_url' => $this->faker->optional()->url(),
            'is_recurring' => $this->faker->boolean(10),
            'parent_incident_id' => null, // Don't create foreign key references in Unit tests
            'related_incident_ids' => [],
            'recurrence_count' => $this->faker->numberBetween(0, 10),
            'related_changes' => [],
            'caused_by_change' => $this->faker->boolean(25),
            'change_id' => $this->faker->optional()->uuid(),
            'change_details' => $this->faker->optional()->text(),
            'detection_method' => $this->faker->randomElement(['automated', 'manual', 'customer_report', 'third_party']),
            'detection_details' => $this->faker->optional()->text(),
            'monitoring_gaps_identified' => $this->faker->boolean(20),
            'monitoring_improvements' => [],
            'tags' => [],
            'custom_fields' => [],
            'metadata' => [],
            'created_by' => $this->faker->optional()->name(),
            'updated_by' => $this->faker->optional()->name(),
            'closed_by' => $this->faker->optional()->name(),
        ];
    }

    /**
     * Create an active incident
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $this->faker->randomElement(['open', 'investigating', 'identified', 'monitoring']),
        ]);
    }

    /**
     * Create a resolved incident
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_summary' => $this->faker->text(),
            'resolution_time_minutes' => $this->faker->numberBetween(5, 1440),
        ]);
    }

    /**
     * Create a closed incident
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
            'resolved_at' => now()->subHours($this->faker->numberBetween(1, 24)),
            'closed_at' => now(),
            'closed_by' => $this->faker->name(),
        ]);
    }

    /**
     * Create a critical incident
     */
    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => 'critical',
            'priority' => 1,
            'business_impact_score' => $this->faker->randomFloat(2, 8, 10),
            'post_mortem_required' => true,
        ]);
    }

    /**
     * Create an escalated incident
     */
    public function escalated(): static
    {
        return $this->state(fn (array $attributes) => [
            'escalated' => true,
            'escalated_at' => now(),
            'escalation_reason' => $this->faker->sentence(),
            'escalation_level' => $this->faker->randomElement(['l1', 'l2', 'l3', 'executive']),
        ]);
    }

    /**
     * Create an incident with alert rule
     */
    public function withAlertRule(): static
    {
        return $this->state(fn (array $attributes) => [
            'alert_rule_id' => AlertRule::factory(),
        ]);
    }

    /**
     * Create an incident with monitoring check
     */
    public function withMonitoringCheck(): static
    {
        return $this->state(fn (array $attributes) => [
            'monitoring_check_id' => MonitoringCheck::factory(),
        ]);
    }

    /**
     * Create an incident with infrastructure node
     */
    public function withInfrastructureNode(): static
    {
        return $this->state(fn (array $attributes) => [
            'infrastructure_node_id' => InfrastructureNode::factory(),
        ]);
    }

    /**
     * Create a recurring incident
     */
    public function recurring(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_recurring' => true,
            'recurrence_count' => $this->faker->numberBetween(2, 10),
        ]);
    }

    /**
     * Create an incident requiring post mortem
     */
    public function requiresPostMortem(): static
    {
        return $this->state(fn (array $attributes) => [
            'post_mortem_required' => true,
            'post_mortem_completed' => false,
        ]);
    }
}
