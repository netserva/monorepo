<?php

declare(strict_types=1);

namespace NetServa\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Cms\Models\Menu;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    protected $model = Menu::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'location' => fake()->randomElement(['header', 'footer', 'sidebar', 'mobile']),
            'items' => $this->generateMenuItems(),
            'is_active' => true,
        ];
    }

    public function header(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Header Menu',
            'location' => 'header',
        ]);
    }

    public function footer(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Footer Menu',
            'location' => 'footer',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    protected function generateMenuItems(): array
    {
        return [
            [
                'label' => 'Home',
                'url' => '/',
                'icon' => 'heroicon-o-home',
                'new_window' => false,
                'order' => 0,
                'children' => [],
            ],
            [
                'label' => 'About',
                'url' => '/about',
                'icon' => null,
                'new_window' => false,
                'order' => 1,
                'children' => [],
            ],
            [
                'label' => 'Services',
                'url' => '/services',
                'icon' => null,
                'new_window' => false,
                'order' => 2,
                'children' => [
                    [
                        'label' => 'Web Development',
                        'url' => '/services/web-development',
                        'icon' => null,
                        'new_window' => false,
                        'order' => 0,
                    ],
                    [
                        'label' => 'Hosting',
                        'url' => '/services/hosting',
                        'icon' => null,
                        'new_window' => false,
                        'order' => 1,
                    ],
                ],
            ],
            [
                'label' => 'Blog',
                'url' => '/blog',
                'icon' => 'heroicon-o-newspaper',
                'new_window' => false,
                'order' => 3,
                'children' => [],
            ],
            [
                'label' => 'Contact',
                'url' => '/contact',
                'icon' => null,
                'new_window' => false,
                'order' => 4,
                'children' => [],
            ],
        ];
    }
}
