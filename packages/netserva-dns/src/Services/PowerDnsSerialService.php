<?php

namespace NetServa\Dns\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use NetServa\Dns\Models\DnsProvider;
use NetServa\Dns\Models\DnsZone;

class PowerDnsSerialService
{
    private DnsProvider $provider;

    private string $baseUrl;

    private array $headers;

    public function __construct(DnsProvider $provider)
    {
        $this->provider = $provider;

        $config = $provider->connection_config;
        $this->baseUrl = rtrim($config['api_endpoint'] ?? '', '/').'/servers/localhost';
        $this->headers = [
            'X-API-Key' => $config['api_key'] ?? '',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Increment the serial number for a specific zone
     */
    public function incrementSerial(DnsZone $zone): bool
    {
        try {
            $zoneName = rtrim($zone->name, '.');

            // Get current zone data from PowerDNS
            $currentZone = $this->getZoneFromPowerDns($zoneName);
            if (! $currentZone) {
                Log::error("Failed to fetch zone data for {$zoneName}");

                return false;
            }

            // Find SOA record and increment serial
            $updatedRecords = $this->incrementSoaSerial($currentZone['rrsets'] ?? []);
            if (! $updatedRecords) {
                Log::error("No SOA record found or failed to increment serial for {$zoneName}");

                return false;
            }

            // Update zone via PowerDNS API
            $success = $this->updateZoneRecords($zoneName, $updatedRecords);

            if ($success) {
                // Update local database
                $newSerial = $this->extractSerialFromSoa($updatedRecords);
                if ($newSerial) {
                    $zone->update(['serial' => $newSerial]);
                }

                Log::info("Successfully incremented serial for zone {$zoneName}");

                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error("Error incrementing serial for zone {$zone->name}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Get zone data from PowerDNS API
     */
    private function getZoneFromPowerDns(string $zoneName): ?array
    {
        $url = "{$this->baseUrl}/zones/{$zoneName}.";

        $response = Http::withHeaders($this->headers)
            ->timeout(10)
            ->get($url);

        if ($response->successful()) {
            return $response->json();
        }

        Log::error("Failed to fetch zone {$zoneName} from PowerDNS: ".$response->body());

        return null;
    }

    /**
     * Increment serial in SOA record
     */
    private function incrementSoaSerial(array $rrsets): ?array
    {
        foreach ($rrsets as $rrset) {
            if ($rrset['type'] === 'SOA' && ! empty($rrset['records'])) {
                $soaRrset = $rrset;
                $currentSerial = null;
                $newSerial = null;

                foreach ($soaRrset['records'] as &$record) {
                    $content = $record['content'];
                    $parts = preg_split('/\s+/', trim($content));

                    if (count($parts) >= 7) {
                        // SOA format: nameserver email serial refresh retry expire minimum
                        $currentSerial = (int) $parts[2];

                        // Increment serial - use date format YYYYMMDDNN if current serial matches today
                        $newSerial = $this->generateNewSerial($currentSerial);
                        $parts[2] = $newSerial;

                        $record['content'] = implode(' ', $parts);
                        $record['disabled'] = false;
                    }
                }

                // Add required changetype for PowerDNS PATCH operation
                $soaRrset['changetype'] = 'REPLACE';

                Log::info('Incremented serial for SOA record', [
                    'old_serial' => $currentSerial ?? 'unknown',
                    'new_serial' => $newSerial ?? 'unknown',
                ]);

                // Return only the SOA record to be updated
                return [$soaRrset];
            }
        }

        Log::error('No SOA record found in rrsets');

        return null;
    }

    /**
     * Generate new serial number
     */
    private function generateNewSerial(int $currentSerial): int
    {
        $today = (int) date('Ymd');
        $todayPrefix = $today * 100; // YYYYMMDD00

        // If current serial is from today, increment the suffix
        if ($currentSerial >= $todayPrefix && $currentSerial < $todayPrefix + 100) {
            return $currentSerial + 1;
        }

        // Otherwise, start with today's date + 01
        return $todayPrefix + 1;
    }

    /**
     * Update zone records via PowerDNS API
     */
    private function updateZoneRecords(string $zoneName, array $rrsets): bool
    {
        $url = "{$this->baseUrl}/zones/{$zoneName}.";

        $payload = [
            'rrsets' => $rrsets,
        ];

        Log::info("Updating PowerDNS zone {$zoneName} via PATCH", ['url' => $url]);

        $response = Http::withHeaders($this->headers)
            ->timeout(15)
            ->patch($url, $payload);

        if ($response->successful()) {
            Log::info("Successfully updated zone {$zoneName}");

            return true;
        }

        Log::error("Failed to update zone {$zoneName} in PowerDNS", [
            'status' => $response->status(),
            'body' => $response->body(),
            'url' => $url,
            'payload' => $payload,
        ]);

        return false;
    }

    /**
     * Extract serial number from SOA record
     */
    private function extractSerialFromSoa(array $rrsets): ?int
    {
        foreach ($rrsets as $rrset) {
            if ($rrset['type'] === 'SOA' && ! empty($rrset['records'])) {
                foreach ($rrset['records'] as $record) {
                    $content = $record['content'];
                    $parts = preg_split('/\s+/', trim($content));

                    if (count($parts) >= 7) {
                        return (int) $parts[2];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Test connection to PowerDNS API
     */
    public function testConnection(): bool
    {
        try {
            Log::info("Testing PowerDNS connection to: {$this->baseUrl}");

            $response = Http::withHeaders($this->headers)
                ->timeout(15)
                ->get($this->baseUrl);

            if ($response->successful()) {
                Log::info("PowerDNS connection test successful for: {$this->provider->name}");

                return true;
            } else {
                Log::error('PowerDNS connection test failed', [
                    'provider' => $this->provider->name,
                    'url' => $this->baseUrl,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }
        } catch (\Exception $e) {
            Log::error('PowerDNS API connection test exception', [
                'provider' => $this->provider->name,
                'url' => $this->baseUrl,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get current serial for a zone from PowerDNS
     */
    public function getCurrentSerial(string $zoneName): ?int
    {
        $zoneData = $this->getZoneFromPowerDns(rtrim($zoneName, '.'));

        if (! $zoneData || empty($zoneData['rrsets'])) {
            return null;
        }

        return $this->extractSerialFromSoa($zoneData['rrsets']);
    }
}
