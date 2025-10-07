<?php

declare(strict_types=1);

namespace NetServa\Config\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Config\Models\Database;
use NetServa\Config\Models\DatabaseCredential;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Ns\Database\Models\DatabaseCredential>
 */
class DatabaseCredentialFactory extends Factory
{
    protected $model = DatabaseCredential::class;

    public function definition(): array
    {
        return [
            'database_id' => Database::factory(),
            'username' => str_replace('.', '_', $this->faker->unique()->userName()).'_'.$this->faker->randomNumber(4),
            'password' => $this->faker->password(),
            'is_active' => $this->faker->boolean(90),
        ];
    }

    /**
     * Active credential state
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Inactive credential state
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
