<?php

namespace NetServa\Config\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseCredential extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Ns\Database\Database\Factories\DatabaseCredentialFactory::new();
    }

    protected $fillable = [
        'database_id',
        'username',
        'password',
        'is_active',
    ];

    protected $casts = [
        'database_id' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => 1, // Use integer for boolean default
    ];

    protected $hidden = [
        'password',
    ];

    // Relationships
    public function database(): BelongsTo
    {
        return $this->belongsTo(Database::class);
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

    public function scopeByUsername($query, string $username)
    {
        return $query->where('username', $username);
    }

    // Methods
    public function getConnectionString(): string
    {
        return sprintf('%s@localhost/%s',
            $this->username,
            $this->database->name ?? ''
        );
    }

    // Mutators
    public function setPasswordAttribute($value)
    {
        if ($value !== null) {
            $this->attributes['password'] = encrypt($value);
        } else {
            $this->attributes['password'] = null;
        }
    }
}
