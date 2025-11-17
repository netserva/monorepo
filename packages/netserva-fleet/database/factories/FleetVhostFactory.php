<?php

namespace NetServa\Fleet\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\NetServa\Fleet\Models\FleetVhost>
 */
class FleetVhostFactory extends Factory
{
    protected $model = FleetVhost::class;

    public function definition(): array
    {
        $domain = fake()->unique()->domainName();

        return [
            'domain' => $domain,
            'slug' => Str::slug($domain),
            'vnode_id' => FleetVnode::factory(),
            'instance_type' => fake()->randomElement(['vm', 'ct', 'lxc', 'docker']),
            'cpu_cores' => fake()->numberBetween(1, 8),
            'memory_mb' => fake()->randomElement([512, 1024, 2048, 4096, 8192]),
            'disk_gb' => fake()->randomElement([10, 20, 50, 100, 200]),
            'ip_addresses' => [fake()->ipv4()],
            'services' => ['nginx'],
            'environment_vars' => [],
            'status' => 'active',
            'is_active' => true,
            'migration_status' => 'pending',
            'rollback_available' => false,
            'migration_attempts' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'status' => 'inactive',
        ]);
    }

    public function withServices(array $services): static
    {
        return $this->state(fn (array $attributes) => [
            'services' => array_merge(['nginx'], $services),
        ]);
    }

    public function webServer(): static
    {
        return $this->state(fn (array $attributes) => [
            'services' => ['nginx', 'php-fpm', 'mysql'],
        ]);
    }

    public function mailServer(): static
    {
        return $this->state(fn (array $attributes) => [
            'services' => ['postfix', 'dovecot'],
        ]);
    }

    public function discovered(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_discovered_at' => now()->subHours(2),
            'discovered_at' => now()->subDays(7),
        ]);
    }

    public function migrated(): static
    {
        return $this->state(fn (array $attributes) => [
            'migration_status' => 'completed',
            'migrated_at' => now()->subDays(1),
            'rollback_available' => true,
        ]);
    }

    public function withEnvironmentVars(array $vars): static
    {
        return $this->state(fn (array $attributes) => [
            'environment_vars' => $vars,
        ]);
    }
}
