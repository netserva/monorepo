<?php

declare(strict_types=1);

namespace NetServa\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * CMS Page Model
 *
 * CRITICAL: NO NetServa dependencies - completely standalone
 */
class Page extends Model implements HasMedia
{
    use HasFactory;
    use HasSlug;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $table = 'cms_pages';

    protected static function newFactory()
    {
        return \NetServa\Cms\Database\Factories\PageFactory::new();
    }

    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'template',
        'parent_id',
        'order',
        'is_published',
        'published_at',
        'meta',
        'settings',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'meta' => 'array',
        'settings' => 'array',
        'order' => 'integer',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Page::class, 'parent_id')
            ->orderBy('order');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function scopeRootPages($query)
    {
        return $query->whereNull('parent_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured_image')
            ->singleFile();

        $this->addMediaCollection('gallery');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getMetaTitle(): ?string
    {
        return $this->meta['title'] ?? $this->title;
    }

    public function getMetaDescription(): ?string
    {
        return $this->meta['description'] ?? $this->excerpt;
    }
}
