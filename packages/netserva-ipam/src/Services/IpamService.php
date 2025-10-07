<?php

namespace NetServa\Ipam\Services;

use NetServa\Ipam\Models\IpAddress;
use NetServa\Ipam\Models\IpNetwork;
use NetServa\Ipam\Models\IpReservation;

class IpamService
{
    /**
     * Create a new IP network
     */
    public function createNetwork(array $data): IpNetwork
    {
        // Parse CIDR to extract network address and prefix length
        if (isset($data['cidr'])) {
            [$networkAddress, $prefixLength] = explode('/', $data['cidr']);
            $data['network_address'] = $networkAddress;
            $data['prefix_length'] = (int) $prefixLength;
        }

        // Calculate total addresses
        if (! isset($data['total_addresses']) && isset($data['prefix_length'])) {
            $data['total_addresses'] = $data['prefix_length'] < 31
                ? pow(2, 32 - $data['prefix_length'])
                : 0;
        }

        // Set default values
        $data['ip_version'] = $data['ip_version'] ?? '4';
        $data['is_active'] = $data['is_active'] ?? true;
        $data['used_addresses'] = 0;
        $data['reserved_addresses'] = 0;
        $data['utilization_percentage'] = 0.0;

        return IpNetwork::create($data);
    }

    /**
     * Allocate an IP address from a network
     */
    public function allocateAddress(IpNetwork $network, array $data = []): ?IpAddress
    {
        $ipAddress = $data['address'] ?? $this->getNextAvailableAddress($network);

        if (! $ipAddress) {
            return null;
        }

        $data = array_merge($data, [
            'ip_network_id' => $network->id,
            'ip_address' => $ipAddress,
            'status' => 'allocated',
            'allocated_at' => now(),
        ]);

        $address = IpAddress::create($data);

        // Update network utilization
        $network->updateUtilization();

        return $address;
    }

    /**
     * Get the next available IP address in a network
     */
    public function getNextAvailableAddress(IpNetwork $network): ?string
    {
        if ($network->ip_version !== '4') {
            return null; // IPv6 not implemented yet
        }

        $networkLong = ip2long($network->network_address);
        $totalAddresses = $network->total_addresses;

        // Get all used IPs in this network
        $usedIps = $network->ipAddresses()
            ->where('status', '!=', 'available')
            ->pluck('ip_address')
            ->map(fn ($ip) => ip2long($ip))
            ->toArray();

        // Get reserved IPs
        $reservedIps = $network->ipReservations()
            ->where('is_active', true)
            ->get()
            ->flatMap(fn ($reservation) => $reservation->getIpAddressesInRange())
            ->map(fn ($ip) => ip2long($ip))
            ->toArray();

        $allUsedIps = array_merge($usedIps, $reservedIps);

        // Find first available IP (skip network address and gateway)
        for ($i = 2; $i < $totalAddresses - 1; $i++) {
            $testIp = $networkLong + $i;
            if (! in_array($testIp, $allUsedIps)) {
                return long2ip($testIp);
            }
        }

        return null; // Network is full
    }

    /**
     * Create an IP reservation
     */
    public function createReservation(IpNetwork $network, array $data): IpReservation
    {
        $reservationData = [
            'ip_network_id' => $network->id,
            'is_active' => true,
            'reservation_type' => 'static_range', // Default reservation type
            'name' => $data['name'] ?? 'IP Reservation', // Add required name field
        ];

        // Map common fields
        if (isset($data['description'])) {
            $reservationData['description'] = $data['description'];
        }

        if (isset($data['expires_at'])) {
            $reservationData['valid_until'] = $data['expires_at'];
        }

        if (isset($data['hostname'])) {
            $reservationData['name'] = $data['hostname'];
        }

        // Calculate address count if range is provided
        if (isset($data['start_ip']) && isset($data['end_ip'])) {
            $startLong = ip2long($data['start_ip']);
            $endLong = ip2long($data['end_ip']);
            $reservationData['start_ip'] = $data['start_ip'];
            $reservationData['end_ip'] = $data['end_ip'];
            $reservationData['address_count'] = $endLong - $startLong + 1;
        } elseif (isset($data['address'])) {
            // Single address reservation
            $reservationData['start_ip'] = $data['address'];
            $reservationData['end_ip'] = $data['address'];
            $reservationData['address_count'] = 1;
        }

        return IpReservation::create($reservationData);
    }

    /**
     * Check if an IP address is within a network
     */
    public function isAddressInNetwork(string $ipAddress, IpNetwork $network): bool
    {
        return $network->containsIp($ipAddress);
    }

    /**
     * Calculate network utilization
     */
    public function getNetworkUtilization(IpNetwork $network): array
    {
        $totalAddresses = $network->total_addresses;
        $usableAddresses = $totalAddresses > 2 ? $totalAddresses - 2 : $totalAddresses; // Subtract network and broadcast

        $allocatedAddresses = $network->ipAddresses()
            ->where('status', 'allocated')
            ->count();

        $utilizationPercentage = $usableAddresses > 0
            ? round(($allocatedAddresses / $usableAddresses) * 100, 1)
            : 0.0;

        return [
            'total_addresses' => $totalAddresses,
            'usable_addresses' => $usableAddresses,
            'allocated_addresses' => $allocatedAddresses,
            'available_addresses' => $usableAddresses - $allocatedAddresses,
            'utilization_percentage' => $utilizationPercentage,
        ];
    }

    /**
     * Create subnet from supernet
     */
    public function createSubnet(IpNetwork $supernet, array $data): IpNetwork
    {
        $data['parent_network_id'] = $supernet->id;

        return $this->createNetwork($data);
    }

    /**
     * Update IP address information
     */
    public function updateAddress(IpAddress $ipAddress, array $data): IpAddress
    {
        $ipAddress->update($data);

        return $ipAddress;
    }
}
