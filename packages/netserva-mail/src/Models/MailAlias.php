<?php

namespace NetServa\Mail\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailAlias extends Model
{
    use HasFactory;

    protected $table = 'mail_aliases';

    protected static function newFactory()
    {
        return \NetServa\Mail\Database\Factories\MailAliasFactory::new();
    }

    protected $fillable = [
        'alias_email',
        'destination_emails',
        'is_active',
        'description',
        'tags',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'destination_emails' => 'json',
        'is_active' => 'boolean',
        'tags' => 'json',
        'metadata' => 'json',
    ];

    // Relationships
    public function mailDomain(): BelongsTo
    {
        return $this->belongsTo(MailDomain::class);
    }

    // Business Logic Methods

    /**
     * Check if alias is accessible
     */
    public function isAccessible(): bool
    {
        return $this->is_active;
    }

    /**
     * Validate destination addresses
     */
    public function validateDestinations(): array
    {
        $results = ['valid' => [], 'invalid' => []];

        if (! $this->destination_emails || ! is_array($this->destination_emails)) {
            return $results;
        }

        foreach ($this->destination_emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $results['valid'][] = $email;
            } else {
                $results['invalid'][] = $email;
            }
        }

        return $results;
    }

    /**
     * Get effective destination addresses
     */
    public function getEffectiveDestinations(): array
    {
        if (! $this->isAccessible()) {
            return [];
        }

        return $this->destination_emails ?: [];
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByEmail($query, $email)
    {
        return $query->where('alias_email', $email);
    }
}
