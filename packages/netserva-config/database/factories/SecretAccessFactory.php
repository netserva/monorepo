<?php

namespace NetServa\Config\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Config\Models\SecretAccess;

class SecretAccessFactory extends Factory
{
    protected $model = SecretAccess::class;

    public function definition(): array
    {
        return [
            'secret_id' => \Ns\Secrets\Models\Secret::factory(),
            'user_id' => \App\Models\User::factory(),
            'access_type' => $this->faker->randomElement(['view', 'copy', 'download', 'api', 'migration']),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'source' => $this->faker->randomElement(['web', 'api', 'cli', 'migration']),
            'additional_context' => [
                'action' => $this->faker->randomElement(['manual', 'automated']),
                'reason' => $this->faker->optional()->sentence(),
            ],
            'accessed_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'accessed_at' => $this->faker->dateTimeBetween('-24 hours', 'now'),
        ]);
    }

    public function api(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'api',
            'access_type' => 'api',
        ]);
    }

    public function web(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'web',
            'access_type' => 'view',
        ]);
    }
}
