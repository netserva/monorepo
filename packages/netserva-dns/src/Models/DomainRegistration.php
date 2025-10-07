<?php

namespace NetServa\Dns\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use NetServa\Core\Models\InfrastructureNode;

class DomainRegistration extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'infrastructure_node_id',
        'domain_registrar_id',
        'domain_name',
        'registration_date',
        'expiry_date',
        'renewal_date',
        'auto_renew',
        'status',
        'registrant_contact',
        'nameservers',
        'description',
    ];

    protected $casts = [
        'registration_date' => 'date',
        'expiry_date' => 'date',
        'renewal_date' => 'date',
        'auto_renew' => 'boolean',
        'registrant_contact' => 'array',
        'nameservers' => 'array',
    ];

    // Relationships
    public function infrastructureNode()
    {
        return $this->belongsTo(InfrastructureNode::class);
    }

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
        return \Ns\Domain\Database\Factories\DomainRegistrationFactory::new();
    }
}
