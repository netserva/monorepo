<?php

namespace NetServa\Config\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Database extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Ns\Database\Database\Factories\DatabaseFactory::new();
    }

    protected $fillable = [
        'connection_id',
        'name',
        'charset',
        'collation',
        'is_active',
    ];

    protected $casts = [
        'connection_id' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'is_active' => true,
    ];

    // Relationships
    public function connection(): BelongsTo
    {
        return $this->belongsTo(DatabaseConnection::class, 'connection_id');
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(DatabaseCredential::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    // Methods

    public function getCredentialsCount(): int
    {
        return $this->credentials()->count();
    }

    public function getCredentialCount(): int
    {
        return $this->getCredentialsCount();
    }
}
