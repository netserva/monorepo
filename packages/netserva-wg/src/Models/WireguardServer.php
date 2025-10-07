<?php

namespace NetServa\Wg\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /**
     * Get next available IP address for a new peer
     */
    public function getNextAvailableIp(): string
    {
        // Parse network CIDR to get base network and prefix
        [$network, $prefix] = explode('/', $this->network_cidr);
        $networkLong = ip2long($network);
        $hostBits = 32 - $prefix;
        $maxHosts = pow(2, $hostBits) - 2; // Subtract network and broadcast

        // Get allocated IPs
        $allocatedIps = $this->peers()
            ->pluck('allocated_ip')
            ->map(fn ($ip) => ip2long($ip))
            ->toArray();

        // Add server IP to allocated list
        $allocatedIps[] = ip2long($this->server_ip);

        // Find first available IP (starting from .2)
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

        // Add peers
        foreach ($this->activePeers as $peer) {
            $config .= "\n[Peer]\n";
            $config .= "PublicKey = {$peer->public_key}\n";
            $config .= "AllowedIPs = {$peer->allocated_ip}/32\n";
        }

        return $config;
    }

    protected static function newFactory()
    {
        return \NetServa\Wg\Database\Factories\WireguardServerFactory::new();
    }
}
