<?php

namespace NetServa\Cli\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NetServa\Fleet\Models\FleetVhost;

/**
 * Mail Credential Model
 *
 * NetServa 3.0 Security Architecture:
 * - Cleartext passwords stored ONLY on workstation database (encrypted at rest)
 * - Remote servers receive SHA512-CRYPT hashes only (Dovecot compatible)
 * - Provides secure credential vault for helpdesk/admin password retrieval
 *
 * Security:
 * - cleartext_password uses Laravel's 'encrypted' cast (APP_KEY encryption)
 * - Database file: ~/.ns/database/database.sqlite (workstation only)
 * - NEVER synced to remote servers
 * - Supports password rotation tracking and audit trail
 */
class MailCredential extends Model
{
    protected $table = 'mail_credentials';

    protected $fillable = [
        'fleet_vhost_id',
        'email',
        'cleartext_password',
        'password_hint',
        'notes',
        'is_active',
        'last_rotated_at',
    ];

    protected $casts = [
        'cleartext_password' => 'encrypted', // Laravel auto-encrypts using APP_KEY
        'is_active' => 'boolean',
        'last_rotated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'cleartext_password', // Hide from JSON by default (security)
    ];

    /**
     * Get the vhost this credential belongs to
     */
    public function vhost(): BelongsTo
    {
        return $this->belongsTo(FleetVhost::class, 'fleet_vhost_id');
    }

    /**
     * Get cleartext password (for admin display only)
     *
     * @return string Decrypted password
     */
    public function getCleartextPassword(): string
    {
        return $this->cleartext_password;
    }

    /**
     * Check if password needs rotation (90 days default)
     */
    public function needsRotation(int $days = 90): bool
    {
        if (! $this->last_rotated_at) {
            return true;
        }

        return $this->last_rotated_at->addDays($days)->isPast();
    }

    /**
     * Mark password as rotated
     */
    public function markRotated(): void
    {
        $this->update(['last_rotated_at' => now()]);
    }

    /**
     * Get local part of email (before @)
     */
    public function getLocalPartAttribute(): string
    {
        return substr($this->email, 0, strpos($this->email, '@'));
    }

    /**
     * Get domain part of email (after @)
     */
    public function getDomainAttribute(): string
    {
        return substr(strstr($this->email, '@'), 1);
    }

    /**
     * Scope to active credentials only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by domain
     */
    public function scopeByDomain($query, string $domain)
    {
        return $query->where('email', 'like', "%@{$domain}");
    }

    /**
     * Scope by vhost
     */
    public function scopeByVhost($query, int $vhostId)
    {
        return $query->where('fleet_vhost_id', $vhostId);
    }

    /**
     * Check if email is admin (for catch-all aliases)
     */
    public function isAdmin(): bool
    {
        return $this->local_part === 'admin';
    }
}
