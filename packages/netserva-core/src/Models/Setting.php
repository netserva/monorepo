<?php

declare(strict_types=1);

namespace NetServa\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Setting Model
 *
 * Simple key-value settings storage for NetServa platform
 * NO FILAMENT DEPENDENCIES - this is a pure data model
 *
 * Uses 'netserva_settings' table (separate from Spatie's 'settings' table)
 */
class Setting extends Model
{
    protected $table = 'netserva_settings';

    protected $fillable = [
        'key',
        'value',
        'type',
        'category',
        'description',
    ];

    protected $casts = [
        'value' => 'string', // Always store as string, cast on retrieval
    ];

    /**
     * Get the typed value based on the type field
     */
    public function getTypedValue(): mixed
    {
        return match ($this->type) {
            'boolean' => (bool) $this->value,
            'integer' => (int) $this->value,
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }

    /**
     * Set a value with automatic type detection
     */
    public function setTypedValue(mixed $value): void
    {
        if (is_bool($value)) {
            $this->type = 'boolean';
            $this->value = $value ? '1' : '0';
        } elseif (is_int($value)) {
            $this->type = 'integer';
            $this->value = (string) $value;
        } elseif (is_array($value)) {
            $this->type = 'json';
            $this->value = json_encode($value);
        } else {
            $this->type = 'string';
            $this->value = (string) $value;
        }
    }

    /**
     * Helper method to get a setting value by key
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->getTypedValue() : $default;
    }

    /**
     * Helper method to set a setting value by key
     */
    public static function setValue(string $key, mixed $value, ?string $category = null): self
    {
        $setting = static::firstOrNew(['key' => $key]);
        $setting->setTypedValue($value);

        if ($category) {
            $setting->category = $category;
        }

        $setting->save();

        return $setting;
    }
}
