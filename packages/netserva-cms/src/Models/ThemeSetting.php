<?php

declare(strict_types=1);

namespace NetServa\Cms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CMS Theme Setting Model
 *
 * Stores individual theme setting values with type casting
 */
class ThemeSetting extends Model
{
    protected $table = 'cms_theme_settings';

    protected $fillable = [
        'cms_theme_id',
        'key',
        'value',
        'type',
        'category',
    ];

    /**
     * Theme relationship
     */
    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class, 'cms_theme_id');
    }

    /**
     * Get value cast to the appropriate type
     */
    public function getTypedValue(): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'float' => (float) $this->value,
            'color' => $this->value, // Return as hex string
            'json', 'array' => json_decode($this->value, true) ?? [],
            default => $this->value,
        };
    }

    /**
     * Set value with automatic type detection and conversion
     */
    public function setTypedValue(mixed $value): void
    {
        if (is_bool($value)) {
            $this->type = 'boolean';
            $this->value = $value ? '1' : '0';
        } elseif (is_int($value)) {
            $this->type = 'integer';
            $this->value = (string) $value;
        } elseif (is_float($value)) {
            $this->type = 'float';
            $this->value = (string) $value;
        } elseif (is_array($value)) {
            $this->type = 'json';
            $this->value = json_encode($value);
        } elseif ($this->isHexColor($value)) {
            $this->type = 'color';
            $this->value = (string) $value;
        } else {
            $this->type = 'string';
            $this->value = (string) $value;
        }
    }

    /**
     * Check if value is a valid hex color
     */
    protected function isHexColor(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return preg_match('/^#[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/', $value) === 1;
    }

    /**
     * Scope query to a specific category
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope query to colors category
     */
    public function scopeColors($query)
    {
        return $query->where('category', 'colors');
    }

    /**
     * Scope query to typography category
     */
    public function scopeTypography($query)
    {
        return $query->where('category', 'typography');
    }

    /**
     * Scope query to layout category
     */
    public function scopeLayout($query)
    {
        return $query->where('category', 'layout');
    }
}
