<?php

declare(strict_types=1);

namespace NetServa\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * CMS Tag Model
 *
 * CRITICAL: NO NetServa dependencies - completely standalone
 */
class Tag extends Model
{
    use HasFactory;
    use HasSlug;

    protected $table = 'cms_tags';

    protected static function newFactory()
    {
        return \NetServa\Cms\Database\Factories\TagFactory::new();
    }

    protected $fillable = [
        'name',
        'slug',
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
        return $this->belongsToMany(Post::class, 'cms_post_tag');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
