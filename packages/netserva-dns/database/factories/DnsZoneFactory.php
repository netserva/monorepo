<?php

namespace NetServa\Dns\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Dns\Models\DnsProvider;
use NetServa\Dns\Models\DnsZone;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Ns\Dns\Models\DnsZone>
 */
class DnsZoneFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DnsZone::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dns_provider_id' => DnsProvider::factory(),
            'external_id' => fake()->uuid(),
            'name' => fake()->domainName(),
            'kind' => fake()->randomElement(['Primary', 'Secondary', 'Forwarded', 'Native']),
            'masters' => null,
            'serial' => fake()->numberBetween(2020010100, 2030123199),
            'last_check' => fake()->optional(70)->dateTimeThisYear(),
            'account' => fake()->optional()->word(),
            'active' => fake()->boolean(85), // 85% chance of being active
            'description' => fake()->optional()->sentence(),
            'provider_data' => null,
            'last_synced' => fake()->optional(60)->dateTimeThisYear(),
            'ttl' => fake()->randomElement([300, 1800, 3600, 7200, 86400]),
            'auto_dnssec' => fake()->boolean(20), // 20% chance of auto-dnssec
            'nameservers' => fake()->optional(80)->randomElements([
                'ns1.'.fake()->domainName(),
                'ns2.'.fake()->domainName(),
                'ns3.'.fake()->domainName(),
            ], fake()->numberBetween(2, 3)),
            'records_count' => fake()->numberBetween(1, 20),
            'dnssec_enabled' => fake()->boolean(15), // 15% chance of dnssec enabled
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate that the DNS zone is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => true,
        ]);
    }

    /**
     * Indicate that the DNS zone is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    /**
     * Indicate that the DNS zone is a primary zone.
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => 'Primary',
            'masters' => null,
        ]);
    }

    /**
     * Indicate that the DNS zone is a secondary zone.
     */
    public function secondary(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => 'Secondary',
            'masters' => [fake()->ipv4(), fake()->ipv4()],
        ]);
    }

    /**
     * Indicate that the DNS zone is a native zone.
     */
    public function native(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => 'Native',
            'masters' => null,
        ]);
    }

    /**
     * Indicate that the DNS zone has DNSSEC enabled.
     */
    public function dnssecEnabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'dnssec_enabled' => true,
            'auto_dnssec' => true,
            'provider_data' => array_merge($attributes['provider_data'] ?? [], [
                'dnssec_keys' => [
                    ['keytag' => fake()->numberBetween(1, 65535), 'algorithm' => 7, 'digest_type' => 1],
                    ['keytag' => fake()->numberBetween(1, 65535), 'algorithm' => 8, 'digest_type' => 2],
                ],
            ]),
        ]);
    }

    /**
     * Indicate that the DNS zone was recently synchronized.
     */
    public function recentlySync(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_synced' => fake()->dateTimeBetween('-1 hour'),
        ]);
    }

    /**
     * Indicate that the DNS zone has never been synchronized.
     */
    public function neverSync(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_synced' => null,
        ]);
    }

    /**
     * State for zones with many records.
     */
    public function withManyRecords(): static
    {
        return $this->state(fn (array $attributes) => [
            'records_count' => fake()->numberBetween(50, 200),
        ]);
    }

    /**
     * Create a zone for a specific domain.
     */
    public function forDomain(string $domain): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $domain,
            'external_id' => md5($domain),
        ]);
    }

    /**
     * Create zone with high serial number (recently updated).
     */
    public function recentlyUpdated(): static
    {
        return $this->state(fn (array $attributes) => [
            'serial' => (int) date('Ymd') * 100 + fake()->numberBetween(1, 99),
            'last_check' => fake()->dateTimeBetween('-1 hour'),
        ]);
    }
}
