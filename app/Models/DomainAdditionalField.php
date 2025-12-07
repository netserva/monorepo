<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Domain Additional Field Model
 *
 * TLD-specific additional data (e.g., .au eligibility requirements)
 * Inspired by WHMCS tbldomainsadditionalfields
 */
class DomainAdditionalField extends Model
{
    protected $fillable = [
        'sw_domain_id',
        'field_name',
        'field_value',
    ];

    /**
     * Belongs to a domain
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(SwDomain::class, 'sw_domain_id');
    }

    /**
     * Common .au domain field names
     */
    public const AU_REGISTRANT_NAME = 'Registrant Name';

    public const AU_REGISTRANT_ID = 'Registrant ID';

    public const AU_REGISTRANT_ID_TYPE = 'Registrant ID Type';

    public const AU_ELIGIBILITY_NAME = 'Eligibility Name';

    public const AU_ELIGIBILITY_TYPE = 'Eligibility Type';

    public const AU_ELIGIBILITY_ID = 'Eligibility ID';

    public const AU_ELIGIBILITY_ID_TYPE = 'Eligibility ID Type';

    public const AU_ELIGIBILITY_REASON = 'Eligibility Reason';

    /**
     * Check if this is an .au eligibility field
     */
    public function isAuEligibilityField(): bool
    {
        return in_array($this->field_name, [
            self::AU_REGISTRANT_NAME,
            self::AU_REGISTRANT_ID,
            self::AU_REGISTRANT_ID_TYPE,
            self::AU_ELIGIBILITY_NAME,
            self::AU_ELIGIBILITY_TYPE,
            self::AU_ELIGIBILITY_ID,
            self::AU_ELIGIBILITY_ID_TYPE,
            self::AU_ELIGIBILITY_REASON,
        ]);
    }
}
