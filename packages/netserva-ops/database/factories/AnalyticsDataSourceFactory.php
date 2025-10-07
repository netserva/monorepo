<?php

namespace NetServa\Ops\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Ops\Models\AnalyticsDataSource;

class AnalyticsDataSourceFactory extends Factory
{
    protected $model = AnalyticsDataSource::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(['database', 'api', 'file', 'stream']),
            'connection' => json_encode([
                'host' => fake()->ipv4(),
                'port' => fake()->numberBetween(1000, 9999),
                'database' => fake()->word(),
            ]),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function database(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'database',
            'connection' => json_encode([
                'driver' => 'mysql',
                'host' => 'localhost',
                'port' => 3306,
                'database' => fake()->word(),
                'charset' => 'utf8mb4',
            ]),
        ]);
    }

    public function api(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'api',
            'connection' => json_encode([
                'base_url' => fake()->url(),
                'timeout' => 30,
                'retry_attempts' => 3,
            ]),
        ]);
    }
}
