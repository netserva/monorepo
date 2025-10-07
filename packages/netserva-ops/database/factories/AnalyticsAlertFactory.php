<?php

namespace NetServa\Ops\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Ops\Models\AnalyticsAlert;
use NetServa\Ops\Models\AnalyticsMetric;

class AnalyticsAlertFactory extends Factory
{
    protected $model = AnalyticsAlert::class;

    public function definition(): array
    {
        return [
            'analytics_metric_id' => AnalyticsMetric::factory(),
            'condition' => fake()->randomElement(['>', '<', '>=', '<=', '=']),
            'threshold' => fake()->randomFloat(2, 10, 1000),
            'channel' => fake()->randomElement(['email', 'slack']),
            'recipients' => json_encode([
                fake()->safeEmail(),
                fake()->safeEmail(),
            ]),
            'last_triggered_at' => fake()->optional()->dateTimeThisMonth(),
            'is_active' => true,
        ];
    }
}
