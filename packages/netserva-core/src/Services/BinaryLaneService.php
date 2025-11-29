<?php

namespace NetServa\Core\Services;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BinaryLane VPS Management Service
 *
 * Comprehensive BinaryLane API v2 wrapper with rate limiting,
 * error handling, caching, and retry logic.
 */
class BinaryLaneService
{
    protected ?PendingRequest $client;

    protected string $baseUrl = 'https://api.binarylane.com.au/v2';

    protected array $config;

    public function __construct()
    {
        $this->config = [
            'api_token' => env('BINARYLANE_API_TOKEN'),
            'rate_limit_max_attempts' => 3,
            'rate_limit_decay_minutes' => 5,
            'cache_ttl' => 300, // 5 minutes
            'timeout' => 30,
        ];

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
                'User-Agent' => 'NetServa-Platform/3.0 (BinaryLane-API-Client)',
            ])
            ->timeout($this->config['timeout'])
            ->retry(3, 100, function (Exception $exception) {
                return $exception instanceof \Illuminate\Http\Client\ConnectionException;
            });
    }

    /**
     * List all server instances
     */
    public function listServers(): array
    {
        $cacheKey = 'binarylane:servers';

        return Cache::remember($cacheKey, $this->config['cache_ttl'], function () {
            return $this->makeRequestWithBackoff('GET', '/servers');
        });
    }

    /**
     * Get server details by ID
     */
    public function getServer(string $serverId): array
    {
        $cacheKey = "binarylane:server:{$serverId}";

        return Cache::remember($cacheKey, $this->config['cache_ttl'], function () use ($serverId) {
            return $this->makeRequestWithBackoff('GET', "/servers/{$serverId}");
        });
    }

    /**
     * List available server sizes/plans
     */
    public function listSizes(): array
    {
        $cacheKey = 'binarylane:sizes';

        return Cache::remember($cacheKey, $this->config['cache_ttl'], function () {
            return $this->makeRequestWithBackoff('GET', '/sizes');
        });
    }

    /**
     * List available OS images
     */
    public function listImages(): array
    {
        $cacheKey = 'binarylane:images';

        return Cache::remember($cacheKey, $this->config['cache_ttl'], function () {
            return $this->makeRequestWithBackoff('GET', '/images');
        });
    }

    /**
     * List available regions/datacenters
     */
    public function listRegions(): array
    {
        $cacheKey = 'binarylane:regions';

        return Cache::remember($cacheKey, $this->config['cache_ttl'], function () {
            return $this->makeRequestWithBackoff('GET', '/regions');
        });
    }

    /**
     * Create a new VPS instance
     */
    public function createServer(array $data): array
    {
        $this->validateServerData($data);

        $result = $this->makeRequestWithBackoff('POST', '/servers', $data);

        // Clear cache
        $this->clearServerCache();

        return $result;
    }

    /**
     * Delete a VPS instance
     */
    public function deleteServer(string $serverId): array
    {
        $result = $this->makeRequestWithBackoff('DELETE', "/servers/{$serverId}");

        // Clear cache
        $this->clearServerCache();

        return $result;
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
            } catch (BinaryLaneRateLimitException $e) {
                $attempt++;

                if ($attempt >= $maxAttempts) {
                    throw $e;
                }

                // Exponential backoff: 1s, 2s, 4s...
                $waitTime = pow(2, $attempt - 1);
                Log::info("BinaryLane rate limited. Waiting {$waitTime} seconds before retry #{$attempt}", [
                    'endpoint' => $endpoint,
                    'attempt' => $attempt,
                ]);

                sleep($waitTime);
            }
        }

        throw new BinaryLaneApiException('Maximum retry attempts exceeded');
    }

    /**
     * Make HTTP request to BinaryLane API
     */
    protected function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        if ($this->client === null) {
            throw new BinaryLaneConfigurationException('BinaryLane API token not configured');
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
            Log::error('BinaryLane API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new BinaryLaneApiException(
                "BinaryLane API request failed: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Handle BinaryLane API response
     */
    protected function handleResponse(Response $response, string $endpoint): array
    {
        $data = $response->json();
        $statusCode = $response->status();

        // Log rate limit headers for monitoring
        Log::debug('BinaryLane API response', [
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'rate_limit_remaining' => $response->header('x-ratelimit-remaining'),
            'rate_limit_reset' => $response->header('x-ratelimit-reset'),
        ]);

        // Handle rate limiting
        if ($statusCode === 429) {
            $retryAfter = (int) $response->header('retry-after', 60);
            throw new BinaryLaneRateLimitException(
                "Rate limit exceeded. Retry after {$retryAfter} seconds.",
                429
            );
        }

        // Handle client/server errors
        if ($response->failed()) {
            $errorMessage = $data['message'] ?? $data['error'] ?? 'Unknown error';

            if (isset($data['errors']) && is_array($data['errors'])) {
                $errorMessage = collect($data['errors'])->implode(', ');
            }

            throw new BinaryLaneApiException(
                "BinaryLane API error ({$statusCode}): {$errorMessage}",
                $statusCode
            );
        }

        // For DELETE requests, return success indicator if no content
        if ($statusCode === 204 || (empty($data) && $statusCode === 200)) {
            return ['success' => true, 'message' => 'Operation completed successfully'];
        }

        return $data;
    }

    /**
     * Validate server creation data
     */
    protected function validateServerData(array $data): void
    {
        $required = ['name', 'size', 'image', 'region'];

        foreach ($required as $field) {
            if (! isset($data[$field]) || empty($data[$field])) {
                throw new BinaryLaneValidationException("Missing required field: {$field}");
            }
        }

        // Validate server name format
        if (! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9.-]{0,62}[a-zA-Z0-9]$/', $data['name'])) {
            throw new BinaryLaneValidationException(
                'Invalid server name format. Must be 1-64 characters, alphanumeric with dots/hyphens, cannot start or end with hyphen or dot'
            );
        }
    }

    /**
     * Clear server-related cache
     */
    protected function clearServerCache(): void
    {
        $patterns = [
            'binarylane:servers',
            'binarylane:server:*',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                // For wildcard patterns, we'd need more sophisticated cache clearing
                // For now, just clear the main servers list
                Cache::forget('binarylane:servers');
            } else {
                Cache::forget($pattern);
            }
        }
    }

    /**
     * Test API connectivity and authentication
     */
    public function testConnection(): array
    {
        try {
            // Test with a simple GET request
            $response = $this->makeRequest('GET', '/account');

            return [
                'success' => true,
                'message' => 'BinaryLane API connection successful',
                'account' => $response['account'] ?? null,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "BinaryLane API connection failed: {$e->getMessage()}",
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Format server data for display
     */
    public function formatServerForDisplay(array $server): array
    {
        $ip = 'N/A';
        if (isset($server['networks']['v4']) && ! empty($server['networks']['v4'])) {
            $ip = $server['networks']['v4'][0]['ip_address'] ?? 'N/A';
        }

        $size = $server['size']['slug'] ?? 'N/A';
        $region = $server['region']['slug'] ?? 'N/A';

        return [
            'id' => $server['id'] ?? 'N/A',
            'name' => $server['name'] ?? 'N/A',
            'ip' => $ip,
            'status' => $server['status'] ?? 'N/A',
            'size' => $size,
            'region' => $region,
        ];
    }

    /**
     * Format size data for display
     */
    public function formatSizeForDisplay(array $size): array
    {
        $memory = $size['memory'] ?? 0;
        $memoryDisplay = $memory >= 1024 ? ($memory / 1024).'GB' : $memory.'MB';

        $disk = $size['disk'] ?? 0;
        $diskDisplay = $disk.'GB';

        $hourly = isset($size['price_hourly']) ? '$'.number_format($size['price_hourly'], 3) : 'N/A';
        $monthly = isset($size['price_monthly']) ? '$'.$size['price_monthly'] : 'N/A';

        return [
            'slug' => $size['slug'] ?? 'N/A',
            'vcpus' => $size['vcpus'] ?? 'N/A',
            'memory' => $memoryDisplay,
            'disk' => $diskDisplay,
            'hourly' => $hourly,
            'monthly' => $monthly,
        ];
    }

    /**
     * Format image data for display
     */
    public function formatImageForDisplay(array $image): array
    {
        $name = $image['name'] ?? 'N/A';
        if (strlen($name) > 20) {
            $name = substr($name, 0, 17).'...';
        }

        return [
            'slug' => $image['slug'] ?? 'N/A',
            'name' => $name,
            'type' => $image['type'] ?? 'N/A',
            'status' => $image['status'] ?? 'N/A',
        ];
    }

    /**
     * Format region data for display
     */
    public function formatRegionForDisplay(array $region): array
    {
        $location = $region['name'] ?? $region['slug'] ?? 'N/A';
        if (strlen($location) > 20) {
            $location = substr($location, 0, 17).'...';
        }

        return [
            'slug' => $region['slug'] ?? 'N/A',
            'name' => $region['name'] ?? 'N/A',
            'location' => $location,
            'available' => $region['available'] ? 'Yes' : 'No',
        ];
    }
}

/**
 * BinaryLane Configuration Exception
 */
class BinaryLaneConfigurationException extends Exception
{
    //
}

/**
 * BinaryLane API Exception
 */
class BinaryLaneApiException extends Exception
{
    //
}

/**
 * BinaryLane Rate Limit Exception
 */
class BinaryLaneRateLimitException extends BinaryLaneApiException
{
    //
}

/**
 * BinaryLane Validation Exception
 */
class BinaryLaneValidationException extends BinaryLaneApiException
{
    //
}
