<?php

namespace NetServa\Fleet\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\NetServa\Fleet\Models\FleetVhost>
 */
class FleetVhostFactory extends Factory
{
    protected $model = FleetVhost::class;

    public function definition(): array
    {
        $domain = fake()->unique()->domainName();

        return [
            'domain' => $domain,
            'vnode_id' => FleetVnode::factory(),

            // Legacy NS 1.0 compatibility
            'uid' => 1000,
            'gid' => 1000,
            'document_root' => "/srv/{$domain}/web",
            'php_version' => '8.4',
            'ssl_enabled' => true,
            'ssl_type' => 'letsencrypt',

            // Domain classification
            'is_primary' => false,
            'is_mail_domain' => false,

            // Unix username (derived from uid if null)
            'unix_username' => null,

            // Application info
            'app_type' => fake()->optional()->randomElement(['wordpress', 'laravel', 'static']),
            'app_version' => null,
            'cms_admin_user' => null,

            // Database
            'db_name' => null,
            'db_user' => null,

            // Contact
            'admin_email' => fake()->optional()->safeEmail(),

            // Status
            'status' => 'active',
            'description' => fake()->optional()->sentence(),
            'dns_provider' => null,
            'metadata' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    public function wordpress(): static
    {
        return $this->state(fn (array $attributes) => [
            'app_type' => 'wordpress',
            'app_version' => '6.7',
            'cms_admin_user' => 'admin',
            'db_name' => 'wp_'.fake()->word(),
            'db_user' => 'wp_'.fake()->word(),
        ]);
    }

    public function laravel(): static
    {
        return $this->state(fn (array $attributes) => [
            'app_type' => 'laravel',
            'app_version' => '12.0',
            'db_name' => 'app_'.fake()->word(),
            'db_user' => 'app_'.fake()->word(),
        ]);
    }

    public function mailDomain(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_mail_domain' => true,
        ]);
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }

    public function selfSigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'ssl_type' => 'self-signed',
        ]);
    }

    public function noSsl(): static
    {
        return $this->state(fn (array $attributes) => [
            'ssl_enabled' => false,
            'ssl_type' => 'none',
        ]);
    }
}
