<?php

declare(strict_types=1);

namespace NetServa\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * CMS Category Model
 *
 * CRITICAL: NO NetServa dependencies - completely standalone
 */
class Category extends Model
{
    use HasFactory;
    use HasSlug;

    protected $table = 'cms_categories';

    protected static function newFactory()
    {
        return \NetServa\Cms\Database\Factories\CategoryFactory::new();
    }

    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'cms_category_post');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
