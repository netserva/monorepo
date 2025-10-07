<?php

namespace Ns\Dns\Services\Providers;

use Illuminate\Support\Facades\Http;

class PowerDnsClient implements DnsProviderInterface
{
    private array $config;

    private string $baseUrl;

    private string $apiKey;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->baseUrl = rtrim($config['api_endpoint'] ?? 'http://localhost:8081', '/');
        $this->apiKey = $config['api_key'] ?? '';
    }

    public function testConnection(): bool
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout($this->config['timeout'] ?? 30)
                ->get($this->baseUrl.'/servers');

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('PowerDNS connection test failed: '.$e->getMessage());

            return false;
        }
    }

    public function getAllZones(): array
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout($this->config['timeout'] ?? 30)
                ->get($this->baseUrl.'/servers/localhost/zones');

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            throw new \Exception('Failed to fetch zones: '.$response->body());
        } catch (\Exception $e) {
            \Log::error('PowerDNS getAllZones failed: '.$e->getMessage());

            return [];
        }
    }

    public function getZone(string $zoneId): array
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout($this->config['timeout'] ?? 30)
                ->get($this->baseUrl."/servers/localhost/zones/{$zoneId}");

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            throw new \Exception('Failed to fetch zone: '.$response->body());
        } catch (\Exception $e) {
            \Log::error('PowerDNS getZone failed: '.$e->getMessage());

            return [];
        }
    }

    public function createZone(array $data): array
    {
        try {
            $zoneData = [
                'name' => $data['name'],
                'kind' => $data['kind'] ?? 'Master',
                'nameservers' => $data['nameservers'] ?? [],
            ];

            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout($this->config['timeout'] ?? 30)
                ->post($this->baseUrl.'/servers/localhost/zones', $zoneData);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            throw new \Exception('Failed to create zone: '.$response->body());
        } catch (\Exception $e) {
            \Log::error('PowerDNS createZone failed: '.$e->getMessage());
            throw $e;
        }
    }

    public function updateZone(string $zoneId, array $data): array
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout($this->config['timeout'] ?? 30)
                ->put($this->baseUrl."/servers/localhost/zones/{$zoneId}", $data);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            throw new \Exception('Failed to update zone: '.$response->body());
        } catch (\Exception $e) {
            \Log::error('PowerDNS updateZone failed: '.$e->getMessage());
            throw $e;
        }
    }

    public function deleteZone(string $zoneId): bool
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout($this->config['timeout'] ?? 30)
                ->delete($this->baseUrl."/servers/localhost/zones/{$zoneId}");

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('PowerDNS deleteZone failed: '.$e->getMessage());

            return false;
        }
    }

    public function getZoneRecords(string $zoneId): array
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout($this->config['timeout'] ?? 30)
                ->get($this->baseUrl."/servers/localhost/zones/{$zoneId}");

            if ($response->successful()) {
                $zone = $response->json();
                $rrsets = $zone['rrsets'] ?? [];

                // Convert PowerDNS rrsets to individual records
                $records = [];
                foreach ($rrsets as $rrset) {
                    $recordsArray = $rrset['records'] ?? [];
                    foreach ($recordsArray as $index => $record) {
                        $records[] = [
                            'id' => $rrset['name'].'_'.$rrset['type'].'_'.$index,
                            'name' => $rrset['name'],
                            'type' => $rrset['type'],
                            'content' => $record['content'],
                            'ttl' => $rrset['ttl'] ?? 3600,
                            'priority' => $this->extractPriority($record['content'], $rrset['type']),
                            'disabled' => $record['disabled'] ?? false,
                            'auth' => true,
                            'comment' => implode(', ', array_column($rrset['comments'] ?? [], 'content')),
                            'provider_data' => $rrset,
                        ];
                    }
                }

                return $records;
            }

            throw new \Exception('Failed to fetch zone records: '.$response->body());
        } catch (\Exception $e) {
            \Log::error('PowerDNS getZoneRecords failed: '.$e->getMessage());

            return [];
        }
    }

    public function createRecord(string $zoneId, array $data): array
    {
        try {
            $rrset = [
                'name' => $data['name'],
                'type' => $data['type'],
                'records' => [
                    ['content' => $data['content'], 'disabled' => $data['disabled'] ?? false],
                ],
                'ttl' => $data['ttl'] ?? 3600,
            ];

            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout($this->config['timeout'] ?? 30)
                ->patch($this->baseUrl."/servers/localhost/zones/{$zoneId}", [
                    'rrsets' => [$rrset],
                ]);

            if ($response->successful()) {
                return $rrset;
            }

            throw new \Exception('Failed to create record: '.$response->body());
        } catch (\Exception $e) {
            \Log::error('PowerDNS createRecord failed: '.$e->getMessage());
            throw $e;
        }
    }

    public function updateRecord(string $zoneId, string $recordId, array $data): array
    {
        // PowerDNS uses RRSET updates, similar to create
        return $this->createRecord($zoneId, $data);
    }

    public function deleteRecord(string $zoneId, string $recordId): bool
    {
        try {
            // PowerDNS requires the record data to delete it
            // This is a simplified version - real implementation would need record details
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout($this->config['timeout'] ?? 30)
                ->patch($this->baseUrl."/servers/localhost/zones/{$zoneId}", [
                    'rrsets' => [
                        [
                            'name' => $recordId,
                            'changetype' => 'DELETE',
                        ],
                    ],
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('PowerDNS deleteRecord failed: '.$e->getMessage());

            return false;
        }
    }

    public function getRecord(string $zoneId, string $recordId): array
    {
        // PowerDNS doesn't have individual record endpoints
        // Would need to fetch zone and filter records
        $records = $this->getZoneRecords($zoneId);

        foreach ($records as $record) {
            if ($record['name'] === $recordId) {
                return $record;
            }
        }

        return [];
    }

    private function extractPriority(string $content, string $type): int
    {
        // Extract priority from MX and SRV record content
        if (in_array($type, ['MX', 'SRV'])) {
            $parts = explode(' ', trim($content), 2);
            if (count($parts) > 1 && is_numeric($parts[0])) {
                return (int) $parts[0];
            }
        }

        return 0;
    }
}
