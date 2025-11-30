<?php

namespace App\Models;

use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Palette extends Model
{
    protected $fillable = [
        'name',
        'label',
        'group',
        'description',
        'colors',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'colors' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the Filament Color classes for this palette.
     *
     * @return array<string, \Filament\Support\Colors\Color>
     */
    public function getFilamentColors(): array
    {
        $colors = $this->colors;

        return [
            'primary' => $this->getColorClass($colors['primary'] ?? 'slate'),
            'danger' => $this->getColorClass($colors['danger'] ?? 'red'),
            'gray' => $this->getColorClass($colors['gray'] ?? 'gray'),
            'info' => $this->getColorClass($colors['info'] ?? 'blue'),
            'success' => $this->getColorClass($colors['success'] ?? 'emerald'),
            'warning' => $this->getColorClass($colors['warning'] ?? 'amber'),
        ];
    }

    /**
     * Get the preview color (primary-500) for UI display.
     * Uses static hex mapping since Filament v4 uses OKLCH.
     */
    public function getPreviewColor(): string
    {
        $primaryColorName = $this->colors['primary'] ?? 'slate';

        return $this->getColorHex($primaryColorName);
    }

    /**
     * Get multiple preview colors for color dots display.
     */
    public function getPreviewColors(): array
    {
        return [
            $this->getColorHex($this->colors['primary'] ?? 'slate'),
            $this->getColorHex($this->colors['success'] ?? 'emerald'),
            $this->getColorHex($this->colors['warning'] ?? 'amber'),
        ];
    }

    /**
     * Get approximate hex color for a color name (500 shade).
     * Static mapping for Filament color palette.
     */
    protected function getColorHex(string $colorName): string
    {
        return match (strtolower($colorName)) {
            'slate' => '#64748b',
            'gray' => '#6b7280',
            'zinc' => '#71717a',
            'neutral' => '#737373',
            'stone' => '#78716c',
            'red' => '#ef4444',
            'orange' => '#f97316',
            'amber' => '#f59e0b',
            'yellow' => '#eab308',
            'lime' => '#84cc16',
            'green' => '#22c55e',
            'emerald' => '#10b981',
            'teal' => '#14b8a6',
            'cyan' => '#06b6d4',
            'sky' => '#0ea5e9',
            'blue' => '#3b82f6',
            'indigo' => '#6366f1',
            'violet' => '#8b5cf6',
            'purple' => '#a855f7',
            'fuchsia' => '#d946ef',
            'pink' => '#ec4899',
            'rose' => '#f43f5e',
            default => '#64748b', // slate-500 fallback
        };
    }

    /**
     * Get CSS variables for injecting into HTML/CSS.
     * Filament v4 uses OKLCH color format.
     */
    public function toCssVariables(): string
    {
        $colors = $this->getFilamentColors();
        $css = '';

        foreach ($colors as $name => $colorClass) {
            $shades = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

            foreach ($shades as $shade) {
                $colorValue = $colorClass[$shade];
                // Filament v4 uses OKLCH format: oklch(0.979 0.021 166.113)
                // Pass through as-is for modern CSS
                $css .= "--color-{$name}-{$shade}: {$colorValue};\n";
            }
        }

        return $css;
    }

    /**
     * Get CSS variables as associative array for API responses.
     */
    public function toCssVariablesArray(): array
    {
        $colors = $this->getFilamentColors();
        $variables = [];

        foreach ($colors as $name => $colorClass) {
            $shades = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

            foreach ($shades as $shade) {
                $colorValue = $colorClass[$shade];
                $variables["--color-{$name}-{$shade}"] = $colorValue;
            }
        }

        return $variables;
    }

    /**
     * Get the Filament Color class for a given color name.
     */
    protected function getColorClass(string $colorName): mixed
    {
        return match (strtolower($colorName)) {
            'slate' => Color::Slate,
            'gray' => Color::Gray,
            'zinc' => Color::Zinc,
            'neutral' => Color::Neutral,
            'stone' => Color::Stone,
            'red' => Color::Red,
            'orange' => Color::Orange,
            'amber' => Color::Amber,
            'yellow' => Color::Yellow,
            'lime' => Color::Lime,
            'green' => Color::Green,
            'emerald' => Color::Emerald,
            'teal' => Color::Teal,
            'cyan' => Color::Cyan,
            'sky' => Color::Sky,
            'blue' => Color::Blue,
            'indigo' => Color::Indigo,
            'violet' => Color::Violet,
            'purple' => Color::Purple,
            'fuchsia' => Color::Fuchsia,
            'pink' => Color::Pink,
            'rose' => Color::Rose,
            default => Color::Slate,
        };
    }

    /**
     * Get the default system palette.
     * Creates a default slate palette if none exists.
     */
    public static function default(): self
    {
        return static::firstOrCreate(
            ['name' => 'slate'],
            [
                'label' => 'Slate',
                'group' => 'neutral',
                'colors' => [
                    'primary' => 'slate',
                    'danger' => 'red',
                    'gray' => 'gray',
                    'info' => 'blue',
                    'success' => 'emerald',
                    'warning' => 'amber',
                ],
                'is_active' => true,
                'sort_order' => 0,
            ]
        );
    }

    /**
     * Scope: Only active palettes.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Scope: Group palettes by category.
     */
    public function scopeGrouped($query)
    {
        return $query->active()->orderBy('group')->orderBy('sort_order');
    }

    /**
     * Relationships
     */
    public function users(): HasMany
    {
        return $this->hasMany(\App\Models\User::class);
    }

    public function venues(): HasMany
    {
        return $this->hasMany(\NetServa\Fleet\Models\FleetVenue::class);
    }

    public function vsites(): HasMany
    {
        return $this->hasMany(\NetServa\Fleet\Models\FleetVsite::class);
    }

    public function vnodes(): HasMany
    {
        return $this->hasMany(\NetServa\Fleet\Models\FleetVnode::class);
    }

    public function vhosts(): HasMany
    {
        return $this->hasMany(\NetServa\Fleet\Models\FleetVhost::class);
    }
}
