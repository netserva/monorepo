<?php

namespace NetServa\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * NetServa Core SSH Connection Model
 *
 * Represents an active or historical SSH connection.
 * Part of the NetServa Core foundation package.
 */
class SshConnection extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \NetServa\Core\Database\Factories\SshConnectionFactory::new();
    }

    protected $fillable = [
        'ssh_host_id',
        'ssh_key_id',
        'name',
        'hostname',
        'port',
        'username',
        'connection_type',
        'connection_string',
        'description',
        'last_connected_at',
        'last_tested_at',
        'is_reachable',
        'ssh_options',
        'is_active',
    ];

    protected $casts = [
        'port' => 'integer',
        'ssh_options' => 'array',
        'last_connected_at' => 'datetime',
        'last_tested_at' => 'datetime',
        'is_reachable' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'connection_type' => 'netserva_managed',
        'is_active' => true,
        'port' => 22,
    ];

    /**
     * Get the SSH host this connection belongs to
     */
    public function sshHost(): BelongsTo
    {
        return $this->belongsTo(SshHost::class);
    }

    /**
     * Get the SSH key used for this connection
     */
    public function sshKey(): BelongsTo
    {
        return $this->belongsTo(SshKey::class);
    }

    /**
     * Scope to get only active connections
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get reachable connections
     */
    public function scopeReachable($query)
    {
        return $query->where('is_reachable', true);
    }

    /**
     * Scope to get recently used connections
     */
    public function scopeRecentlyUsed($query, int $days = 7)
    {
        return $query->where('last_connected_at', '>=', now()->subDays($days));
    }

    /**
     * Get the display name for this connection
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: $this->getConnectionDisplayString();
    }

    /**
     * Get a formatted connection string for display
     */
    public function getConnectionDisplayString(): string
    {
        $display = $this->username.'@'.$this->hostname;

        if ($this->port && $this->port !== 22) {
            $display .= ':'.$this->port;
        }

        return $display;
    }

    /**
     * Get the full connection string
     */
    public function getFullConnectionString(): string
    {
        return $this->connection_string ?: $this->getConnectionDisplayString();
    }

    /**
     * Check if connection has been used recently
     */
    public function isRecentlyUsed(int $days = 7): bool
    {
        if (! $this->last_connected_at) {
            return false;
        }

        return $this->last_connected_at->isAfter(now()->subDays($days));
    }

    /**
     * Get connection status for display
     */
    public function getStatusAttribute(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        if ($this->is_reachable === null) {
            return 'unknown';
        }

        return $this->is_reachable ? 'reachable' : 'unreachable';
    }

    /**
     * Get status color for UI display
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'reachable' => 'success',
            'unreachable' => 'danger',
            'inactive' => 'gray',
            default => 'warning',
        };
    }

    /**
     * Update the connection as recently used
     */
    public function markAsUsed(): void
    {
        $this->update([
            'last_connected_at' => now(),
        ]);
    }

    /**
     * Update the connection reachability status
     */
    public function updateReachability(bool $isReachable): void
    {
        $this->update([
            'is_reachable' => $isReachable,
            'last_tested_at' => now(),
        ]);
    }

    /**
     * Get the connection type with better formatting
     */
    public function getFormattedConnectionTypeAttribute(): string
    {
        return match ($this->connection_type) {
            'netserva_managed' => 'NetServa Managed',
            'password' => 'Password',
            'key' => 'SSH Key',
            'agent' => 'SSH Agent',
            default => ucfirst($this->connection_type),
        };
    }

    /**
     * Get related tunnels or port forwards (placeholder for future expansion)
     */
    public function getActiveTunnelsAttribute(): array
    {
        // This could be expanded to track active port forwards
        // For now, return empty array
        return [];
    }

    /**
     * Check if connection uses SSH key authentication
     */
    public function usesKeyAuth(): bool
    {
        return $this->ssh_key_id !== null ||
               in_array($this->connection_type, ['key', 'netserva_managed']);
    }

    /**
     * Check if connection uses password authentication
     */
    public function usesPasswordAuth(): bool
    {
        return $this->connection_type === 'password';
    }

    /**
     * Get connection age in human readable format
     */
    public function getConnectionAgeAttribute(): string
    {
        if (! $this->last_connected_at) {
            return 'Never connected';
        }

        return $this->last_connected_at->diffForHumans();
    }

    /**
     * Get test age in human readable format
     */
    public function getTestAgeAttribute(): string
    {
        if (! $this->last_tested_at) {
            return 'Never tested';
        }

        return $this->last_tested_at->diffForHumans();
    }

    /**
     * Generate a unique identifier for this connection
     */
    public function getConnectionIdentifierAttribute(): string
    {
        return md5($this->hostname.':'.$this->port.':'.$this->username);
    }
}
