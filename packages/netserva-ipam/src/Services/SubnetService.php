<?php

namespace NetServa\Ipam\Services;

use Illuminate\Support\Collection;

class SubnetService
{
    /**
     * Create a new subnet
     */
    public function createSubnet(array $data): array
    {
        // Validate network format
        if (! $this->isValidCidr($data['network_address'])) {
            throw new \InvalidArgumentException("Invalid CIDR format: {$data['network_address']}");
        }

        try {
            [$network, $prefix] = explode('/', $data['network_address']);
            $totalIps = $this->calculateSubnetSize($prefix);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException("Invalid CIDR format: {$data['network_address']}");
        }

        return [
            'id' => rand(1, 1000),
            'name' => $data['name'],
            'network_address' => $data['network_address'],
            'description' => $data['description'] ?? '',
            'total_ips' => $totalIps,
            'used_ips' => 0,
            'available_ips' => $totalIps,
            'created_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Get all subnets
     */
    public function getAllSubnets(): Collection
    {
        // Mock subnet data
        return collect([
            [
                'id' => 1,
                'name' => 'Production Network',
                'network_address' => '192.168.1.0/24',
                'total_ips' => 254,
                'used_ips' => 127,
                'available_ips' => 127,
                'utilization_percentage' => 50,
            ],
            [
                'id' => 2,
                'name' => 'Development Network',
                'network_address' => '192.168.2.0/24',
                'total_ips' => 254,
                'used_ips' => 203,
                'available_ips' => 51,
                'utilization_percentage' => 80,
            ],
            [
                'id' => 3,
                'name' => 'Guest Network',
                'network_address' => '192.168.100.0/24',
                'total_ips' => 254,
                'used_ips' => 25,
                'available_ips' => 229,
                'utilization_percentage' => 10,
            ],
        ]);
    }

    /**
     * Get subnet by network address
     */
    public function getSubnet(string $networkAddress): ?array
    {
        $subnets = $this->getAllSubnets();

        return $subnets->firstWhere('network_address', $networkAddress);
    }

    /**
     * Update subnet information
     */
    public function updateSubnet(int $id, array $data): array
    {
        // Mock update
        return array_merge($this->getSubnetById($id), $data, [
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Delete subnet
     */
    public function deleteSubnet(int $id): bool
    {
        // Mock deletion
        return true;
    }

    /**
     * Find available subnets for allocation
     */
    public function findAvailableSubnets(int $minimumIps = 1): Collection
    {
        return $this->getAllSubnets()
            ->where('available_ips', '>=', $minimumIps)
            ->sortByDesc('available_ips');
    }

    /**
     * Get subnet by ID
     */
    protected function getSubnetById(int $id): array
    {
        $subnet = $this->getAllSubnets()->firstWhere('id', $id);

        if (! $subnet) {
            throw new \InvalidArgumentException("Subnet with ID {$id} not found");
        }

        return $subnet;
    }

    /**
     * Validate CIDR format
     */
    protected function isValidCidr(string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return false;
        }

        [$network, $prefix] = explode('/', $cidr);

        if (! filter_var($network, FILTER_VALIDATE_IP)) {
            return false;
        }

        $prefix = (int) $prefix;

        return $prefix >= 0 && $prefix <= 32;
    }

    /**
     * Calculate subnet size from prefix length
     */
    protected function calculateSubnetSize(int $prefixLength): int
    {
        if ($prefixLength >= 32) {
            return 0; // /32 is a single host, no usable addresses
        }

        if ($prefixLength == 31) {
            return 2; // /31 point-to-point link has 2 usable addresses
        }

        return pow(2, 32 - $prefixLength) - 2; // Subtract network and broadcast
    }
}
