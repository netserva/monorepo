<?php

namespace NetServa\Config\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use NetServa\Config\Models\Secret;
use NetServa\Config\Models\SecretCategory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Ns\Secrets\Models\Secret>
 */
class SecretFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Secret::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'slug' => $this->faker->unique()->slug(),
            'type' => $this->faker->randomElement(['password', 'api_key', 'ssh_private_key', 'certificate', 'token', 'connection_string', 'environment_variable', 'other']),
            'description' => $this->faker->optional()->sentence(),
            'encrypted_value' => encrypt($this->faker->password(16, 32)),
            'encryption_method' => 'aes-256-gcm',
            'metadata' => $this->faker->optional()->randomElement([
                ['source' => 'manual'],
                ['source' => 'import', 'original_file' => 'secrets.json'],
                ['purpose' => 'database', 'server' => 'production'],
            ]),
            'secret_category_id' => $this->faker->optional()->randomElement([
                null,
                SecretCategory::factory(),
            ]),
            'tags' => $this->faker->optional()->randomElement([
                ['production', 'critical'],
                ['api', 'external'],
                ['database', 'mysql'],
                ['ssh', 'server'],
            ]),
            'is_active' => $this->faker->boolean(90),
            'expires_at' => $this->faker->optional()->dateTimeBetween('now', '+2 years'),
            'ssh_host_reference' => $this->faker->optional()->slug(),
        ];
    }

    /**
     * Configure the model factory to handle 'value' conversion to 'encrypted_value'
     */
    public function configure()
    {
        return $this->afterMaking(function (Secret $secret) {
            // No need to do anything in afterMaking
        })->afterCreating(function (Secret $secret) {
            // No need to do anything in afterCreating
        });
    }

    /**
     * Override the make method to handle 'value' attribute
     */
    public function make($attributes = [], ?Model $parent = null)
    {
        if (is_array($attributes) && isset($attributes['value'])) {
            $attributes['encrypted_value'] = encrypt($attributes['value']);
            unset($attributes['value']);
        }

        return parent::make($attributes, $parent);
    }

    /**
     * Override the create method to handle 'value' attribute
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        if (is_array($attributes) && isset($attributes['value'])) {
            $attributes['encrypted_value'] = encrypt($attributes['value']);
            unset($attributes['value']);
        }

        return parent::create($attributes, $parent);
    }
}
