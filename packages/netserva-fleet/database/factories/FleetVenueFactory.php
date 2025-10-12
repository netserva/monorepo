<?php

namespace NetServa\Fleet\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use NetServa\Fleet\Models\FleetVenue;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\NetServa\Fleet\Models\FleetVenue>
 */
class FleetVenueFactory extends Factory
{
    protected $model = FleetVenue::class;

    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'provider' => fake()->randomElement(['local', 'homelab', 'hetzner', 'digitalocean', 'aws']),
            'location' => fake()->optional()->city(),
            'region' => fake()->optional()->randomElement(['us-east', 'us-west', 'eu-central', 'ap-southeast']),
            'metadata' => [],
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function cloud(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => fake()->randomElement(['hetzner', 'digitalocean', 'aws', 'gcp', 'azure']),
            'region' => fake()->randomElement(['us-east', 'us-west', 'eu-central', 'ap-southeast']),
        ]);
    }

    public function local(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'local',
            'region' => null,
        ]);
    }
}
