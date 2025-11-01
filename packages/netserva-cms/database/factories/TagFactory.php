<?php

declare(strict_types=1);

namespace NetServa\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use NetServa\Cms\Models\Tag;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        $name = fake()->word();

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
        ];
    }
}
