<?php

namespace NetServa\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use NetServa\Core\Traits\Auditable;

/**
 * NetServa Core Base Platform Node
 *
 * Base model for platform nodes with common functionality.
 * Part of the NetServa Core foundation package.
 */
abstract class BaseInfrastructureNode extends Model implements PlatformNodeInterface
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'stack_code',
        'vtech_code',
        'status',
        'description',
        'metadata',
        'depth',
        'path',
        'parent_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'depth' => 'integer',
    ];

    // Hierarchical relationships
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    // Attributes
    public function getFullPathAttribute(): string
    {
        if ($this->parent_id && $this->parent) {
            return $this->parent->full_path.'/'.$this->name;
        }

        return $this->name ?? '';
    }

    // Interface implementations
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isHost(): bool
    {
        return $this->type === 'host';
    }

    public function isService(): bool
    {
        return $this->type === 'service';
    }

    public function getStatus(): string
    {
        return $this->status ?? 'unknown';
    }

    public function updateStatus(string $status): bool
    {
        return $this->update(['status' => $status]);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByStackCode($query, string $stackCode)
    {
        return $query->where('stack_code', $stackCode);
    }

    public function scopeByVtechCode($query, string $vtechCode)
    {
        return $query->where('vtech_code', $vtechCode);
    }

    // Helper methods
    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    public function getDepth(): int
    {
        return $this->depth ?? 0;
    }

    public function getAncestors()
    {
        $ancestors = collect();
        $node = $this->parent;

        while ($node) {
            $ancestors->prepend($node);
            $node = $node->parent;
        }

        return $ancestors;
    }

    public function getDescendants()
    {
        $descendants = collect();

        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getDescendants());
        }

        return $descendants;
    }

    public function updatePath(): void
    {
        $this->update([
            'path' => $this->full_path,
            'depth' => $this->getAncestors()->count(),
        ]);

        // Update children paths recursively
        foreach ($this->children as $child) {
            $child->updatePath();
        }
    }
}
