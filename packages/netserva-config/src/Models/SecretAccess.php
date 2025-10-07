<?php

namespace NetServa\Config\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecretAccess extends Model
{
    use HasFactory;

    protected $table = 'secret_accesses';

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Ns\Secrets\Database\Factories\SecretAccessFactory::new();
    }

    protected $fillable = [
        'secret_id',
        'user_id',
        'access_type',
        'ip_address',
        'user_agent',
        'source',
        'additional_context',
        'accessed_at',
    ];

    protected $casts = [
        'additional_context' => 'array',
        'accessed_at' => 'datetime',
    ];

    public $timestamps = false; // We use accessed_at instead

    // Relationships

    public function secret(): BelongsTo
    {
        return $this->belongsTo(Secret::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes

    public function scopeByAccessType($query, string $accessType)
    {
        return $query->where('access_type', $accessType);
    }

    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }

    public function scopeByUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('accessed_at', '>=', now()->subHours($hours));
    }

    public function scopeBySecret($query, Secret $secret)
    {
        return $query->where('secret_id', $secret->id);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('accessed_at', [$startDate, $endDate]);
    }
}
