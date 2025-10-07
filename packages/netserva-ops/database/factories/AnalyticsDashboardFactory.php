<?php

namespace NetServa\Ops\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Ops\Models\AnalyticsDashboard;

class AnalyticsDashboardFactory extends Factory
{
    protected $model = AnalyticsDashboard::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Dashboard',
            'description' => fake()->sentence(),
            'widgets' => json_encode([
                ['viz_id' => 1, 'x' => 0, 'y' => 0, 'width' => 6, 'height' => 4],
                ['viz_id' => 2, 'x' => 6, 'y' => 0, 'width' => 6, 'height' => 4],
            ]),
            'layout_columns' => 12,
            'refresh_interval' => fake()->randomElement([60, 300, 600]),
            'is_public' => fake()->boolean(30),
            'is_active' => true,
        ];
    }
}
