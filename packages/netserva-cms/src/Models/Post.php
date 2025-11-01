<?php

declare(strict_types=1);

namespace NetServa\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * CMS Post Model
 *
 * CRITICAL: NO NetServa dependencies - completely standalone
 */
class Post extends Model implements HasMedia
{
    use HasFactory;
    use HasSlug;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $table = 'cms_posts';

    protected static function newFactory()
    {
        return \NetServa\Cms\Database\Factories\PostFactory::new();
    }

    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'featured_image',
        'category_id',
        'is_published',
        'published_at',
        'word_count',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'og_image',
        'twitter_card',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'word_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Post $post) {
            if ($post->content) {
                $post->word_count = str_word_count(strip_tags($post->content));
            }
        });
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'cms_post_tag');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeWithTag($query, $tagId)
    {
        return $query->whereHas('tags', function ($q) use ($tagId) {
            $q->where('cms_tags.id', $tagId);
        });
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
        return $this->meta_title ?? $this->title;
    }

    public function getMetaDescription(): ?string
    {
        return $this->meta_description ?? $this->excerpt;
    }

    public function getReadingTime(): int
    {
        // Assuming average reading speed of 200 words per minute
        return (int) ceil($this->word_count / 200);
    }
}
