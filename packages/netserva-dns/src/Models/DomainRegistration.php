<?php

namespace NetServa\Dns\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DomainRegistration extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'domain_name',
        'domain_registrar_id',
        'status',
        'registration_date',
        'expiry_date',
        'auto_renew',
        'registrant_contact',
        'admin_contact',
        'tech_contact',
        'billing_contact',
        'nameservers',
        'privacy_enabled',
        'lock_enabled',
        'dns_config',
        'metadata',
        'notes',
        'last_synced_at',
    ];

    protected $casts = [
        'registration_date' => 'date',
        'expiry_date' => 'date',
        'auto_renew' => 'boolean',
        'nameservers' => 'array',
        'privacy_enabled' => 'boolean',
        'lock_enabled' => 'boolean',
        'dns_config' => 'array',
        'metadata' => 'array',
        'last_synced_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'unknown',
        'auto_renew' => false,
        'privacy_enabled' => false,
        'lock_enabled' => true,
    ];

    public function domainRegistrar()
    {
        return $this->belongsTo(DomainRegistrar::class);
    }

    // Scopes
    public function scopeExpiringSoon($query, $days = 60)
    {
        return $query->where('expiry_date', '<=', now()->addDays($days));
    }

    public function scopeAutoRenew($query)
    {
        return $query->where('auto_renew', true);
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
        return \NetServa\Dns\Database\Factories\DomainRegistrationFactory::new();
    }
}
