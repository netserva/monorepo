<?php

namespace NetServa\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;

/**
 * VPass Model - NetServa 3.0 Unified Credential Vault
 *
 * Security Architecture:
 * - ALL sensitive credentials stored on workstation only (encrypted at rest)
 * - Can link to vsite, vnode, or vhost (nullable FKs)
 * - Hierarchical inheritance: vhost → vnode → vsite
 *
 * Service types:
 * - mysql, sqlite, postgresql (database)
 * - ssh, sftp (remote access)
 * - mail, imap, smtp (email)
 * - wordpress, admin (CMS)
 * - api, cloudflare, binarylane (providers)
 */
class VPass extends Model
{
    protected $table = 'vpass';

    protected $fillable = [
        'name',
        'service',
        'username',
        'password',
        'url',
        'port',
        'notes',
        'fleet_vsite_id',
        'fleet_vnode_id',
        'fleet_vhost_id',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'port' => 'integer',
        ];
    }

    protected $hidden = [
        'password',
    ];

    // Service type constants
    public const SERVICE_MYSQL = 'mysql';

    public const SERVICE_SQLITE = 'sqlite';

    public const SERVICE_SSH = 'ssh';

    public const SERVICE_SFTP = 'sftp';

    public const SERVICE_MAIL = 'mail';

    public const SERVICE_IMAP = 'imap';

    public const SERVICE_SMTP = 'smtp';

    public const SERVICE_WORDPRESS = 'wordpress';

    public const SERVICE_ADMIN = 'admin';

    public const SERVICE_API = 'api';

    public const SERVICE_CLOUDFLARE = 'cloudflare';

    public const SERVICE_BINARYLANE = 'binarylane';

    /**
     * Get the vsite this password belongs to
     */
    public function vsite(): BelongsTo
    {
        return $this->belongsTo(FleetVsite::class, 'fleet_vsite_id');
    }

    /**
     * Get the vnode this password belongs to
     */
    public function vnode(): BelongsTo
    {
        return $this->belongsTo(FleetVnode::class, 'fleet_vnode_id');
    }

    /**
     * Get the vhost this password belongs to
     */
    public function vhost(): BelongsTo
    {
        return $this->belongsTo(FleetVhost::class, 'fleet_vhost_id');
    }

    /**
     * Get decrypted password (for display/use)
     */
    public function getSecret(): string
    {
        return $this->password;
    }

    /**
     * Get owner (returns vhost, vnode, or vsite - whichever is set)
     */
    public function getOwnerAttribute(): ?Model
    {
        return $this->vhost ?? $this->vnode ?? $this->vsite;
    }

    /**
     * Get owner type display name
     */
    public function getOwnerTypeAttribute(): string
    {
        if ($this->fleet_vhost_id) {
            return 'VHost';
        }
        if ($this->fleet_vnode_id) {
            return 'VNode';
        }
        if ($this->fleet_vsite_id) {
            return 'VSite';
        }

        return 'Global';
    }

    /**
     * Hierarchical resolution - find credential walking up hierarchy
     *
     * @param  FleetVhost|FleetVnode|FleetVsite  $owner
     * @param  string  $service  Service type (mysql, ssh, etc.)
     * @param  string|null  $name  Credential name filter
     */
    public static function resolve(Model $owner, string $service, ?string $name = null): ?self
    {
        $query = static::where('service', $service);

        if ($name) {
            $query->where('name', $name);
        }

        // 1. Check vhost level
        if ($owner instanceof FleetVhost) {
            $credential = (clone $query)->where('fleet_vhost_id', $owner->id)->first();
            if ($credential) {
                return $credential;
            }

            // Walk up to vnode
            if ($owner->vnode) {
                return static::resolve($owner->vnode, $service, $name);
            }
        }

        // 2. Check vnode level
        if ($owner instanceof FleetVnode) {
            $credential = (clone $query)->where('fleet_vnode_id', $owner->id)->first();
            if ($credential) {
                return $credential;
            }

            // Walk up to vsite
            if ($owner->vsite) {
                return static::resolve($owner->vsite, $service, $name);
            }
        }

        // 3. Check vsite level
        if ($owner instanceof FleetVsite) {
            return (clone $query)->where('fleet_vsite_id', $owner->id)->first();
        }

        return null;
    }

    /**
     * Create or update credential for owner
     */
    public static function store(
        Model $owner,
        string $service,
        string $name,
        string $password,
        ?string $username = null,
        ?string $url = null,
        ?int $port = null,
        ?string $notes = null
    ): self {
        $ownerKey = match (true) {
            $owner instanceof FleetVhost => 'fleet_vhost_id',
            $owner instanceof FleetVnode => 'fleet_vnode_id',
            $owner instanceof FleetVsite => 'fleet_vsite_id',
            default => throw new \InvalidArgumentException('Invalid owner type'),
        };

        return static::updateOrCreate(
            [
                $ownerKey => $owner->id,
                'service' => $service,
                'name' => $name,
            ],
            [
                'password' => $password,
                'username' => $username,
                'url' => $url,
                'port' => $port,
                'notes' => $notes,
            ]
        );
    }

    /**
     * Scope by service type
     */
    public function scopeByService($query, string $service)
    {
        return $query->where('service', $service);
    }

    /**
     * Scope for vhost credentials
     */
    public function scopeForVhost($query, FleetVhost $vhost)
    {
        return $query->where('fleet_vhost_id', $vhost->id);
    }

    /**
     * Scope for vnode credentials
     */
    public function scopeForVnode($query, FleetVnode $vnode)
    {
        return $query->where('fleet_vnode_id', $vnode->id);
    }

    /**
     * Scope for vsite credentials
     */
    public function scopeForVsite($query, FleetVsite $vsite)
    {
        return $query->where('fleet_vsite_id', $vsite->id);
    }

    /**
     * Get service display name
     */
    public function getServiceDisplayAttribute(): string
    {
        return match ($this->service) {
            self::SERVICE_MYSQL => 'MySQL',
            self::SERVICE_SQLITE => 'SQLite',
            self::SERVICE_SSH => 'SSH',
            self::SERVICE_SFTP => 'SFTP',
            self::SERVICE_MAIL => 'Email',
            self::SERVICE_IMAP => 'IMAP',
            self::SERVICE_SMTP => 'SMTP',
            self::SERVICE_WORDPRESS => 'WordPress',
            self::SERVICE_ADMIN => 'Admin Panel',
            self::SERVICE_API => 'API',
            self::SERVICE_CLOUDFLARE => 'Cloudflare',
            self::SERVICE_BINARYLANE => 'BinaryLane',
            default => ucfirst($this->service),
        };
    }
}
