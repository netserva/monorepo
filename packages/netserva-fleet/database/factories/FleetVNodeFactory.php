<?php

namespace NetServa\Fleet\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use NetServa\Fleet\Models\FleetVNode;
use NetServa\Fleet\Models\FleetVSite;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\NetServa\Fleet\Models\FleetVNode>
 */
class FleetVNodeFactory extends Factory
{
    protected $model = FleetVNode::class;

    public function definition(): array
    {
        $name = fake()->unique()->domainWord();
        $fqdn = $name.'.'.fake()->domainName();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'fqdn' => $fqdn,
            'vsite_id' => FleetVSite::factory(),
            'role' => fake()->randomElement(['compute', 'storage', 'database', 'web']),
            'environment' => fake()->randomElement(['production', 'staging', 'development']),
            'ip_address' => fake()->ipv4(),
            'operating_system' => fake()->randomElement(['Ubuntu 24.04', 'Debian 12', 'Rocky Linux 9']),
            'kernel_version' => fake()->randomElement(['6.8.0', '6.6.0', '6.1.0']),
            'cpu_cores' => fake()->numberBetween(2, 16),
            'memory_mb' => fake()->randomElement([2048, 4096, 8192, 16384, 32768]),
            'disk_gb' => fake()->randomElement([20, 50, 100, 200, 500]),
            'services' => ['sshd', 'systemd'],
            'discovery_method' => 'ssh',
            'scan_frequency_hours' => 24,
            'status' => 'active',
            'is_active' => true,
            'email_capable' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'status' => 'inactive',
        ]);
    }

    public function webServer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'web',
            'services' => ['sshd', 'systemd', 'nginx', 'php-fpm'],
        ]);
    }

    public function mailServer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'mail',
            'services' => ['sshd', 'systemd', 'postfix', 'dovecot'],
            'email_capable' => true,
        ]);
    }

    public function databaseServer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'database',
            'services' => ['sshd', 'systemd', 'mysql', 'mariadb'],
        ]);
    }

    public function discovered(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_discovered_at' => now()->subHours(2),
            'next_scan_at' => now()->addHours(22),
        ]);
    }
}
