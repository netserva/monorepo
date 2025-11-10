<?php

declare(strict_types=1);

namespace NetServa\Cms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\File;

/**
 * CMS Theme Model
 *
 * Manages theme metadata, settings, and inheritance
 */
class Theme extends Model
{
    protected $table = 'cms_themes';

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'version',
        'author',
        'parent_theme',
        'is_active',
        'manifest',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'manifest' => 'array',
    ];

    /**
     * Theme settings relationship
     */
    public function settings(): HasMany
    {
        return $this->hasMany(ThemeSetting::class, 'cms_theme_id');
    }

    /**
     * Parent theme relationship
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Theme::class, 'parent_theme', 'name');
    }

    /**
     * Child themes relationship
     */
    public function children(): HasMany
    {
        return $this->hasMany(Theme::class, 'parent_theme', 'name');
    }

    /**
     * Get the currently active theme
     */
    public static function active(): ?self
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Get theme base path
     */
    public function path(): string
    {
        return resource_path("themes/{$this->name}");
    }

    /**
     * Get theme views path
     */
    public function viewPath(): string
    {
        return "{$this->path()}/resources/views";
    }

    /**
     * Get theme assets path
     */
    public function assetsPath(): string
    {
        return "{$this->path()}/resources";
    }

    /**
     * Check if theme path exists
     */
    public function exists(): bool
    {
        return File::exists($this->path());
    }

    /**
     * Get a theme setting value with fallback chain:
     * 1. Database setting (user customization)
     * 2. Manifest default
     * 3. Provided default
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        // Try database setting first (user customization)
        $setting = $this->settings()->where('key', $key)->first();

        if ($setting) {
            return $setting->getTypedValue();
        }

        // Fallback to manifest default
        $manifestValue = data_get($this->manifest, "settings.{$key}");

        if ($manifestValue !== null) {
            return $manifestValue;
        }

        // Use provided default
        return $default;
    }

    /**
     * Set a theme setting (saves to database)
     */
    public function setSetting(string $key, mixed $value, string $category = 'general'): ThemeSetting
    {
        // Create temporary setting to determine type and value
        $temp = new ThemeSetting;
        $temp->setTypedValue($value);

        // Update or create with all required fields
        $setting = $this->settings()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $temp->value,
                'type' => $temp->type,
                'category' => $category,
            ]
        );

        return $setting;
    }

    /**
     * Load theme manifest from theme.json file
     */
    public static function loadManifest(string $themeName): ?array
    {
        $path = resource_path("themes/{$themeName}/theme.json");

        if (! File::exists($path)) {
            return null;
        }

        $contents = File::get($path);
        $manifest = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON in theme manifest for '{$themeName}': ".json_last_error_msg());
        }

        return $manifest;
    }

    /**
     * Get theme screenshot URL
     */
    public function screenshotUrl(): ?string
    {
        $screenshot = $this->manifest['screenshot'] ?? 'screenshot.png';
        $path = public_path("themes/{$this->name}/{$screenshot}");

        if (File::exists($path)) {
            return asset("themes/{$this->name}/{$screenshot}");
        }

        return null;
    }

    /**
     * Get all available templates for a given type
     */
    public function templates(string $type = 'page'): array
    {
        return $this->manifest['templates'][$type] ?? [];
    }

    /**
     * Get theme color palette
     */
    public function colors(): array
    {
        return $this->manifest['settings']['colors']['palette'] ?? [];
    }

    /**
     * Get theme typography settings
     */
    public function typography(): array
    {
        return $this->manifest['settings']['typography'] ?? [];
    }

    /**
     * Scope query to active theme
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
