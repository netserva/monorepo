<?php

namespace NetServa\Ops\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Ops\Models\AnalyticsDataSource;
use NetServa\Ops\Models\AnalyticsMetric;

class AnalyticsMetricFactory extends Factory
{
    protected $model = AnalyticsMetric::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'analytics_data_source_id' => AnalyticsDataSource::factory(),
            'query' => 'SELECT value FROM metrics WHERE id = 1',
            'value' => fake()->optional()->randomFloat(2, 0, 1000),
            'collected_at' => fake()->optional()->dateTimeBetween('-1 week', 'now'),
            'frequency' => fake()->randomElement(['hourly', 'daily', 'weekly']),
            'unit' => fake()->randomElement(['USD', '%', 'count', 'GB']),
            'type' => fake()->randomElement(['number', 'percentage', 'currency']),
            'threshold_warning' => fake()->optional()->randomFloat(2, 50, 80),
            'threshold_critical' => fake()->optional()->randomFloat(2, 80, 100),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withDataSource(AnalyticsDataSource $dataSource): static
    {
        return $this->state(fn (array $attributes) => [
            'analytics_data_source_id' => $dataSource->id,
        ]);
    }

    public function frequency(string $frequency): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => $frequency,
        ]);
    }
}
