<?php

namespace NetServa\Dns\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Dns\Models\DnsRecord;
use NetServa\Dns\Models\DnsZone;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Ns\Dns\Models\DnsRecord>
 */
class DnsRecordFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DnsRecord::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS']);

        return [
            'dns_zone_id' => DnsZone::factory(),
            'external_id' => fake()->uuid(),
            'name' => fake()->randomElement(['@', 'www', 'mail', 'ftp', fake()->word()]),
            'type' => $type,
            'content' => $this->generateContentForType($type),
            'ttl' => fake()->randomElement([300, 600, 1800, 3600, 7200, 86400]),
            'priority' => $type === 'MX' ? fake()->randomElement([10, 20, 30]) : 0,
            'disabled' => fake()->boolean(10), // 10% chance of being disabled
            'auth' => fake()->boolean(90), // 90% chance of being authoritative
            'comment' => fake()->optional(30)->sentence(),
            'provider_data' => [
                'created_on' => fake()->dateTimeThisYear(),
                'modified_on' => fake()->dateTimeThisYear(),
                'locked' => fake()->boolean(5),
                'meta' => [
                    'auto_added' => fake()->boolean(20),
                    'source' => fake()->randomElement(['api', 'web', 'import']),
                ],
            ],
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Generate appropriate content based on DNS record type.
     */
    protected function generateContentForType(string $type): string
    {
        return match ($type) {
            'A' => fake()->ipv4(),
            'AAAA' => fake()->ipv6(),
            'CNAME' => fake()->domainName(),
            'MX' => fake()->domainName(),
            'TXT' => fake()->text(100),
            'NS' => 'ns'.fake()->numberBetween(1, 4).'.'.fake()->domainName(),
            'PTR' => fake()->domainName(),
            'SRV' => fake()->numberBetween(1, 100).' '.fake()->numberBetween(1, 100).' '.fake()->domainName(),
            'SOA' => implode(' ', [
                'ns1.'.fake()->domainName(),
                'admin.'.fake()->domainName(),
                fake()->numberBetween(2020010100, 2030123199), // serial
                7200, // refresh
                3600, // retry
                1209600, // expire
                3600, // minimum
            ]),
            'CAA' => '0 issue "letsencrypt.org"',
            default => fake()->domainName(),
        };
    }

    /**
     * Indicate that the DNS record is enabled.
     */
    public function enabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'disabled' => false,
        ]);
    }

    /**
     * Indicate that the DNS record is disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'disabled' => true,
        ]);
    }

    /**
     * Create an A record.
     */
    public function aRecord(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'A',
            'content' => fake()->ipv4(),
            'priority' => 0,
        ]);
    }

    /**
     * Create an AAAA record.
     */
    public function aaaaRecord(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'AAAA',
            'content' => fake()->ipv6(),
            'priority' => 0,
        ]);
    }

    /**
     * Create a CNAME record.
     */
    public function cnameRecord(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'CNAME',
            'content' => fake()->domainName(),
            'priority' => 0,
        ]);
    }

    /**
     * Create an MX record.
     */
    public function mxRecord(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'MX',
            'content' => 'mail.'.fake()->domainName(),
            'priority' => fake()->randomElement([10, 20, 30]),
        ]);
    }

    /**
     * Create a TXT record.
     */
    public function txtRecord(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'TXT',
            'content' => fake()->randomElement([
                'v=spf1 include:_spf.google.com ~all',
                'google-site-verification='.fake()->sha256(),
                fake()->text(100),
            ]),
            'priority' => 0,
        ]);
    }

    /**
     * Create an NS record.
     */
    public function nsRecord(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'NS',
            'content' => 'ns'.fake()->numberBetween(1, 4).'.'.fake()->domainName(),
            'priority' => 0,
        ]);
    }

    /**
     * Create an SRV record.
     */
    public function srvRecord(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'SRV',
            'name' => '_service._tcp',
            'content' => fake()->numberBetween(1, 100).' '.fake()->numberBetween(1, 100).' '.fake()->domainName(),
            'priority' => fake()->randomElement([10, 20, 30]),
        ]);
    }

    /**
     * Create a PTR record.
     */
    public function ptrRecord(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'PTR',
            'content' => fake()->domainName(),
            'priority' => 0,
        ]);
    }

    /**
     * Create record that was recently synchronized.
     */
    public function recentlySync(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider_data' => array_merge($attributes['provider_data'] ?? [], [
                'last_synced' => fake()->dateTimeBetween('-1 hour'),
                'sync_status' => 'success',
            ]),
        ]);
    }

    /**
     * Create record for a specific zone.
     */
    public function forZone(DnsZone $zone): static
    {
        return $this->state(fn (array $attributes) => [
            'dns_zone_id' => $zone->id,
        ]);
    }

    /**
     * Create record with specific name and content.
     */
    public function withNameAndContent(string $name, string $content): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
            'content' => $content,
        ]);
    }

    /**
     * Create high priority record.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => fake()->numberBetween(1, 10),
            'sort_order' => fake()->numberBetween(1, 10),
        ]);
    }

    /**
     * Create low priority record.
     */
    public function lowPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => fake()->numberBetween(50, 100),
            'sort_order' => fake()->numberBetween(50, 100),
        ]);
    }
}
