<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Domain Metadata Model
 *
 * Key-value metadata storage for sw_domains
 * Inspired by WHMCS tbldomains_extra pattern
 */
class DomainMetadata extends Model
{
    protected $table = 'domain_metadata';

    protected $fillable = [
        'sw_domain_id',
        'key',
        'value',
    ];

    /**
     * Belongs to a domain
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(SwDomain::class, 'sw_domain_id');
    }
}
