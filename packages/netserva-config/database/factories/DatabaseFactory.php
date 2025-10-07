<?php

declare(strict_types=1);

namespace NetServa\Config\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Config\Models\Database;
use NetServa\Config\Models\DatabaseConnection;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Ns\Database\Models\Database>
 */
class DatabaseFactory extends Factory
{
    protected $model = Database::class;

    public function definition(): array
    {
        $charset = $this->faker->randomElement(['utf8mb4', 'utf8', 'latin1']);
        $collation = match ($charset) {
            'utf8mb4' => $this->faker->randomElement(['utf8mb4_unicode_ci', 'utf8mb4_general_ci']),
            'utf8' => $this->faker->randomElement(['utf8_unicode_ci', 'utf8_general_ci']),
            'latin1' => $this->faker->randomElement(['latin1_swedish_ci', 'latin1_general_ci']),
            default => 'utf8mb4_unicode_ci'
        };

        return [
            'connection_id' => DatabaseConnection::factory(),
            'name' => $this->faker->unique()->domainWord().'_db_'.$this->faker->randomNumber(4),
            'charset' => $charset,
            'collation' => $collation,
            'is_active' => $this->faker->boolean(90),
        ];
    }

    /**
     * Active database state
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Inactive database state
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
