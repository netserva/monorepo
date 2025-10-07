<?php

namespace NetServa\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Core\Models\Server;
use NetServa\Core\Models\VHost;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\NetServa\Core\Models\VHost>
 */
class VHostFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = VHost::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $domain = fake()->unique()->domainName();

        return [
            'domain' => $domain,
            'display_name' => ucfirst(fake()->word()).' Site',
            'description' => fake()->sentence(),
            'host' => fake()->domainName(),
            'domain_aliases' => [
                'www.'.$domain,
                fake()->domainName(),
            ],
            'document_root' => '/var/www/'.str_replace('.', '_', $domain),
            'web_server' => fake()->randomElement(['nginx', 'apache', 'caddy']),
            'ssl_enabled' => fake()->boolean(70), // 70% have SSL
            'ssl_cert_path' => fake()->boolean(70) ? '/etc/ssl/certs/'.$domain.'.crt' : null,
            'ssl_key_path' => fake()->boolean(70) ? '/etc/ssl/private/'.$domain.'.key' : null,
            'php_enabled' => fake()->boolean(80), // 80% use PHP
            'php_version' => fake()->randomElement(['8.1', '8.2', '8.3', '8.4']),
            'database_enabled' => fake()->boolean(60), // 60% have databases
            'database_name' => fake()->boolean(60) ? str_replace('.', '_', $domain) : null,
            'email_enabled' => fake()->boolean(40), // 40% have email
            'status' => fake()->randomElement(['active', 'inactive', 'maintenance']),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate that the vhost is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the vhost is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Indicate that the vhost is in maintenance.
     */
    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'maintenance',
        ]);
    }

    /**
     * Indicate that the vhost has SSL enabled.
     */
    public function withSsl(): static
    {
        return $this->state(fn (array $attributes) => [
            'ssl_enabled' => true,
            'ssl_cert_path' => '/etc/ssl/certs/'.$attributes['domain'].'.crt',
            'ssl_key_path' => '/etc/ssl/private/'.$attributes['domain'].'.key',
        ]);
    }

    /**
     * Indicate that the vhost has PHP enabled.
     */
    public function withPhp(string $version = '8.3'): static
    {
        return $this->state(fn (array $attributes) => [
            'php_enabled' => true,
            'php_version' => $version,
        ]);
    }

    /**
     * Indicate that the vhost has a database.
     */
    public function withDatabase(): static
    {
        return $this->state(fn (array $attributes) => [
            'database_enabled' => true,
            'database_name' => str_replace('.', '_', $attributes['domain']),
        ]);
    }

    /**
     * Indicate that the vhost has email enabled.
     */
    public function withEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_enabled' => true,
        ]);
    }

    /**
     * Associate the vhost with a server.
     */
    public function forServer(Server $server): static
    {
        return $this->state(fn (array $attributes) => [
            'server_id' => $server->id,
        ]);
    }
}
