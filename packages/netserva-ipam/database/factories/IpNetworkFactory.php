<?php

namespace NetServa\Ipam\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Ipam\Models\IpNetwork;

class IpNetworkFactory extends Factory
{
    protected $model = IpNetwork::class;

    public function definition(): array
    {
        $cidrPrefixes = [24, 25, 26, 27, 28, 29, 30];
        $prefixLength = $this->faker->randomElement($cidrPrefixes);

        // Generate a unique private network
        $networkOctet = $this->faker->unique()->numberBetween(1, 254);
        $networkAddress = "192.168.{$networkOctet}.0";
        $cidr = "{$networkAddress}/{$prefixLength}";
        $gateway = "192.168.{$networkOctet}.1";

        // Calculate total addresses
        $totalAddresses = $prefixLength < 31 ? pow(2, 32 - $prefixLength) - 2 : 0;

        return [
            'name' => $this->faker->words(3, true).' Network',
            'description' => $this->faker->sentence(),
            'cidr' => $cidr,
            'network_address' => $networkAddress,
            'prefix_length' => $prefixLength,
            'ip_version' => '4',
            'gateway' => $gateway,
            'dns_servers' => $this->faker->optional(0.7)->passthrough([
                ['ip' => '8.8.8.8'],
                ['ip' => '8.8.4.4'],
            ]),
            'network_type' => $this->faker->randomElement(array_keys(IpNetwork::NETWORK_TYPES)),
            'is_active' => $this->faker->boolean(90),
            'total_addresses' => $totalAddresses,
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

    public function production(): static
    {
        return $this->state(fn (array $attributes) => [
            'environment' => 'production',
        ]);
    }

    public function development(): static
    {
        return $this->state(fn (array $attributes) => [
            'environment' => 'development',
        ]);
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'network_type' => 'private',
        ]);
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'network_type' => 'public',
        ]);
    }

    public function highUtilization(): static
    {
        return $this->state(fn (array $attributes) => [
            'utilization_percentage' => $this->faker->randomFloat(2, 85, 100),
        ]);
    }

    public function lowUtilization(): static
    {
        return $this->state(fn (array $attributes) => [
            'utilization_percentage' => $this->faker->randomFloat(2, 0, 30),
        ]);
    }
}
