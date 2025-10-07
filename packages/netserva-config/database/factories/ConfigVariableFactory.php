<?php

namespace NetServa\Config\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Config\Models\ConfigVariable;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Ns\Config\Models\ConfigVariable>
 */
class ConfigVariableFactory extends Factory
{
    protected $model = ConfigVariable::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'key' => strtoupper($this->faker->word()).'_'.strtoupper($this->faker->word()),
            'description' => $this->faker->sentence(),
            'scope' => 'global',
            'environment' => null,
            'infrastructure_node_id' => null,
            'config_profile_id' => null,
            'config_template_id' => null,
            'value' => $this->faker->word(),
            'value_type' => 'string',
            'default_value' => null,
            'is_required' => false,
            'is_sensitive' => false,
            'is_encrypted' => false,
            'encryption_key_id' => null,
            'sensitivity_level' => 'internal',
            'is_active' => true,
            'is_readonly' => false,
            'is_system_variable' => false,
            'priority' => 50,
            'validation_rules' => null,
            'created_by' => 'system',
            'metadata' => [
                'version' => '1.0',
                'source' => 'system',
                'last_modified_by' => 'system',
            ],
        ];
    }

    /**
     * Indicate that the variable is sensitive.
     */
    public function sensitive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_sensitive' => true,
            'is_encrypted' => true,
            'sensitivity_level' => 'confidential',
        ]);
    }

    /**
     * Indicate that the variable is required.
     */
    public function required(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => true,
        ]);
    }

    /**
     * Indicate that the variable is optional.
     */
    public function optional(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => false,
        ]);
    }

    /**
     * Indicate that the variable is encrypted.
     */
    public function encrypted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_encrypted' => true,
        ]);
    }

    /**
     * Set variable as string type.
     */
    public function string(): static
    {
        return $this->state(fn (array $attributes) => [
            'value_type' => 'string',
            'value' => $this->faker->word(),
            'validation_rules' => [
                'min_length' => 1,
                'max_length' => 255,
            ],
        ]);
    }

    /**
     * Set variable as integer type.
     */
    public function integer(): static
    {
        return $this->state(fn (array $attributes) => [
            'value_type' => 'integer',
            'value' => $this->faker->numberBetween(1, 100),
            'validation_rules' => [
                'min' => 0,
                'max' => 1000,
            ],
        ]);
    }

    /**
     * Set variable as boolean type.
     */
    public function boolean(): static
    {
        return $this->state(fn (array $attributes) => [
            'value_type' => 'boolean',
            'value' => $this->faker->boolean(),
            'validation_rules' => [
                'allowed_values' => [true, false],
            ],
        ]);
    }

    /**
     * Set variable as password type.
     */
    public function password(): static
    {
        return $this->state(fn (array $attributes) => [
            'value_type' => 'string',
            'is_sensitive' => true,
            'is_encrypted' => true,
            'sensitivity_level' => 'secret',
            'value' => bcrypt($this->faker->password(12)),
        ]);
    }

    /**
     * Set variable as URL type.
     */
    public function url(): static
    {
        return $this->state(fn (array $attributes) => [
            'value_type' => 'string',
            'value' => $this->faker->url(),
            'validation_rules' => [
                'format' => 'url',
            ],
        ]);
    }

    /**
     * Set variable as email type.
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'value_type' => 'string',
            'value' => $this->faker->email(),
            'validation_rules' => [
                'format' => 'email',
            ],
        ]);
    }

    /**
     * Indicate that the variable is computed.
     */
    public function computed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_computed' => true,
            'computation_expression' => '{{var1}} + {{var2}}',
            'computation_dependencies' => ['var1', 'var2'],
        ]);
    }

    /**
     * Set the variable to environment scope.
     */
    public function forEnvironment(string $environment = 'production'): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => 'environment',
            'environment' => $environment,
        ]);
    }

    /**
     * Set variable as environment specific.
     */
    public function environmentSpecific(): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => 'environment',
            'environment' => 'production',
        ]);
    }

    /**
     * Create database-related variables.
     */
    public function database(): static
    {
        $dbKeys = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USERNAME', 'DB_PASSWORD'];
        $key = $this->faker->randomElement($dbKeys);

        return $this->state(fn (array $attributes) => [
            'key' => $key,
            'name' => 'Database '.str_replace('DB_', '', $key),
            'value_type' => $key === 'DB_PORT' ? 'integer' : 'string',
            'value' => match ($key) {
                'DB_HOST' => 'localhost',
                'DB_PORT' => 3306,
                'DB_NAME' => 'database',
                'DB_USERNAME' => 'user',
                'DB_PASSWORD' => 'password123',
                default => $this->faker->word(),
            },
            'is_sensitive' => $key === 'DB_PASSWORD',
            'is_encrypted' => $key === 'DB_PASSWORD',
            'metadata' => [
                'category' => 'database',
                'priority' => 'high',
                'example_values' => ['localhost', '127.0.0.1'],
            ],
        ]);
    }
}
