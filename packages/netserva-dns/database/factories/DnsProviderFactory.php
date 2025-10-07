<?php

namespace NetServa\Dns\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Dns\Models\DnsProvider;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Ns\Dns\Models\DnsProvider>
 */
class DnsProviderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DnsProvider::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['powerdns', 'cloudflare', 'bind9', 'route53', 'godaddy']),
            'name' => fake()->company().' DNS',
            'description' => fake()->sentence(),
            'connection_config' => [
                'host' => fake()->domainName(),
                'port' => fake()->randomElement([53, 5353, 8053]),
                'api_key' => fake()->uuid(),
                'api_secret' => fake()->sha256(),
            ],
            'active' => fake()->boolean(80), // 80% chance of being active
            'version' => fake()->semver(),
            'last_sync' => fake()->optional(60)->dateTimeThisYear(),
            'sync_config' => [
                'auto_sync' => fake()->boolean(),
                'sync_interval' => fake()->randomElement([300, 900, 1800, 3600]), // seconds
                'zones_filter' => fake()->optional()->randomElement([[], ['example.com', 'test.org']]),
            ],
            'rate_limit' => fake()->numberBetween(50, 200),
            'timeout' => fake()->numberBetween(5, 60),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate that the DNS provider is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => true,
        ]);
    }

    /**
     * Indicate that the DNS provider is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    /**
     * Indicate that the DNS provider is PowerDNS.
     */
    public function powerDns(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'powerdns',
            'connection_config' => [
                'api_endpoint' => 'http://localhost:8081',
                'api_key' => fake()->uuid(),
                'server_id' => 'localhost',
            ],
        ]);
    }

    /**
     * Indicate that the DNS provider is Cloudflare.
     */
    public function cloudflare(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'cloudflare',
            'connection_config' => [
                'api_key' => fake()->sha256(),
                'email' => fake()->email(),
                'zone_id' => fake()->sha1(),
            ],
        ]);
    }

    /**
     * Indicate that the DNS provider is BIND9.
     */
    public function bind9(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'bind9',
            'connection_config' => [
                'host' => fake()->domainName(),
                'port' => 953,
                'key_name' => fake()->word(),
                'key_secret' => fake()->sha256(),
                'config_path' => '/etc/bind/',
                'zone_path' => '/var/lib/bind/',
            ],
        ]);
    }

    /**
     * Indicate that the DNS provider is Route53.
     */
    public function route53(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'route53',
            'connection_config' => [
                'access_key_id' => fake()->lexify('AKIA????????????????'),
                'secret_access_key' => fake()->sha256(),
                'region' => fake()->randomElement(['us-east-1', 'us-west-2', 'eu-west-1']),
                'hosted_zone_id' => fake()->regexify('Z[A-Z0-9]{13}'),
            ],
        ]);
    }

    /**
     * Indicate that the DNS provider is GoDaddy.
     */
    public function godaddy(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'godaddy',
            'connection_config' => [
                'api_key' => fake()->sha256(),
                'api_secret' => fake()->sha256(),
                'environment' => fake()->randomElement(['production', 'test']),
            ],
        ]);
    }

    /**
     * Indicate that the DNS provider was recently synchronized.
     */
    public function recentlySync(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_sync' => fake()->dateTimeBetween('-1 hour'),
        ]);
    }

    /**
     * Indicate that the DNS provider has never been synchronized.
     */
    public function neverSync(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_sync' => null,
        ]);
    }

    /**
     * Indicate that the DNS provider has auto-sync enabled.
     */
    public function autoSync(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_config' => array_merge($attributes['sync_config'] ?? [], [
                'auto_sync' => true,
                'sync_interval' => fake()->randomElement([300, 900, 1800]),
            ]),
        ]);
    }

    /**
     * Indicate that the DNS provider has auto-sync disabled.
     */
    public function manualSync(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_config' => array_merge($attributes['sync_config'] ?? [], [
                'auto_sync' => false,
                'sync_interval' => null,
            ]),
        ]);
    }

    /**
     * State for providers with high rate limits.
     */
    public function highRateLimit(): static
    {
        return $this->state(fn (array $attributes) => [
            'rate_limit' => fake()->numberBetween(500, 1000),
        ]);
    }

    /**
     * State for providers with low rate limits.
     */
    public function lowRateLimit(): static
    {
        return $this->state(fn (array $attributes) => [
            'rate_limit' => fake()->numberBetween(10, 50),
        ]);
    }
}
