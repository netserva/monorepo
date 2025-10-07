<?php

namespace NetServa\Config\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Config\Models\SecretCategory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Ns\Secrets\Models\SecretCategory>
 */
class SecretCategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SecretCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->optional()->sentence(),
            'color' => $this->faker->optional()->hexColor(),
            'icon' => $this->faker->optional()->randomElement(['key', 'shield', 'lock', 'database', 'server']),
        ];
    }
}
