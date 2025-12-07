<?php

namespace NetServa\Fleet\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\NetServa\Fleet\Models\FleetVnode>
 */
class FleetVnodeFactory extends Factory
{
    protected $model = FleetVnode::class;

    public function definition(): array
    {
        $name = fake()->unique()->domainWord();
        $fqdn = $name.'.'.fake()->domainName();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'ssh_host' => Str::slug($name),  // CLI identifier for sx
            'hostname' => $name,  // Actual server hostname
            'fqdn' => $fqdn,  // Public FQDN
            'vsite_id' => FleetVsite::factory(),
            'role' => fake()->randomElement(['compute', 'storage', 'database', 'web', 'mail', 'gateway']),
            'environment' => fake()->randomElement(['production', 'staging', 'development']),
            'ipv4_public' => fake()->ipv4(),  // Renamed from ip_address
            'ipv4_private' => fake()->optional()->localIpv4(),
            'operating_system' => fake()->randomElement(['Ubuntu 24.04', 'Debian 12', 'Rocky Linux 9', 'Alpine Linux']),
            'kernel_version' => fake()->randomElement(['6.8.0', '6.6.0', '6.1.0']),
            'cpu_cores' => fake()->numberBetween(2, 16),
            'memory_mb' => fake()->randomElement([2048, 4096, 8192, 16384, 32768]),
            'disk_gb' => fake()->randomElement([20, 50, 100, 200, 500]),
            'services' => 'sshd,systemd',  // Comma-separated string
            'ssh_user' => 'root',
            'ssh_port' => 22,
            'discovery_method' => 'ssh',
            'scan_frequency_hours' => 24,
            'status' => 'active',
            'is_active' => true,
            'is_critical' => false,
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
            'services' => 'sshd,systemd,nginx,php-fpm',
        ]);
    }

    public function mailServer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'mail',
            'services' => 'sshd,systemd,postfix,dovecot',
            'email_capable' => true,
        ]);
    }

    public function databaseServer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'database',
            'services' => 'sshd,systemd,mysql,mariadb',
        ]);
    }

    public function gateway(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'gateway',
            'services' => 'sshd,systemd,nginx,dnsmasq,wireguard',
            'is_critical' => true,
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
