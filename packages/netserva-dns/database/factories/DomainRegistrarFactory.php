<?php

namespace NetServa\Dns\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Dns\Models\DomainRegistrar;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Nsis\DomainManager\Models\DomainRegistrar>
 */
class DomainRegistrarFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DomainRegistrar::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $registrarTypes = ['namecheap', 'godaddy', 'cloudflare', 'route53', 'gandi', 'enom'];
        $statuses = ['active', 'inactive', 'suspended'];

        $registrarType = fake()->randomElement($registrarTypes);

        return [
            'name' => fake()->company().' Registrar',
            'registrar_type' => $registrarType,
            'api_endpoint' => fake()->url().'/api/v1',
            'api_key_encrypted' => encrypt(fake()->uuid()),
            'api_secret_encrypted' => encrypt(fake()->uuid()),
            'additional_config' => [
                'timeout' => fake()->numberBetween(30, 120),
                'retries' => fake()->numberBetween(1, 5),
                'rate_limit' => fake()->numberBetween(100, 1000),
                'api_version' => fake()->randomFloat(1, 1.0, 2.0),
                'sandbox_mode' => fake()->boolean(20), // 20% chance of sandbox mode
            ],
            'status' => fake()->randomElement($statuses),
            'description' => fake()->sentence(),
        ];
    }

    /**
     * Indicate that the registrar is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the registrar is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Create a Namecheap registrar.
     */
    public function namecheap(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Namecheap Registrar',
            'registrar_type' => 'namecheap',
            'api_endpoint' => 'https://api.namecheap.com/xml.response',
            'additional_config' => array_merge($attributes['additional_config'] ?? [], [
                'client_ip' => fake()->ipv4(),
                'sandbox_mode' => fake()->boolean(),
            ]),
        ]);
    }

    /**
     * Create a GoDaddy registrar.
     */
    public function godaddy(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'GoDaddy Registrar',
            'registrar_type' => 'godaddy',
            'api_endpoint' => 'https://api.godaddy.com/v1',
            'additional_config' => array_merge($attributes['additional_config'] ?? [], [
                'environment' => fake()->randomElement(['production', 'ote']),
            ]),
        ]);
    }

    /**
     * Create a Cloudflare registrar.
     */
    public function cloudflare(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Cloudflare Registrar',
            'registrar_type' => 'cloudflare',
            'api_endpoint' => 'https://api.cloudflare.com/client/v4',
            'additional_config' => array_merge($attributes['additional_config'] ?? [], [
                'zone_id' => fake()->uuid(),
                'email' => fake()->email(),
            ]),
        ]);
    }

    /**
     * Create a Route53 registrar.
     */
    public function route53(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'AWS Route53 Registrar',
            'registrar_type' => 'route53',
            'api_endpoint' => 'https://route53domains.us-east-1.amazonaws.com',
            'additional_config' => array_merge($attributes['additional_config'] ?? [], [
                'region' => fake()->randomElement(['us-east-1', 'us-west-2', 'eu-west-1']),
                'hosted_zone_id' => fake()->regexify('Z[A-Z0-9]{10,32}'),
            ]),
        ]);
    }
}
