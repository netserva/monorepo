<?php

namespace NetServa\Mail\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NetServa\Core\Models\InfrastructureNode;
use NetServa\Ops\Traits\Auditable;

class MailDomain extends Model
{
    use Auditable, HasFactory;

    protected static function newFactory()
    {
        return \NetServa\Mail\Database\Factories\MailDomainFactory::new();
    }

    protected $fillable = [
        'name',
        'domain',
        'mail_server_id',
        'is_active',
        'enable_dkim',
        'enable_spf',
        'enable_dmarc',
        'relay_enabled',
        'relay_host',
        'relay_port',
        'description',
        'tags',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'enable_dkim' => 'boolean',
        'enable_spf' => 'boolean',
        'enable_dmarc' => 'boolean',
        'relay_enabled' => 'boolean',
        'relay_port' => 'integer',
        'tags' => 'json',
        'metadata' => 'json',
    ];

    // Relationships
    public function infrastructureNode(): BelongsTo
    {
        return $this->belongsTo(InfrastructureNode::class);
    }

    public function mailServer(): BelongsTo
    {
        return $this->belongsTo(MailServer::class);
    }

    public function mailboxes(): HasMany
    {
        return $this->hasMany(Mailbox::class);
    }

    public function mailAliases(): HasMany
    {
        return $this->hasMany(MailAlias::class);
    }

    // Business Logic Methods

    /**
     * Check if domain is accessible
     */
    public function isAccessible(): bool
    {
        return $this->is_active;
    }

    /**
     * Get DKIM DNS record
     */
    public function getDkimDnsRecord(): ?string
    {
        if (! $this->enable_dkim) {
            return null;
        }

        return "default._domainkey.{$this->domain} IN TXT \"v=DKIM1; k=rsa; p=\"";
    }

    /**
     * Get SPF DNS record
     */
    public function getSpfDnsRecord(): ?string
    {
        if (! $this->enable_spf) {
            return null;
        }

        return "{$this->domain} IN TXT \"v=spf1 mx a -all\"";
    }

    /**
     * Get DMARC DNS record
     */
    public function getDmarcDnsRecord(): ?string
    {
        if (! $this->enable_dmarc) {
            return null;
        }

        return "_dmarc.{$this->domain} IN TXT \"v=DMARC1; p=quarantine\"";
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWithRelay($query)
    {
        return $query->where('relay_enabled', true);
    }
}
