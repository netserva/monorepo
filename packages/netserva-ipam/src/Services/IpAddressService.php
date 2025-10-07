<?php

namespace NetServa\Ipam\Services;

use Illuminate\Support\Collection;

class IpAddressService
{
    /**
     * Allocate a specific IP address
     */
    public function allocateSpecificIp(string $ip): ?array
    {
        // Validate IP format
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException("Invalid IP address format: {$ip}");
        }

        // Mock allocation - in real implementation would check database
        return [
            'ip_address' => $ip,
            'subnet' => $this->getSubnetForIp($ip),
            'status' => 'allocated',
            'allocated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Allocate next available IP from subnet
     */
    public function allocateFromSubnet(string $network): ?array
    {
        // Parse network CIDR
        [$networkAddr, $prefixLength] = explode('/', $network);

        // Generate mock available IP
        $ipParts = explode('.', $networkAddr);
        $lastOctet = (int) $ipParts[3];
        $nextIp = implode('.', [$ipParts[0], $ipParts[1], $ipParts[2], $lastOctet + 1]);

        return [
            'ip_address' => $nextIp,
            'subnet' => $network,
            'status' => 'allocated',
            'allocated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Release an IP address
     */
    public function releaseIp(string $ip): bool
    {
        // Validate IP format
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException("Invalid IP address format: {$ip}");
        }

        // Mock release - in real implementation would update database
        return true;
    }

    /**
     * Check if IP address is available
     */
    public function isAvailable(string $ip): bool
    {
        // Mock availability check
        return rand(0, 1) === 1;
    }

    /**
     * Get IP address details
     */
    public function getIpDetails(string $ip): array
    {
        return [
            'ip_address' => $ip,
            'subnet' => $this->getSubnetForIp($ip),
            'status' => rand(0, 1) ? 'allocated' : 'available',
            'hostname' => $this->getHostnameForIp($ip),
            'last_seen' => now()->subMinutes(rand(1, 60))->toDateTimeString(),
            'mac_address' => $this->generateMockMacAddress(),
        ];
    }

    /**
     * Get all IP addresses in subnet
     */
    public function getSubnetIps(string $network): Collection
    {
        [$networkAddr, $prefixLength] = explode('/', $network);
        $ipParts = explode('.', $networkAddr);

        $ips = collect();

        // Generate mock IPs for demonstration
        for ($i = 1; $i <= 10; $i++) {
            $ip = implode('.', [$ipParts[0], $ipParts[1], $ipParts[2], $i]);
            $ips->push([
                'ip_address' => $ip,
                'status' => rand(0, 1) ? 'allocated' : 'available',
                'hostname' => rand(0, 1) ? "host-{$i}.example.com" : null,
                'last_seen' => now()->subMinutes(rand(1, 1440))->toDateTimeString(),
            ]);
        }

        return $ips;
    }

    /**
     * Get subnet for IP address
     */
    protected function getSubnetForIp(string $ip): string
    {
        $ipParts = explode('.', $ip);

        return implode('.', [$ipParts[0], $ipParts[1], $ipParts[2], '0']).'/24';
    }

    /**
     * Get hostname for IP address
     */
    protected function getHostnameForIp(string $ip): ?string
    {
        // Mock hostname resolution
        $hostnames = [
            'router.example.com',
            'server.example.com',
            'workstation.example.com',
            'printer.example.com',
            null, // Some IPs don't have hostnames
        ];

        return $hostnames[array_rand($hostnames)];
    }

    /**
     * Generate mock MAC address
     */
    protected function generateMockMacAddress(): string
    {
        $mac = [];
        for ($i = 0; $i < 6; $i++) {
            $mac[] = sprintf('%02x', rand(0, 255));
        }

        return implode(':', $mac);
    }
}
