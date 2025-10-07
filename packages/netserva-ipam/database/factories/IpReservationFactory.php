<?php

namespace NetServa\Ipam\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Ipam\Models\IpNetwork;
use NetServa\Ipam\Models\IpReservation;

class IpReservationFactory extends Factory
{
    protected $model = IpReservation::class;

    public function definition(): array
    {
        $ipNetwork = IpNetwork::factory()->create();

        // Generate a random IP range within the network
        $startOctet = $this->faker->numberBetween(10, 200);
        $rangeSize = $this->faker->numberBetween(5, 20);
        $endOctet = $startOctet + $rangeSize;

        $networkBase = '192.168.1.'; // Use a standard base for consistency
        $startIp = $networkBase.$startOctet;
        $endIp = $networkBase.$endOctet;

        return [
            'ip_network_id' => $ipNetwork->id,
            'start_ip' => $startIp,
            'end_ip' => $endIp,
            'name' => $this->faker->words(3, true).' Pool',
            'description' => $this->faker->sentence(),
            'reservation_type' => $this->faker->randomElement(array_keys(IpReservation::RESERVATION_TYPES)),
            'purpose' => $this->faker->optional(0.7)->randomElement([
                'Web servers',
                'Database cluster',
                'Application servers',
                'Load balancers',
                'Development environment',
                'Testing environment',
                'Backup services',
                'Monitoring tools',
            ]),
            'is_active' => $this->faker->boolean(85),
            'address_count' => $rangeSize + 1, // Will be recalculated by model
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

    public function dhcpPool(): static
    {
        return $this->state(fn (array $attributes) => [
            'reservation_type' => 'dhcp_pool',
            'allow_auto_allocation' => true,
            'purpose' => 'DHCP address pool',
        ]);
    }

    public function staticRange(): static
    {
        return $this->state(fn (array $attributes) => [
            'reservation_type' => 'static_range',
            'purpose' => 'Static IP assignment range',
        ]);
    }

    public function infrastructure(): static
    {
        return $this->state(fn (array $attributes) => [
            'reservation_type' => 'network_infrastructure',
            'purpose' => 'Network infrastructure devices',
            'allow_auto_allocation' => false,
        ]);
    }

    public function securityBuffer(): static
    {
        return $this->state(fn (array $attributes) => [
            'reservation_type' => 'security_buffer',
            'purpose' => 'Security isolation buffer',
            'allow_auto_allocation' => false,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_until' => $this->faker->dateTimeBetween('-1 month', '-1 day'),
        ]);
    }

    public function expiringSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_until' => $this->faker->dateTimeBetween('now', '+30 days'),
        ]);
    }

    public function longTerm(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_until' => $this->faker->dateTimeBetween('+6 months', '+2 years'),
        ]);
    }

    public function neverExpires(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_until' => null,
        ]);
    }

    public function autoAllocation(): static
    {
        return $this->state(fn (array $attributes) => [
            'allow_auto_allocation' => true,
            'allocation_rules' => [
                'max_allocations_per_user' => $this->faker->numberBetween(1, 10),
                'require_approval' => false,
            ],
        ]);
    }

    public function manualOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'allow_auto_allocation' => false,
        ]);
    }

    public function smallRange(): static
    {
        $startOctet = $this->faker->numberBetween(10, 240);
        $startIp = '192.168.1.'.$startOctet;
        $endIp = '192.168.1.'.($startOctet + 4); // 5 IP range

        return $this->state(fn (array $attributes) => [
            'start_ip' => $startIp,
            'end_ip' => $endIp,
            'address_count' => 5,
        ]);
    }

    public function largeRange(): static
    {
        $startOctet = $this->faker->numberBetween(10, 150);
        $startIp = '192.168.1.'.$startOctet;
        $endIp = '192.168.1.'.($startOctet + 99); // 100 IP range

        return $this->state(fn (array $attributes) => [
            'start_ip' => $startIp,
            'end_ip' => $endIp,
            'address_count' => 100,
        ]);
    }
}
