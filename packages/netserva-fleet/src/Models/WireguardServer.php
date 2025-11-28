<?php

namespace NetServa\Fleet\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NetServa\Core\Models\SshHost;

/**
 * WireGuard Server Model (merged from WG package)
 *
 * Manages WireGuard VPN server configurations.
 */
class WireguardServer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'network_cidr',
        'server_ip',
        'listen_port',
        'public_key',
        'private_key_encrypted',
        'endpoint',
        'ssh_host_id',
        'fleet_vnode_id',
        'status',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'status' => 'draft',
        'is_active' => true,
        'listen_port' => 51820,
    ];

    public function peers(): HasMany
    {
        return $this->hasMany(WireguardPeer::class);
    }

    public function activePeers(): HasMany
    {
        return $this->hasMany(WireguardPeer::class)->where('is_active', true);
    }

    public function sshHost(): BelongsTo
    {
        return $this->belongsTo(SshHost::class);
    }

    public function vnode(): BelongsTo
    {
        return $this->belongsTo(FleetVnode::class, 'fleet_vnode_id');
    }

    /**
     * Get next available IP address for a new peer
     */
    public function getNextAvailableIp(): string
    {
        [$network, $prefix] = explode('/', $this->network_cidr);
        $networkLong = ip2long($network);
        $hostBits = 32 - $prefix;
        $maxHosts = pow(2, $hostBits) - 2;

        $allocatedIps = $this->peers()
            ->pluck('allocated_ip')
            ->map(fn ($ip) => ip2long($ip))
            ->toArray();

        $allocatedIps[] = ip2long($this->server_ip);

        for ($i = 2; $i <= $maxHosts; $i++) {
            $testIp = $networkLong + $i;
            if (! in_array($testIp, $allocatedIps)) {
                return long2ip($testIp);
            }
        }

        throw new \Exception('No available IP addresses in network');
    }

    /**
     * Generate server configuration
     */
    public function generateServerConfig(): string
    {
        $config = "[Interface]\n";
        $config .= "PrivateKey = {$this->private_key}\n";
        $config .= "Address = {$this->network_cidr}\n";
        $config .= "ListenPort = {$this->listen_port}\n";

        foreach ($this->activePeers as $peer) {
            $config .= "\n[Peer]\n";
            $config .= "PublicKey = {$peer->public_key}\n";
            $config .= "AllowedIPs = {$peer->allocated_ip}/32\n";
        }

        return $config;
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
