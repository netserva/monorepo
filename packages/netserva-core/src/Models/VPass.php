<?php

namespace NetServa\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use NetServa\Fleet\Models\FleetVenue;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;

/**
 * VPass Model - NetServa 3.0 Unified Credential Vault
 *
 * Security Architecture:
 * - ALL sensitive credentials stored on workstation only (encrypted at rest)
 * - Polymorphic ownership: venue/vsite/vnode/vhost
 * - Hierarchical inheritance: vhost → vnode → vsite → venue
 * - Supports: mail passwords, API keys, DB credentials, SSL keys, OAuth tokens
 *
 * Naming Convention:
 * - ptype = Password Type (VMAIL, APKEY, DBPWD, SSLKY, OAUTH)
 * - pserv = Service Provider (cloudflare, binarylane, proxmox, dovecot, mysql)
 * - pname = Identifier Name (email, key name, username, account ID)
 * - pdata = Encrypted Secret (password, API key, token, private key)
 * - pmeta = Metadata (JSON: zone IDs, endpoints, account info)
 * - pstat = Active Status (1=active, 0=disabled)
 * - pdate = Last Rotated Timestamp
 * - pused = Last Used Timestamp
 * - pexpd = Expiration Timestamp
 * - pnote = Admin Notes
 */
class VPass extends Model
{
    protected $table = 'vpass';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'ptype',
        'pserv',
        'pname',
        'pdata',
        'pmeta',
        'pstat',
        'pdate',
        'pused',
        'pexpd',
        'pnote',
    ];

    protected $casts = [
        'pdata' => 'encrypted', // Laravel auto-encrypts using APP_KEY
        'pmeta' => 'array',
        'pstat' => 'boolean',
        'pdate' => 'datetime',
        'pused' => 'datetime',
        'pexpd' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'pdata', // Hide from JSON by default (security)
    ];

    /**
     * Polymorphic owner relationship (FleetVenue, FleetVsite, FleetVnode, FleetVhost)
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get decrypted secret (for admin display only)
     *
     * @return string Decrypted password/API key/token
     */
    public function getSecret(): string
    {
        return $this->pdata;
    }

    /**
     * Hierarchical inheritance resolution (NetServa 3.0 pattern)
     *
     * Resolution order:
     * 1. VHost-specific credential (if owner is VHost)
     * 2. VNode-level credential (parent of VHost)
     * 3. VSite-level credential (parent of VNode)
     * 4. Venue-level credential (parent of VSite)
     * 5. NULL if not found
     *
     * @param  Model  $owner  Starting owner (FleetVhost, FleetVnode, FleetVsite, FleetVenue)
     * @param  string  $pserv  Service provider (cloudflare, binarylane, proxmox, etc.)
     * @param  string  $pname  Identifier name (default = 'default')
     * @param  string|null  $ptype  Password type filter (APKEY, DBPWD, etc.)
     * @return self|null Resolved credential or null
     */
    public static function resolve(
        Model $owner,
        string $pserv,
        string $pname = 'default',
        ?string $ptype = null
    ): ?self {
        // Build base query
        $query = static::where('pserv', $pserv)
            ->where('pname', $pname)
            ->where('pstat', true); // Only active credentials

        if ($ptype) {
            $query->where('ptype', $ptype);
        }

        // 1. Check owner directly
        $credential = (clone $query)
            ->where('owner_type', get_class($owner))
            ->where('owner_id', $owner->id)
            ->first();

        if ($credential) {
            $credential->touch('pused'); // Update last used timestamp

            return $credential;
        }

        // 2. Walk up hierarchy if VHost
        if ($owner instanceof FleetVhost && $owner->vnode) {
            $credential = static::resolve($owner->vnode, $pserv, $pname, $ptype);
            if ($credential) {
                return $credential;
            }
        }

        // 3. Walk up hierarchy if VNode
        if ($owner instanceof FleetVnode && $owner->vsite) {
            $credential = static::resolve($owner->vsite, $pserv, $pname, $ptype);
            if ($credential) {
                return $credential;
            }
        }

        // 4. Walk up hierarchy if VSite
        if ($owner instanceof FleetVsite && $owner->venue) {
            $credential = static::resolve($owner->venue, $pserv, $pname, $ptype);
            if ($credential) {
                return $credential;
            }
        }

        // 5. Not found in hierarchy
        return null;
    }

    /**
     * Find or create credential for owner
     *
     * @param  Model  $owner  Owner model
     * @param  string  $pserv  Service provider
     * @param  string  $pname  Identifier name
     * @param  string  $ptype  Password type
     * @param  string  $pdata  Secret data
     * @param  array|null  $pmeta  Metadata
     */
    public static function findOrCreate(
        Model $owner,
        string $pserv,
        string $pname,
        string $ptype,
        string $pdata,
        ?array $pmeta = null
    ): self {
        return static::updateOrCreate(
            [
                'owner_type' => get_class($owner),
                'owner_id' => $owner->id,
                'pserv' => $pserv,
                'pname' => $pname,
            ],
            [
                'ptype' => $ptype,
                'pdata' => $pdata,
                'pmeta' => $pmeta,
                'pstat' => true,
                'pdate' => now(),
            ]
        );
    }

    /**
     * Check if credential needs rotation
     *
     * @param  int  $days  Days before rotation required (default 90)
     * @return bool True if rotation needed
     */
    public function needsRotation(int $days = 90): bool
    {
        if (! $this->pdate) {
            return true;
        }

        return $this->pdate->addDays($days)->isPast();
    }

    /**
     * Check if credential is expired
     *
     * @return bool True if expired
     */
    public function isExpired(): bool
    {
        if (! $this->pexpd) {
            return false;
        }

        return $this->pexpd->isPast();
    }

    /**
     * Mark credential as rotated
     */
    public function markRotated(): void
    {
        $this->update(['pdate' => now()]);
    }

    /**
     * Disable credential without deletion
     */
    public function disable(): void
    {
        $this->update(['pstat' => false]);
    }

    /**
     * Enable disabled credential
     */
    public function enable(): void
    {
        $this->update(['pstat' => true]);
    }

    /**
     * Scope to active credentials only
     */
    public function scopeActive($query)
    {
        return $query->where('pstat', true);
    }

    /**
     * Scope by service provider
     */
    public function scopeByService($query, string $pserv)
    {
        return $query->where('pserv', $pserv);
    }

    /**
     * Scope by password type
     */
    public function scopeByType($query, string $ptype)
    {
        return $query->where('ptype', $ptype);
    }

    /**
     * Scope by owner model
     */
    public function scopeByOwner($query, Model $owner)
    {
        return $query->where('owner_type', get_class($owner))
            ->where('owner_id', $owner->id);
    }

    /**
     * Scope credentials needing rotation
     */
    public function scopeNeedsRotation($query, int $days = 90)
    {
        return $query->where(function ($q) use ($days) {
            $q->whereNull('pdate')
                ->orWhere('pdate', '<', now()->subDays($days));
        });
    }

    /**
     * Scope expired credentials
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('pexpd')
            ->where('pexpd', '<', now());
    }

    /**
     * Get owner type display name
     */
    public function getOwnerTypeDisplayAttribute(): string
    {
        return match ($this->owner_type) {
            'NetServa\\Fleet\\Models\\FleetVenue' => 'Venue',
            'NetServa\\Fleet\\Models\\FleetVsite' => 'VSite',
            'NetServa\\Fleet\\Models\\FleetVnode' => 'VNode',
            'NetServa\\Fleet\\Models\\FleetVhost' => 'VHost',
            default => 'Unknown',
        };
    }

    /**
     * Get credential type display name
     */
    public function getTypeDisplayAttribute(): string
    {
        return match ($this->ptype) {
            'VMAIL' => 'Email Password',
            'APKEY' => 'API Key',
            'DBPWD' => 'Database Password',
            'SSLKY' => 'SSL Private Key',
            'OAUTH' => 'OAuth Token',
            default => $this->ptype,
        };
    }
}
