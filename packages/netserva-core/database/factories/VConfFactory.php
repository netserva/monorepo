<?php

namespace NetServa\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Core\Models\VConf;
use NetServa\Fleet\Models\FleetVhost;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\NetServa\Core\Models\VConf>
 */
class VConfFactory extends Factory
{
    protected $model = VConf::class;

    public function definition(): array
    {
        $name = fake()->randomElement(['VHOST', 'VNODE', 'UUSER', 'UPATH', 'WPATH', 'MPATH', 'DNAME', 'DUSER', 'DTYPE', 'DHOST', 'DPORT', 'V_PHP']);
        $value = match ($name) {
            'VHOST' => fake()->domainName(),
            'VNODE' => fake()->domainWord(),
            'UUSER' => 'u'.fake()->numberBetween(1001, 9999),
            'UPATH' => '/srv/'.fake()->domainName(),
            'WPATH' => '/srv/'.fake()->domainName().'/web',
            'MPATH' => '/srv/'.fake()->domainName().'/msg',
            'DNAME' => fake()->word(),
            'DUSER' => fake()->word(),
            'DTYPE' => fake()->randomElement(['mysql', 'sqlite', 'postgresql']),
            'DHOST' => 'localhost',
            'DPORT' => '3306',
            'V_PHP' => fake()->randomElement(['8.1', '8.2', '8.3', '8.4']),
            default => fake()->word(),
        };

        return [
            'fleet_vhost_id' => FleetVhost::factory(),
            'name' => $name,
            'value' => $value,
            'category' => VConf::categorize($name),
            'is_sensitive' => VConf::isSensitive($name),
        ];
    }

    public function password(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->randomElement(['DPASS', 'UPASS', 'APASS']),
            'value' => fake()->password(),
            'category' => 'passwords',
            'is_sensitive' => true,
        ]);
    }

    public function path(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->randomElement(['UPATH', 'WPATH', 'MPATH', 'VPATH']),
            'value' => '/srv/'.fake()->domainName(),
            'category' => 'paths',
            'is_sensitive' => false,
        ]);
    }

    public function database(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->randomElement(['DNAME', 'DUSER', 'DTYPE', 'DHOST', 'DPORT']),
            'value' => fake()->word(),
            'category' => 'database',
            'is_sensitive' => false,
        ]);
    }
}
