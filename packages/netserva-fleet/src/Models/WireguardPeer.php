<?php

namespace NetServa\Fleet\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WireGuard Peer Model (merged from WG package)
 *
 * Manages WireGuard VPN peer configurations.
 */
class WireguardPeer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'wireguard_server_id',
        'allocated_ip',
        'allowed_ips',
        'public_key',
        'private_key_encrypted',
        'status',
        'last_handshake',
        'is_active',
    ];

    protected $casts = [
        'allowed_ips' => 'array',
        'last_handshake' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'status' => 'disconnected',
        'is_active' => true,
        'allowed_ips' => '["0.0.0.0/0"]',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(WireguardServer::class, 'wireguard_server_id');
    }

    /**
     * Generate client configuration for this peer
     */
    public function generateClientConfig(): string
    {
        $server = $this->server;

        $config = "[Interface]\n";
        $config .= "PrivateKey = {$this->private_key}\n";
        $config .= "Address = {$this->allocated_ip}/32\n";

        $config .= "\n[Peer]\n";
        $config .= "PublicKey = {$server->public_key}\n";
        $config .= "Endpoint = {$server->endpoint}:{$server->listen_port}\n";
        $config .= 'AllowedIPs = '.implode(', ', $this->allowed_ips)."\n";
        $config .= "PersistentKeepalive = 25\n";

        return $config;
    }

    /**
     * Check if peer is currently connected
     */
    public function isConnected(): bool
    {
        return $this->status === 'connected'
            && $this->last_handshake
            && $this->last_handshake->gt(now()->subMinutes(5));
    }

    /**
     * Get time since last handshake in human readable format
     */
    public function getLastSeenAttribute(): ?string
    {
        if (! $this->last_handshake) {
            return null;
        }

        return $this->last_handshake->diffForHumans();
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeConnected($query)
    {
        return $query->where('status', 'connected');
    }
}
