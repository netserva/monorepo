<?php

namespace NetServa\Fleet\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use NetServa\Fleet\Models\FleetVsite;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\NetServa\Fleet\Models\FleetVsite>
 */
class FleetVsiteFactory extends Factory
{
    protected $model = FleetVsite::class;

    public function definition(): array
    {
        $name = fake()->unique()->domainWord();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'provider' => fake()->randomElement(['binarylane', 'proxmox', 'incus', 'digitalocean', 'vultr', 'bare-metal']),
            'technology' => fake()->randomElement(['proxmox', 'incus', 'docker', 'kvm']),
            'location' => fake()->randomElement(['sydney', 'brisbane', 'melbourne', 'goldcoast', 'vpc']),
            'owner' => fake()->randomElement(['self', 'customer-1', 'customer-2']),
            'capabilities' => ['virtualization', 'storage', 'networking'],
            'description' => fake()->optional()->sentence(),
            'status' => 'active',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'status' => 'inactive',
        ]);
    }

    public function proxmox(): static
    {
        return $this->state(fn (array $attributes) => [
            'technology' => 'proxmox',
            'capabilities' => ['virtualization', 'storage', 'networking', 'backup'],
        ]);
    }

    public function incus(): static
    {
        return $this->state(fn (array $attributes) => [
            'technology' => 'incus',
            'capabilities' => ['containers', 'storage', 'networking'],
        ]);
    }
}
