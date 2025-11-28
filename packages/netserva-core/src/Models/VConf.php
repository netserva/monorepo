<?php

namespace NetServa\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NetServa\Core\Database\Factories\VConfFactory;
use NetServa\Fleet\Models\FleetVhost;

/**
 * VConf Model (VHost Configuration Variable)
 *
 * Follows NetServa v-naming: venue → vsite → vnode → vhost → vconf → vserv
 *
 * Stores individual NetServa environment variables (up to 60).
 * All variable names are 5-char uppercase (with optional underscore).
 *
 * Examples: WPATH, UPATH, DPASS, U_UID, V_PHP, etc.
 *
 * Created: 20250107 - Updated: 20250107
 * Copyright (C) 1995-2025 Mark Constable <mc@netserva.org> (MIT License)
 */
class VConf extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): VConfFactory
    {
        return VConfFactory::new();
    }

    protected $table = 'vconfs';

    protected $fillable = [
        'fleet_vhost_id',
        'name',
        'value',
        'category',
        'is_sensitive',
    ];

    protected $casts = [
        'is_sensitive' => 'boolean',
    ];

    /**
     * Variable name must be 5 chars max: uppercase letters, numbers, and underscore
     * Examples: VHOST, AHOST, IP4_0, IP4_1, IP6_0, U_UID, V_PHP
     */
    public static function validateName(string $name): bool
    {
        return (bool) preg_match('/^[A-Z0-9_]{1,5}$/', $name) && strlen($name) <= 5;
    }

    /**
     * Get the vhost that owns this variable
     */
    public function vhost(): BelongsTo
    {
        return $this->belongsTo(FleetVhost::class, 'fleet_vhost_id');
    }

    /**
     * Categorize variable based on name
     */
    public static function categorize(string $name): string
    {
        return match (true) {
            // Paths
            str_ends_with($name, 'PATH') => 'paths',

            // Passwords (ends with PASS)
            str_ends_with($name, 'PASS') => 'passwords',

            // User/Group (starts with U_ or A_)
            str_starts_with($name, 'U_') || str_starts_with($name, 'A_') || $name === 'UUSER' => 'user',

            // Database (starts with D)
            str_starts_with($name, 'D') && ! str_ends_with($name, 'PATH') => 'database',

            // Web/PHP
            in_array($name, ['V_PHP', 'WUGID', 'WPUSR']) => 'web',

            // Mail
            in_array($name, ['MHOST', 'MPATH', 'AMAIL']) => 'mail',

            // OS
            in_array($name, ['OSTYP', 'OSREL', 'OSMIR']) => 'os',

            // Config paths (starts with C_)
            str_starts_with($name, 'C_') => 'config',

            // Domain/Host
            in_array($name, ['VHOST', 'VNODE', 'HDOMN', 'HNAME', 'AHOST']) => 'domain',

            // Network
            str_starts_with($name, 'IP') => 'network',

            // SQL commands
            in_array($name, ['SQCMD', 'SQDNS', 'EXMYS', 'EXSQL']) => 'sql',

            // Timezone
            in_array($name, ['TAREA', 'TCITY']) => 'timezone',

            // Admin
            in_array($name, ['ADMIN', 'ANAME', 'VUSER']) => 'admin',

            // Default
            default => 'other',
        };
    }

    /**
     * Check if variable is sensitive (password)
     */
    public static function isSensitive(string $name): bool
    {
        return str_ends_with($name, 'PASS');
    }

    /**
     * Scope: Get variables for a specific vhost
     */
    public function scopeForVhost($query, int $vhostId)
    {
        return $query->where('fleet_vhost_id', $vhostId);
    }

    /**
     * Scope: Get variables by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope: Get non-sensitive variables only
     */
    public function scopeNonSensitive($query)
    {
        return $query->where('is_sensitive', false);
    }

    /**
     * Scope: Get sensitive variables only
     */
    public function scopeSensitive($query)
    {
        return $query->where('is_sensitive', true);
    }

    /**
     * Get masked value for display (masks passwords)
     */
    public function getMaskedValueAttribute(): string
    {
        if ($this->is_sensitive && ! empty($this->value)) {
            return str_repeat('*', min(strlen($this->value), 16));
        }

        return $this->value ?? '';
    }

    /**
     * Accessor: Get value as bash-safe quoted string
     */
    public function getBashValueAttribute(): string
    {
        $value = $this->value ?? '';
        $escaped = addslashes($value);

        return "'{$escaped}'";
    }
}
