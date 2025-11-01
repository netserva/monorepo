<?php

declare(strict_types=1);

namespace NetServa\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use NetServa\Cms\Models\Page;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(10),
            'content' => fake()->paragraphs(3, true),
            'template' => fake()->randomElement(['default', 'homepage', 'service', 'pricing', 'blank']),
            'parent_id' => null,
            'order' => fake()->numberBetween(0, 100),
            'is_published' => true,
            'published_at' => now(),
            'meta_title' => $title,
            'meta_description' => fake()->sentence(15),
            'meta_keywords' => fake()->words(5, true),
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    public function homepage(): static
    {
        return $this->state(fn (array $attributes) => [
            'template' => 'homepage',
            'slug' => 'home',
            'title' => 'Home',
        ]);
    }

    public function service(): static
    {
        return $this->state(fn (array $attributes) => [
            'template' => 'service',
        ]);
    }

    public function pricing(): static
    {
        return $this->state(fn (array $attributes) => [
            'template' => 'pricing',
            'slug' => 'pricing',
            'title' => 'Pricing',
        ]);
    }
}
