<?php

namespace NetServa\Dns\Services;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CloudFlare API Service
 *
 * Comprehensive CloudFlare API v4 wrapper with rate limiting,
 * error handling, caching, and retry logic.
 */
class CloudFlareService
{
    protected ?PendingRequest $client;

    protected string $baseUrl = 'https://api.cloudflare.com/client/v4/';

    protected array $config;

    public function __construct()
    {
        $this->config = config('services.cloudflare', [
            'api_token' => env('CLOUDFLARE_API_TOKEN'),
            'rate_limit_max_attempts' => 3,
            'rate_limit_decay_minutes' => 5,
            'cache_ttl' => 300, // 5 minutes
            'timeout' => 30,
        ]);

        $this->initializeClient();
    }

    /**
     * Initialize HTTP client with authentication and defaults
     */
    protected function initializeClient(): void
    {
        // Allow lazy initialization - only throw when actually using the service
        if (empty($this->config['api_token'])) {
            $this->client = null;

            return;
        }

        $this->client = Http::baseUrl($this->baseUrl)
            ->withToken($this->config['api_token'])
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'NetServa-Platform/3.0 (CloudFlare-API-Client)',
            ])
            ->timeout($this->config['timeout'])
            ->retry(3, 100, function (Exception $exception) {
                return $exception instanceof \Illuminate\Http\Client\ConnectionException;
            });
    }

    /**
     * List all zones accessible by the API token
     */
    public function listZones(array $params = []): array
    {
        $cacheKey = 'cloudflare:zones:'.md5(serialize($params));

        return Cache::remember($cacheKey, $this->config['cache_ttl'], function () use ($params) {
            return $this->makeRequestWithBackoff('GET', 'zones', $params);
        });
    }

    /**
     * Get zone details by ID
     */
    public function getZone(string $zoneId): array
    {
        $cacheKey = "cloudflare:zone:{$zoneId}";

        return Cache::remember($cacheKey, $this->config['cache_ttl'], function () use ($zoneId) {
            return $this->makeRequestWithBackoff('GET', "zones/{$zoneId}");
        });
    }

    /**
     * Get zone by domain name
     */
    public function getZoneByName(string $domain): ?array
    {
        $zones = $this->listZones(['name' => $domain]);

        return collect($zones)->firstWhere('name', $domain);
    }

    /**
     * List DNS records for a zone
     */
    public function listDnsRecords(string $zoneId, array $params = []): array
    {
        $cacheKey = "cloudflare:dns_records:{$zoneId}:".md5(serialize($params));

        return Cache::remember($cacheKey, $this->config['cache_ttl'], function () use ($zoneId, $params) {
            return $this->makeRequestWithBackoff('GET', "zones/{$zoneId}/dns_records", $params);
        });
    }

    /**
     * Get specific DNS record
     */
    public function getDnsRecord(string $zoneId, string $recordId): array
    {
        return $this->makeRequestWithBackoff('GET', "zones/{$zoneId}/dns_records/{$recordId}");
    }

    /**
     * Create DNS record
     */
    public function createDnsRecord(string $zoneId, array $data): array
    {
        $this->validateDnsRecordData($data);

        $result = $this->makeRequestWithBackoff('POST', "zones/{$zoneId}/dns_records", $data);

        // Clear cache for this zone
        $this->clearZoneCache($zoneId);

        return $result;
    }

    /**
     * Update DNS record
     */
    public function updateDnsRecord(string $zoneId, string $recordId, array $data): array
    {
        $this->validateDnsRecordData($data);

        $result = $this->makeRequestWithBackoff('PUT', "zones/{$zoneId}/dns_records/{$recordId}", $data);

        // Clear cache for this zone
        $this->clearZoneCache($zoneId);

        return $result;
    }

    /**
     * Delete DNS record
     */
    public function deleteDnsRecord(string $zoneId, string $recordId): array
    {
        $result = $this->makeRequestWithBackoff('DELETE', "zones/{$zoneId}/dns_records/{$recordId}");

        // Clear cache for this zone
        $this->clearZoneCache($zoneId);

        return $result;
    }

    /**
     * Get zone analytics
     */
    public function getZoneAnalytics(string $zoneId, array $params = []): array
    {
        return $this->makeRequestWithBackoff('GET', "zones/{$zoneId}/analytics/dashboard", $params);
    }

    /**
     * Purge cache for zone or specific files
     */
    public function purgeCache(string $zoneId, array $files = []): array
    {
        $data = empty($files) ? ['purge_everything' => true] : ['files' => $files];

        return $this->makeRequestWithBackoff('POST', "zones/{$zoneId}/purge_cache", $data);
    }

    /**
     * Get zone settings
     */
    public function getZoneSettings(string $zoneId): array
    {
        return $this->makeRequestWithBackoff('GET', "zones/{$zoneId}/settings");
    }

    /**
     * Update zone setting
     */
    public function updateZoneSetting(string $zoneId, string $setting, mixed $value): array
    {
        return $this->makeRequestWithBackoff('PATCH', "zones/{$zoneId}/settings/{$setting}", [
            'value' => $value,
        ]);
    }

    /**
     * Make HTTP request with exponential backoff for rate limiting
     */
    protected function makeRequestWithBackoff(string $method, string $endpoint, array $data = []): array
    {
        $maxAttempts = $this->config['rate_limit_max_attempts'];
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            try {
                return $this->makeRequest($method, $endpoint, $data);
            } catch (CloudFlareRateLimitException $e) {
                $attempt++;

                if ($attempt >= $maxAttempts) {
                    throw $e;
                }

                // Exponential backoff: 1s, 2s, 4s...
                $waitTime = pow(2, $attempt - 1);
                Log::info("CloudFlare rate limited. Waiting {$waitTime} seconds before retry #{$attempt}", [
                    'endpoint' => $endpoint,
                    'attempt' => $attempt,
                ]);

                sleep($waitTime);
            }
        }

        throw new CloudFlareApiException('Maximum retry attempts exceeded');
    }

    /**
     * Make HTTP request to CloudFlare API
     */
    protected function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        if ($this->client === null) {
            throw new CloudFlareConfigurationException('CloudFlare API token not configured');
        }

        try {
            $response = match (strtoupper($method)) {
                'GET' => $this->client->get($endpoint, $data),
                'POST' => $this->client->post($endpoint, $data),
                'PUT' => $this->client->put($endpoint, $data),
                'PATCH' => $this->client->patch($endpoint, $data),
                'DELETE' => $this->client->delete($endpoint, $data),
                default => throw new Exception("Unsupported HTTP method: {$method}")
            };

            return $this->handleResponse($response, $endpoint);
        } catch (Exception $e) {
            Log::error('CloudFlare API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new CloudFlareApiException(
                "CloudFlare API request failed: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Handle CloudFlare API response
     */
    protected function handleResponse(Response $response, string $endpoint): array
    {
        $data = $response->json();
        $statusCode = $response->status();

        // Log rate limit headers for monitoring
        if ($response->hasHeader('cf-ray')) {
            Log::debug('CloudFlare API response', [
                'endpoint' => $endpoint,
                'cf_ray' => $response->header('cf-ray'),
                'rate_limit_remaining' => $response->header('x-ratelimit-remaining'),
                'rate_limit_reset' => $response->header('x-ratelimit-reset'),
            ]);
        }

        // Handle rate limiting
        if ($statusCode === 429) {
            $retryAfter = (int) $response->header('retry-after', 60);
            throw new CloudFlareRateLimitException(
                "Rate limit exceeded. Retry after {$retryAfter} seconds.",
                429
            );
        }

        // Handle client/server errors
        if ($response->failed()) {
            $errorMessage = $data['errors'][0]['message'] ?? 'Unknown error';
            $errorCode = $data['errors'][0]['code'] ?? $statusCode;

            throw new CloudFlareApiException(
                "CloudFlare API error ({$errorCode}): {$errorMessage}",
                $statusCode
            );
        }

        // Handle CloudFlare API success/error responses
        if (isset($data['success']) && ! $data['success']) {
            $errors = collect($data['errors'] ?? [])->pluck('message')->implode(', ');
            throw new CloudFlareApiException("CloudFlare API errors: {$errors}");
        }

        return $data['result'] ?? $data;
    }

    /**
     * Validate DNS record data
     */
    protected function validateDnsRecordData(array $data): void
    {
        $required = ['type', 'name', 'content'];
        $validTypes = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'SRV', 'PTR', 'NS', 'CAA'];

        foreach ($required as $field) {
            if (! isset($data[$field]) || empty($data[$field])) {
                throw new CloudFlareValidationException("Missing required field: {$field}");
            }
        }

        if (! in_array(strtoupper($data['type']), $validTypes)) {
            throw new CloudFlareValidationException("Invalid DNS record type: {$data['type']}");
        }

        // Validate TTL if provided
        if (isset($data['ttl']) && ($data['ttl'] < 1 || $data['ttl'] > 86400)) {
            throw new CloudFlareValidationException('TTL must be between 1 and 86400 seconds');
        }
    }

    /**
     * Clear cache for specific zone
     */
    protected function clearZoneCache(string $zoneId): void
    {
        $patterns = [
            "cloudflare:zone:{$zoneId}",
            "cloudflare:dns_records:{$zoneId}:*",
            'cloudflare:zones:*',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                // For wildcard patterns, we'd need a more sophisticated cache clearing
                // For now, we'll clear specific known keys
                Cache::forget("cloudflare:zone:{$zoneId}");
            } else {
                Cache::forget($pattern);
            }
        }
    }

    /**
     * Get API usage statistics
     */
    public function getApiUsage(): array
    {
        return [
            'rate_limit_max' => 1200, // CloudFlare API limit per 5 minutes
            'rate_limit_window' => 300, // 5 minutes in seconds
            'cached_requests_saved' => Cache::get('cloudflare:api_cache_hits', 0),
            'total_requests' => Cache::get('cloudflare:api_total_requests', 0),
        ];
    }

    /**
     * Test API connectivity and authentication
     */
    public function testConnection(): array
    {
        try {
            $response = $this->makeRequest('GET', 'user/tokens/verify');

            return [
                'success' => true,
                'message' => 'CloudFlare API connection successful',
                'token_status' => $response['status'] ?? 'active',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "CloudFlare API connection failed: {$e->getMessage()}",
                'error' => $e->getMessage(),
            ];
        }
    }
}

/**
 * CloudFlare Configuration Exception
 */
class CloudFlareConfigurationException extends Exception
{
    //
}

/**
 * CloudFlare API Exception
 */
class CloudFlareApiException extends Exception
{
    //
}

/**
 * CloudFlare Rate Limit Exception
 */
class CloudFlareRateLimitException extends CloudFlareApiException
{
    //
}

/**
 * CloudFlare Validation Exception
 */
class CloudFlareValidationException extends CloudFlareApiException
{
    //
}
