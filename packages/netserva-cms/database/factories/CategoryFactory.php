<?php

declare(strict_types=1);

namespace NetServa\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use NetServa\Cms\Models\Category;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(10),
            'type' => fake()->randomElement(['post', 'portfolio', 'news', 'docs']),
        ];
    }

    public function post(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'post',
        ]);
    }

    public function portfolio(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'portfolio',
        ]);
    }

    public function news(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'news',
        ]);
    }

    public function docs(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'docs',
        ]);
    }
}
