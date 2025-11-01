<?php

declare(strict_types=1);

namespace NetServa\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use NetServa\Cms\Models\Post;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $title = fake()->sentence(6);
        $content = fake()->paragraphs(5, true);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(20),
            'content' => $content,
            'word_count' => str_word_count(strip_tags($content)),
            'is_published' => true,
            'published_at' => fake()->dateTimeBetween('-1 year', 'now'),
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
            'published_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }
}
