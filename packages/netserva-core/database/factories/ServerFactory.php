<?php

namespace NetServa\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Core\Models\Server;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\NetServa\Core\Models\Server>
 */
class ServerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Server::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->userName(),
            'hostname' => fake()->unique()->domainName(),
            'ip_address' => fake()->ipv4(),
            'status' => fake()->randomElement(['active', 'inactive', 'maintenance']),
            'description' => fake()->sentence(),
            'os' => fake()->randomElement(['Debian 12', 'Ubuntu 24.04', 'Alpine 3.19']),
            'kernel' => '6.17.0-2-cachyos',
            'memory_gb' => fake()->randomElement([4, 8, 16, 32, 64]),
            'cpu_count' => fake()->randomElement([1, 2, 4, 8, 16]),
            'disk_total_gb' => fake()->randomElement([50, 100, 250, 500, 1000]),
            'disk_used_gb' => fake()->randomFloat(2, 10, 100),
            'last_seen_at' => now(),
        ];
    }

    /**
     * Indicate that the server is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the server is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Indicate that the server is under maintenance.
     */
    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'maintenance',
        ]);
    }

    /**
     * Indicate that the server has not been seen recently.
     */
    public function stale(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_seen_at' => now()->subHours(25),
        ]);
    }
}
