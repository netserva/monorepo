<?php

namespace NetServa\Dns\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DomainRegistrar extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'registrar_type',
        'api_endpoint',
        'api_key_encrypted',
        'api_secret_encrypted',
        'additional_config',
        'status',
        'description',
    ];

    protected $casts = [
        'additional_config' => 'array',
    ];

    // Relationships
    public function domainRegistrations()
    {
        return $this->hasMany(DomainRegistration::class);
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('registrar_type', $type);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Ns\Domain\Database\Factories\DomainRegistrarFactory::new();
    }
}
