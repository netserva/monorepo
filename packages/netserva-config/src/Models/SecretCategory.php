<?php

namespace NetServa\Config\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SecretCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'secret_categories';

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Ns\Secrets\Database\Factories\SecretCategoryFactory::new();
    }

    protected $fillable = [
        'name',
        'description',
        'color',
        'icon',
        'status',
        'sort_order',
    ];

    protected $attributes = [
        'status' => 'active',
        'sort_order' => 0,
    ];

    // Relationships
    public function secrets()
    {
        return $this->hasMany(Secret::class);
    }

    // Scopes
    public function scopeWithSecrets($query)
    {
        return $query->has('secrets');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($query) use ($term) {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }
}
