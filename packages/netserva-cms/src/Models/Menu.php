<?php

declare(strict_types=1);

namespace NetServa\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * CMS Menu Model
 *
 * CRITICAL: NO NetServa dependencies - completely standalone
 */
class Menu extends Model
{
    use HasFactory;

    protected $table = 'cms_menus';

    protected static function newFactory()
    {
        return \NetServa\Cms\Database\Factories\MenuFactory::new();
    }

    protected $fillable = [
        'name',
        'location',
        'items',
        'is_active',
    ];

    protected $casts = [
        'items' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByLocation($query, string $location)
    {
        return $query->where('location', $location);
    }

    /**
     * Get menu by location (helper method)
     */
    public static function getByLocation(string $location): ?self
    {
        return static::active()
            ->byLocation($location)
            ->first();
    }

    /**
     * Get menu items as a hierarchical array
     */
    public function getMenuItems(): array
    {
        return $this->items ?? [];
    }
}
