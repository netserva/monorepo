<?php

namespace Database\Factories;

use App\Models\GlueRecord;
use App\Models\SwDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GlueRecord>
 */
class GlueRecordFactory extends Factory
{
    protected $model = GlueRecord::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Don't create domain here - let caller provide it via forDomain()
        return [
            'sw_domain_id' => null,  // Must be provided
            'hostname' => 'ns'.fake()->numberBetween(1, 4).'.example.com',
            'ip_addresses' => [
                fake()->ipv4(),
            ],
            'is_synced' => true,
            'last_synced_at' => now(),
        ];
    }

    /**
     * Indicate that the glue record has both IPv4 and IPv6
     */
    public function withBothIpVersions(): static
    {
        return $this->state(fn (array $attributes) => [
            'ip_addresses' => [
                fake()->ipv4(),
                fake()->ipv6(),
            ],
        ]);
    }

    /**
     * Indicate that the glue record is not synced
     */
    public function notSynced(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_synced' => false,
            'last_synced_at' => null,
            'sync_error' => 'Failed to sync: Connection timeout',
        ]);
    }

    /**
     * For a specific domain
     */
    public function forDomain(SwDomain $domain): static
    {
        return $this->state(fn (array $attributes) => [
            'sw_domain_id' => $domain->id,
            'hostname' => 'ns'.fake()->numberBetween(1, 4).'.'.$domain->domain_name,
        ]);
    }
}
