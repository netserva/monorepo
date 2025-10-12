<?php

namespace NetServa\Fleet\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use NetServa\Fleet\Models\FleetVenue;
use NetServa\Fleet\Models\FleetVSite;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\NetServa\Fleet\Models\FleetVSite>
 */
class FleetVSiteFactory extends Factory
{
    protected $model = FleetVSite::class;

    public function definition(): array
    {
        $name = fake()->unique()->domainWord();

        return [
            'venue_id' => FleetVenue::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'provider' => fake()->randomElement(['local', 'hetzner', 'digitalocean', 'aws', 'proxmox', 'incus']),
            'technology' => fake()->randomElement(['proxmox', 'incus', 'docker', 'kvm']),
            'location' => fake()->optional()->city(),
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
