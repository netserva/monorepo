<?php

namespace Database\Seeders;

use App\Models\Palette;
use Illuminate\Database\Seeder;

class PaletteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $palettes = [
            // Neutral palettes (5)
            [
                'name' => 'slate',
                'label' => 'Slate',
                'group' => 'neutral',
                'description' => 'Cool, professional gray tones',
                'colors' => [
                    'primary' => 'slate',
                    'danger' => 'red',
                    'gray' => 'slate',
                    'info' => 'blue',
                    'success' => 'emerald',
                    'warning' => 'amber',
                ],
                'sort_order' => 10,
            ],
            [
                'name' => 'gray',
                'label' => 'Gray',
                'group' => 'neutral',
                'description' => 'Balanced gray tones',
                'colors' => [
                    'primary' => 'gray',
                    'danger' => 'red',
                    'gray' => 'gray',
                    'info' => 'blue',
                    'success' => 'emerald',
                    'warning' => 'amber',
                ],
                'sort_order' => 20,
            ],
            [
                'name' => 'zinc',
                'label' => 'Zinc',
                'group' => 'neutral',
                'description' => 'Modern, slightly blue-gray tones',
                'colors' => [
                    'primary' => 'zinc',
                    'danger' => 'red',
                    'gray' => 'zinc',
                    'info' => 'blue',
                    'success' => 'emerald',
                    'warning' => 'amber',
                ],
                'sort_order' => 30,
            ],
            [
                'name' => 'neutral',
                'label' => 'Neutral',
                'group' => 'neutral',
                'description' => 'Warm gray tones',
                'colors' => [
                    'primary' => 'neutral',
                    'danger' => 'red',
                    'gray' => 'neutral',
                    'info' => 'blue',
                    'success' => 'emerald',
                    'warning' => 'amber',
                ],
                'sort_order' => 40,
            ],
            [
                'name' => 'stone',
                'label' => 'Stone',
                'group' => 'neutral',
                'description' => 'Earthy, warm gray tones',
                'colors' => [
                    'primary' => 'stone',
                    'danger' => 'red',
                    'gray' => 'stone',
                    'info' => 'blue',
                    'success' => 'emerald',
                    'warning' => 'amber',
                ],
                'sort_order' => 50,
            ],

            // Color palettes (17)
            [
                'name' => 'red',
                'label' => 'Red',
                'group' => 'colors',
                'description' => 'Bold, energetic red tones',
                'colors' => [
                    'primary' => 'red',
                    'danger' => 'red',
                    'gray' => 'gray',
                    'info' => 'blue',
                    'success' => 'emerald',
                    'warning' => 'amber',
                ],
                'sort_order' => 100,
            ],
            [
                'name' => 'orange',
                'label' => 'Orange',
                'group' => 'colors',
                'description' => 'Vibrant, warm orange tones',
                'colors' => [
                    'primary' => 'orange',
                    'danger' => 'red',
                    'gray' => 'gray',
                    'info' => 'blue',
                    'success' => 'emerald',
                    'warning' => 'orange',
                ],
                'sort_order' => 110,
            ],
            [
                'name' => 'amber',
                'label' => 'Amber',
                'group' => 'colors',
                'description' => 'Golden, warm amber tones',
                'colors' => [
                    'primary' => 'amber',
                    'danger' => 'red',
                    'gray' => 'gray',
                    'info' => 'blue',
                    'success' => 'emerald',
                    'warning' => 'amber',
                ],
                'sort_order' => 120,
            ],
            [
                'name' => 'yellow',
                'label' => 'Yellow',
                'group' => 'colors',
                'description' => 'Bright, cheerful yellow tones',
                'colors' => [
                    'primary' => 'yellow',
                    'danger' => 'red',
                    'gray' => 'gray',
                    'info' => 'blue',
                    'success' => 'emerald',
                    'warning' => 'yellow',
                ],
                'sort_order' => 130,
            ],
            [
                'name' => 'lime',
                'label' => 'Lime',
                'group' => 'colors',
                'description' => 'Fresh, vibrant lime green tones',
                'colors' => [
                    'primary' => 'lime',
                    'danger' => 'red',
                    'gray' => 'gray',
                    'info' => 'blue',
                    'success' => 'lime',
                    'warning' => 'amber',
                ],
                'sort_order' => 140,
            ],
            [
                'name' => 'green',
                'label' => 'Green',
                'group' => 'colors',
                'description' => 'Natural, balanced green tones',
                'colors' => [
                    'primary' => 'green',
                    'danger' => 'red',
                    'gray' => 'gray',
                    'info' => 'blue',
                    'success' => 'green',
                    'warning' => 'amber',
                ],
                'sort_order' => 150,
            ],
            [
                'name' => 'emerald',
                'label' => 'Emerald',
                'group' => 'colors',
                'description' => 'Rich, professional emerald tones',
                'colors' => [
                    'primary' => 'emerald',
                    'danger' => 'red',
                    'gray' => 'gray',
                    'info' => 'blue',
                    'success' => 'emerald',
                    'warning' => 'amber',
                ],
                'sort_order' => 160,
            ],
            [
                'name' => 'teal',
                'label' => 'Teal',
                'group' => 'colors',
                'description' => 'Calming, balanced teal tones',
                'colors' => [
                    'primary' => 'teal',
                    'danger' => 'red',
                    'gray' => 'gray',
                    'info' => 'teal',
                    'success' => 'emerald',
                    'warning' => 'amber',
                ],
                'sort_order' => 170,
            ],
            [
                'name' => 'cyan',
                'label' => 'Cyan',
                'group' => 'colors',
                'description' => 'Bright, modern cyan tones',
                'colors' => [
                    'primary' => 'cyan',
                    'danger' => 'red',
                    'gray' => 'gray',
                    'info' => 'cyan',
                    'success' => 'emerald',
                    'warning' => 'amber',
                ],
                'sort_order' => 180,
            ],
            [
                'name' => 'sky',
                'label' => 'Sky',
                'group' => 'colors',
                'description' => 'Light, airy sky blue tones',
                'colors' => [
                    'primary' => 'sky',
                    'danger' => 'red',
                    'gray' => 'gray',
                    'info' => 'sky',
                    'success' => 'emerald',
                    'warning' => 'amber',
                ],
                'sort_order' => 190,
            ],
            [
                'name' => 'blue',
                'label' => 'Blue',
                'group' => 'colors',
                'description' => 'Classic, trustworthy blue tones',
                'colors' => [
                    'primary' => 'blue',
                    'danger' => 'red',
                    'gray' => 'gray',
                    'info' => 'blue',
                    'success' => 'emerald',
                    'warning' => 'amber',
                ],
                'sort_order' => 200,
            ],
            [
                'name' => 'indigo',
                'label' => 'Indigo',
                'group' => 'colors',
                'description' => 'Deep, sophisticated indigo tones',
                'colors' => [
                    'primary' => 'indigo',
                    'danger' => 'red',
                    'gray' => 'gray',
                    'info' => 'indigo',
                    'success' => 'emerald',
                    'warning' => 'amber',
                ],
                'sort_order' => 210,
            ],
            [
                'name' => 'violet',
                'label' => 'Violet',
                'group' => 'colors',
                'description' => 'Creative, elegant violet tones',
                'colors' => [
                    'primary' => 'violet',
                    'danger' => 'red',
                    'gray' => 'gray',
                    'info' => 'blue',
                    'success' => 'emerald',
                    'warning' => 'amber',
                ],
                'sort_order' => 220,
            ],
            [
                'name' => 'purple',
                'label' => 'Purple',
                'group' => 'colors',
                'description' => 'Royal, luxurious purple tones',
                'colors' => [
                    'primary' => 'purple',
                    'danger' => 'red',
                    'gray' => 'gray',
                    'info' => 'blue',
                    'success' => 'emerald',
                    'warning' => 'amber',
                ],
                'sort_order' => 230,
            ],
            [
                'name' => 'fuchsia',
                'label' => 'Fuchsia',
                'group' => 'colors',
                'description' => 'Vibrant, energetic fuchsia tones',
                'colors' => [
                    'primary' => 'fuchsia',
                    'danger' => 'red',
                    'gray' => 'gray',
                    'info' => 'blue',
                    'success' => 'emerald',
                    'warning' => 'amber',
                ],
                'sort_order' => 240,
            ],
            [
                'name' => 'pink',
                'label' => 'Pink',
                'group' => 'colors',
                'description' => 'Soft, friendly pink tones',
                'colors' => [
                    'primary' => 'pink',
                    'danger' => 'red',
                    'gray' => 'gray',
                    'info' => 'blue',
                    'success' => 'emerald',
                    'warning' => 'amber',
                ],
                'sort_order' => 250,
            ],
            [
                'name' => 'rose',
                'label' => 'Rose',
                'group' => 'colors',
                'description' => 'Elegant, warm rose tones',
                'colors' => [
                    'primary' => 'rose',
                    'danger' => 'red',
                    'gray' => 'gray',
                    'info' => 'blue',
                    'success' => 'emerald',
                    'warning' => 'amber',
                ],
                'sort_order' => 260,
            ],
        ];

        foreach ($palettes as $paletteData) {
            Palette::updateOrCreate(
                ['name' => $paletteData['name']],
                $paletteData
            );
        }

        $this->command->info('✅ Seeded '.count($palettes).' palettes (5 neutral + 17 colors)');
    }
}
