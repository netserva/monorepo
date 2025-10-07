<?php

namespace NetServa\Ipam\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Ipam\Models\IpAddress;
use NetServa\Ipam\Models\IpNetwork;

class IpAddressFactory extends Factory
{
    protected $model = IpAddress::class;

    public function definition(): array
    {
        $ipNetwork = IpNetwork::factory()->create();

        return [
            'ip_network_id' => $ipNetwork->id,
            'ip_address' => $this->faker->localIpv4(),
            'hostname' => $this->faker->optional(0.7)->domainWord(),
            'fqdn' => $this->faker->optional(0.5)->domainName(),
            'status' => $this->faker->randomElement(array_keys(IpAddress::STATUSES)),
            'mac_address' => $this->faker->optional(0.6)->macAddress(),
            'description' => $this->faker->optional(0.5)->sentence(),
            'owner' => $this->faker->optional(0.6)->randomElement([
                'IT Department',
                'Development Team',
                'Operations',
                'Security Team',
                'Network Admin',
            ]),
            'service' => $this->faker->optional(0.7)->randomElement([
                'Web Server',
                'Database Server',
                'DNS Server',
                'Mail Server',
                'File Server',
                'Application Server',
                'Load Balancer',
                'Firewall',
                'Router',
                'Switch',
            ]),
            'ssh_host_reference' => $this->faker->optional(0.3)->slug(),
            'allocated_at' => $this->faker->optional(0.6)->dateTimeBetween('-1 year', 'now'),
        ];
    }

    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'available',
            'allocated_at' => null,
            'owner' => null,
            'service' => null,
        ]);
    }

    public function allocated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'allocated',
            'allocated_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'owner' => $this->faker->randomElement([
                'IT Department',
                'Development Team',
                'Operations',
            ]),
            'service' => $this->faker->randomElement([
                'Web Server',
                'Database Server',
                'Application Server',
            ]),
        ]);
    }

    public function reserved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'reserved',
            'allocated_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    public function dhcpPool(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'dhcp_pool',
        ]);
    }

    public function gateway(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'gateway',
            'service' => 'Gateway',
        ]);
    }

    public function dnsServer(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'dns',
            'service' => 'DNS Server',
        ]);
    }

    public function withExpiredLease(): static
    {
        return $this->state(fn (array $attributes) => [
            'allocated_at' => $this->faker->dateTimeBetween('-1 week', '-1 day'),
        ]);
    }

    public function withHostname(): static
    {
        return $this->state(fn (array $attributes) => [
            'hostname' => $this->faker->domainWord(),
            'fqdn' => $this->faker->domainName(),
        ]);
    }
}
