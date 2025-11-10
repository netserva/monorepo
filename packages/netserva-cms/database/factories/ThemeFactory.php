<?php

declare(strict_types=1);

namespace NetServa\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Cms\Models\Theme;

/**
 * @extends Factory<Theme>
 */
class ThemeFactory extends Factory
{
    protected $model = Theme::class;

    public function definition(): array
    {
        $name = fake()->unique()->slug(2);

        return [
            'name' => $name,
            'display_name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'version' => fake()->randomElement(['1.0.0', '1.1.0', '2.0.0', '2.1.3']),
            'author' => fake()->name(),
            'parent_theme' => null,
            'is_active' => false,
            'manifest' => [
                'settings' => [
                    'color' => [
                        [
                            'slug' => 'primary',
                            'name' => 'Primary Color',
                            'value' => fake()->hexColor(),
                            'description' => 'Main brand color',
                        ],
                        [
                            'slug' => 'secondary',
                            'name' => 'Secondary Color',
                            'value' => fake()->hexColor(),
                            'description' => 'Secondary brand color',
                        ],
                        [
                            'slug' => 'accent',
                            'name' => 'Accent Color',
                            'value' => fake()->hexColor(),
                            'description' => 'Accent color for highlights',
                        ],
                    ],
                    'typography' => [
                        'fonts' => [
                            'heading' => [
                                'family' => fake()->randomElement(['Inter', 'Roboto', 'Open Sans', 'Montserrat']),
                                'provider' => 'google',
                            ],
                            'body' => [
                                'family' => fake()->randomElement(['system-ui', 'Arial', 'Helvetica']),
                                'provider' => 'system',
                            ],
                        ],
                    ],
                    'layout' => [
                        'contentWidth' => fake()->randomElement(['800px', '900px', '1000px']),
                        'wideWidth' => fake()->randomElement(['1200px', '1400px', '1600px']),
                    ],
                ],
            ],
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withParent(?string $parentThemeName = null): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_theme' => $parentThemeName ?? Theme::factory()->create()->name,
        ]);
    }

    public function withoutManifest(): static
    {
        return $this->state(fn (array $attributes) => [
            'manifest' => null,
        ]);
    }

    public function minimal(): static
    {
        return $this->state(fn (array $attributes) => [
            'manifest' => [
                'settings' => [
                    'color' => [
                        [
                            'slug' => 'primary',
                            'name' => 'Primary',
                            'value' => '#000000',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
