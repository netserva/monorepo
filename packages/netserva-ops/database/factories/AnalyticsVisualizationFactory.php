<?php

namespace NetServa\Ops\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Ops\Models\AnalyticsVisualization;

class AnalyticsVisualizationFactory extends Factory
{
    protected $model = AnalyticsVisualization::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'type' => fake()->randomElement(['line', 'bar', 'pie', 'table', 'metric']),
            'metric_ids' => json_encode([1, 2]), // JSON array of metric IDs
            'config' => json_encode([
                'title' => fake()->sentence(3),
                'color' => fake()->safeColorName(),
            ]),
            'refresh_interval' => fake()->randomElement([60, 300, 600, 3600]),
            'is_active' => true,
            'analytics_dashboard_id' => null,
            'dashboard_position_x' => fake()->numberBetween(0, 10),
            'dashboard_position_y' => fake()->numberBetween(0, 10),
            'dashboard_width' => fake()->numberBetween(1, 6),
            'dashboard_height' => fake()->numberBetween(1, 4),
        ];
    }
}
