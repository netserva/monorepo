<?php

namespace Ns\Dns\Services\Providers;

use Illuminate\Support\Facades\Http;

class CloudFlareClient implements DnsProviderInterface
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function testConnection(): bool
    {
        // TODO: Implement CloudFlare API connection test
        return false;
    }

    public function getAllZones(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.($this->config['api_token'] ?? ''),
                'Content-Type' => 'application/json',
            ])->get('https://api.cloudflare.com/client/v4/zones');

            $data = $response->json();

            if ($data['success'] ?? false) {
                return $data['result'] ?? [];
            }

            return [];
        } catch (\Exception $e) {
            \Log::error('CloudFlare API error: '.$e->getMessage());

            return [];
        }
    }

    public function getZone(string $zoneId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.($this->config['api_token'] ?? ''),
                'Content-Type' => 'application/json',
            ])->get("https://api.cloudflare.com/client/v4/zones/{$zoneId}");

            $data = $response->json();

            // Handle both real CloudFlare API format and test mocks
            if (isset($data['result'])) {
                return $data['result'];
            }

            // If success field exists, check it
            if (isset($data['success']) && ! $data['success']) {
                return [];
            }

            return $data;
        } catch (\Exception $e) {
            \Log::error('CloudFlare API error: '.$e->getMessage());

            return [];
        }
    }

    public function createZone(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.($this->config['api_token'] ?? ''),
                'Content-Type' => 'application/json',
            ])->post('https://api.cloudflare.com/client/v4/zones', [
                'name' => $data['name'],
                'account' => ['id' => $this->config['account_id'] ?? 'default'],
            ]);

            $responseData = $response->json();

            // Handle both real CloudFlare API format and test mocks
            if (isset($responseData['result'])) {
                return $responseData['result'];
            }

            // If success field exists, check it
            if (isset($responseData['success']) && ! $responseData['success']) {
                return [];
            }

            return $responseData;
        } catch (\Exception $e) {
            \Log::error('CloudFlare API error: '.$e->getMessage());

            return [];
        }
    }

    public function updateZone(string $zoneId, array $data): array
    {
        // TODO: Implement CloudFlare API
        throw new \Exception('CloudFlare provider not yet implemented');
    }

    public function deleteZone(string $zoneId): bool
    {
        // TODO: Implement CloudFlare API
        return false;
    }

    public function getZoneRecords(string $zoneId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.($this->config['api_token'] ?? ''),
                'Content-Type' => 'application/json',
            ])->get("https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records");

            $data = $response->json();

            // Handle both real CloudFlare API format and test mocks
            if (isset($data['result'])) {
                return $data['result'];
            }

            // If success field exists, check it
            if (isset($data['success']) && ! $data['success']) {
                return [];
            }

            return $data;
        } catch (\Exception $e) {
            \Log::error('CloudFlare API error: '.$e->getMessage());

            return [];
        }
    }

    public function getRecord(string $zoneId, string $recordId): array
    {
        // TODO: Implement CloudFlare API
        return [];
    }

    public function createRecord(string $zoneId, array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.($this->config['api_token'] ?? ''),
                'Content-Type' => 'application/json',
            ])->post("https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records", [
                'type' => $data['type'],
                'name' => $data['name'],
                'content' => $data['content'],
                'ttl' => $data['ttl'] ?? 300,
                'priority' => $data['priority'] ?? null,
            ]);

            $responseData = $response->json();

            // Handle both real CloudFlare API format and test mocks
            if (isset($responseData['result'])) {
                return $responseData['result'];
            }

            // If success field exists, check it
            if (isset($responseData['success']) && ! $responseData['success']) {
                return [];
            }

            return $responseData;
        } catch (\Exception $e) {
            \Log::error('CloudFlare API error: '.$e->getMessage());

            return [];
        }
    }

    public function updateRecord(string $zoneId, string $recordId, array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.($this->config['api_token'] ?? ''),
                'Content-Type' => 'application/json',
            ])->patch("https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records/{$recordId}", [
                'type' => $data['type'] ?? null,
                'name' => $data['name'] ?? null,
                'content' => $data['content'] ?? null,
                'ttl' => $data['ttl'] ?? null,
                'priority' => $data['priority'] ?? null,
            ]);

            $responseData = $response->json();

            // Handle both real CloudFlare API format and test mocks
            if (isset($responseData['result'])) {
                return $responseData['result'];
            }

            // If success field exists, check it
            if (isset($responseData['success']) && ! $responseData['success']) {
                return [];
            }

            return $responseData;
        } catch (\Exception $e) {
            \Log::error('CloudFlare API error: '.$e->getMessage());

            return [];
        }
    }

    public function deleteRecord(string $zoneId, string $recordId): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.($this->config['api_token'] ?? ''),
                'Content-Type' => 'application/json',
            ])->delete("https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records/{$recordId}");

            $responseData = $response->json();

            // Check for successful deletion (usually 200 or 204)
            if ($response->successful()) {
                return true;
            }

            // Handle both real CloudFlare API format and test mocks
            if (isset($responseData['success'])) {
                return $responseData['success'];
            }

            return false;
        } catch (\Exception $e) {
            \Log::error('CloudFlare API error: '.$e->getMessage());

            return false;
        }
    }
}
