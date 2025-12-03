<?php

namespace NetServa\Fleet\Services;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use NetServa\Fleet\Models\FleetVsite;

/**
 * BinaryLane API Service
 *
 * Handles API communication with BinaryLane VPS provider.
 * Follows the same pattern as ProxmoxApiService.
 */
class BinaryLaneService
{
    protected string $baseUrl;

    protected ?string $token = null;

    protected int $timeout;

    protected int $cacheTtl;

    public function __construct(?FleetVsite $vsite = null)
    {
        $this->baseUrl = rtrim(config('fleet.binarylane.api_url', 'https://api.binarylane.com.au/v2'), '/');
        $this->timeout = config('fleet.binarylane.timeout', 30);
        $this->cacheTtl = config('fleet.binarylane.cache_ttl', 3600);

        if ($vsite && $vsite->provider === 'binarylane') {
            $credentials = $vsite->api_credentials;
            $this->token = $credentials['token'] ?? null;
        }
    }

    /**
     * Set API token directly
     */
    public function setToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }

    /**
     * Create from VSite
     */
    public static function fromVsite(FleetVsite $vsite): self
    {
        if ($vsite->provider !== 'binarylane') {
            throw new Exception("VSite '{$vsite->name}' is not a BinaryLane provider");
        }

        return new self($vsite);
    }

    // =========================================================================
    // Account
    // =========================================================================

    /**
     * Get account information
     */
    public function account(): array
    {
        return $this->get('/account')['account'] ?? [];
    }

    /**
     * Get account balance
     */
    public function balance(): array
    {
        return $this->get('/customers/my/balance') ?? [];
    }

    // =========================================================================
    // Servers
    // =========================================================================

    /**
     * List all servers
     */
    public function listServers(): Collection
    {
        $response = $this->get('/servers');

        return collect($response['servers'] ?? [])->map(fn ($server) => $this->normalizeServer($server));
    }

    /**
     * Get a single server
     */
    public function getServer(int $id): array
    {
        $response = $this->get("/servers/{$id}");

        return $this->normalizeServer($response['server'] ?? []);
    }

    /**
     * Create a new server
     *
     * @param  array  $data  Required: name, size, image, region. Optional: ssh_keys, ipv6, vpc_id, user_data, password
     */
    public function createServer(array $data): array
    {
        $payload = [
            'name' => $data['name'],
            'size' => $data['size'],
            'image' => $data['image'],
            'region' => $data['region'],
        ];

        // Optional fields
        if (isset($data['ssh_keys'])) {
            $payload['ssh_keys'] = $data['ssh_keys'];
        }

        if (isset($data['ipv6']) && $data['ipv6']) {
            $payload['ipv6'] = true;
        }

        if (isset($data['vpc_id'])) {
            $payload['vpc_id'] = $data['vpc_id'];
        }

        if (isset($data['user_data'])) {
            $payload['user_data'] = $data['user_data'];
        }

        if (isset($data['password'])) {
            $payload['password'] = $data['password'];
        }

        if (isset($data['backups']) && $data['backups']) {
            $payload['backups'] = true;
        }

        $response = $this->post('/servers', $payload);

        return [
            'server' => $this->normalizeServer($response['server'] ?? []),
            'action' => $response['links']['actions'][0] ?? null,
        ];
    }

    /**
     * Delete a server
     */
    public function deleteServer(int $id): bool
    {
        $this->delete("/servers/{$id}");

        return true;
    }

    /**
     * Perform server action (power on/off, reboot, etc.)
     */
    public function serverAction(int $serverId, string $action, array $params = []): array
    {
        $payload = array_merge(['type' => $action], $params);

        return $this->post("/servers/{$serverId}/actions", $payload);
    }

    /**
     * Power on server
     */
    public function powerOn(int $serverId): array
    {
        return $this->serverAction($serverId, 'power_on');
    }

    /**
     * Power off server (hard)
     */
    public function powerOff(int $serverId): array
    {
        return $this->serverAction($serverId, 'power_off');
    }

    /**
     * Shutdown server (graceful)
     */
    public function shutdown(int $serverId): array
    {
        return $this->serverAction($serverId, 'shutdown');
    }

    /**
     * Reboot server
     */
    public function reboot(int $serverId): array
    {
        return $this->serverAction($serverId, 'reboot');
    }

    /**
     * Power cycle server
     */
    public function powerCycle(int $serverId): array
    {
        return $this->serverAction($serverId, 'power_cycle');
    }

    /**
     * Resize server
     */
    public function resize(int $serverId, string $size): array
    {
        return $this->serverAction($serverId, 'resize', ['size' => $size]);
    }

    /**
     * Rename server
     */
    public function rename(int $serverId, string $name): array
    {
        return $this->serverAction($serverId, 'rename', ['name' => $name]);
    }

    // =========================================================================
    // Reference Data (Cached)
    // =========================================================================

    /**
     * List available sizes
     */
    public function sizes(?string $region = null): Collection
    {
        $cacheKey = 'binarylane:sizes';

        $sizes = Cache::remember($cacheKey, $this->cacheTtl, function () {
            $response = $this->get('/sizes');

            return collect($response['sizes'] ?? [])->map(fn ($size) => $this->normalizeSize($size));
        });

        if ($region) {
            $sizes = $sizes->filter(fn ($size) => in_array($region, $size['regions'] ?? []));
        }

        return $sizes;
    }

    /**
     * List available images
     */
    public function images(string $type = 'distribution'): Collection
    {
        $cacheKey = "binarylane:images:{$type}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($type) {
            $response = $this->get('/images', ['type' => $type]);

            return collect($response['images'] ?? [])->map(fn ($image) => $this->normalizeImage($image));
        });
    }

    /**
     * List available regions
     */
    public function regions(): Collection
    {
        $cacheKey = 'binarylane:regions';

        return Cache::remember($cacheKey, $this->cacheTtl, function () {
            $response = $this->get('/regions');

            return collect($response['regions'] ?? [])->map(fn ($region) => $this->normalizeRegion($region));
        });
    }

    /**
     * List VPCs
     */
    public function vpcs(): Collection
    {
        $cacheKey = 'binarylane:vpcs';

        return Cache::remember($cacheKey, $this->cacheTtl, function () {
            $response = $this->get('/vpcs');

            return collect($response['vpcs'] ?? [])->map(fn ($vpc) => $this->normalizeVpc($vpc));
        });
    }

    /**
     * Get a single VPC
     */
    public function getVpc(int $id): array
    {
        $response = $this->get("/vpcs/{$id}");

        return $this->normalizeVpc($response['vpc'] ?? []);
    }

    // =========================================================================
    // SSH Keys
    // =========================================================================

    /**
     * List SSH keys
     */
    public function sshKeys(): Collection
    {
        $response = $this->get('/account/keys');

        return collect($response['ssh_keys'] ?? [])->map(fn ($key) => [
            'id' => $key['id'],
            'name' => $key['name'],
            'fingerprint' => $key['fingerprint'],
            'public_key' => $key['public_key'] ?? null,
        ]);
    }

    /**
     * Get a single SSH key
     */
    public function getSshKey(int $id): array
    {
        $response = $this->get("/account/keys/{$id}");

        return $response['ssh_key'] ?? [];
    }

    /**
     * Add SSH key
     */
    public function addSshKey(string $name, string $publicKey): array
    {
        $response = $this->post('/account/keys', [
            'name' => $name,
            'public_key' => $publicKey,
        ]);

        return $response['ssh_key'] ?? [];
    }

    /**
     * Delete SSH key
     */
    public function deleteSshKey(int $id): bool
    {
        $this->delete("/account/keys/{$id}");

        return true;
    }

    // =========================================================================
    // Actions
    // =========================================================================

    /**
     * Get action status
     */
    public function getAction(int $actionId): array
    {
        $response = $this->get("/actions/{$actionId}");

        return $response['action'] ?? [];
    }

    /**
     * Wait for action to complete
     */
    public function waitForAction(int $actionId, int $timeout = 300, int $interval = 5): array
    {
        $startTime = time();

        while (time() - $startTime < $timeout) {
            $action = $this->getAction($actionId);

            if (in_array($action['status'] ?? '', ['completed', 'errored'])) {
                return $action;
            }

            sleep($interval);
        }

        throw new Exception("Action {$actionId} timed out after {$timeout} seconds");
    }

    // =========================================================================
    // Normalization Helpers
    // =========================================================================

    protected function normalizeServer(array $data): array
    {
        $ipv4 = null;
        $ipv6 = null;

        foreach ($data['networks']['v4'] ?? [] as $network) {
            if ($network['type'] === 'public') {
                $ipv4 = $network['ip_address'];
                break;
            }
        }

        foreach ($data['networks']['v6'] ?? [] as $network) {
            if ($network['type'] === 'public') {
                $ipv6 = $network['ip_address'];
                break;
            }
        }

        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'status' => $data['status'] ?? null,
            'size_slug' => $data['size']['slug'] ?? $data['size_slug'] ?? null,
            'region_slug' => $data['region']['slug'] ?? $data['region'] ?? null,
            'image_slug' => $data['image']['slug'] ?? $data['image'] ?? null,
            'image_name' => $data['image']['name'] ?? null,
            'ipv4' => $ipv4,
            'ipv6' => $ipv6,
            'vpc_id' => $data['vpc_id'] ?? null,
            'memory_mb' => $data['memory'] ?? null,
            'vcpus' => $data['vcpus'] ?? null,
            'disk_gb' => $data['disk'] ?? null,
            'created_at' => $data['created_at'] ?? null,
            'price_monthly' => $data['size']['price_monthly'] ?? null,
            'raw' => $data,
        ];
    }

    protected function normalizeSize(array $data): array
    {
        return [
            'slug' => $data['slug'] ?? null,
            'description' => $data['description'] ?? null,
            'vcpus' => $data['vcpus'] ?? null,
            'memory_mb' => $data['memory'] ?? null,
            'disk_gb' => $data['disk'] ?? null,
            'transfer_tb' => isset($data['transfer']) ? $data['transfer'] / 1000 : null,
            'price_monthly' => $data['price_monthly'] ?? null,
            'price_hourly' => $data['price_hourly'] ?? null,
            'regions' => $data['regions'] ?? [],
            'available' => $data['available'] ?? true,
        ];
    }

    protected function normalizeImage(array $data): array
    {
        return [
            'id' => $data['id'] ?? null,
            'slug' => $data['slug'] ?? null,
            'name' => $data['name'] ?? null,
            'distribution' => $data['distribution'] ?? null,
            'description' => $data['description'] ?? null,
            'public' => $data['public'] ?? false,
            'regions' => $data['regions'] ?? [],
            'min_disk_size' => $data['min_disk_size'] ?? null,
        ];
    }

    protected function normalizeRegion(array $data): array
    {
        return [
            'slug' => $data['slug'] ?? null,
            'name' => $data['name'] ?? null,
            'available' => $data['available'] ?? true,
            'sizes' => $data['sizes'] ?? [],
            'features' => $data['features'] ?? [],
        ];
    }

    protected function normalizeVpc(array $data): array
    {
        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'ip_range' => $data['ip_range'] ?? null,
            'region' => $data['region']['slug'] ?? $data['region'] ?? null,
            'route_entries' => $data['route_entries'] ?? [],
        ];
    }

    // =========================================================================
    // HTTP Helpers
    // =========================================================================

    protected function client(): PendingRequest
    {
        if (! $this->token) {
            throw new Exception('BinaryLane API token not configured');
        }

        return Http::withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'Content-Type' => 'application/json',
        ])->timeout($this->timeout);
    }

    protected function get(string $endpoint, array $query = []): array
    {
        $url = $this->baseUrl.$endpoint;

        $response = $this->client()->get($url, $query);

        if (! $response->successful()) {
            $this->handleError($response, 'GET', $endpoint);
        }

        return $response->json() ?? [];
    }

    protected function post(string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl.$endpoint;

        $response = $this->client()->post($url, $data);

        if (! $response->successful()) {
            $this->handleError($response, 'POST', $endpoint);
        }

        return $response->json() ?? [];
    }

    protected function delete(string $endpoint): void
    {
        $url = $this->baseUrl.$endpoint;

        $response = $this->client()->delete($url);

        if (! $response->successful() && $response->status() !== 204) {
            $this->handleError($response, 'DELETE', $endpoint);
        }
    }

    protected function handleError($response, string $method, string $endpoint): void
    {
        $status = $response->status();
        $body = $response->json() ?? [];
        $message = $body['message'] ?? $body['error'] ?? $response->body();

        Log::error("BinaryLane API error: {$method} {$endpoint}", [
            'status' => $status,
            'response' => $body,
        ]);

        throw new Exception("BinaryLane API error ({$status}): {$message}");
    }

    /**
     * Test API connectivity
     */
    public function testConnection(): array
    {
        try {
            $account = $this->account();

            return [
                'success' => true,
                'email' => $account['email'] ?? 'unknown',
                'status' => $account['status'] ?? 'unknown',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Clear cached reference data
     */
    public function clearCache(): void
    {
        Cache::forget('binarylane:sizes');
        Cache::forget('binarylane:images:distribution');
        Cache::forget('binarylane:images:backup');
        Cache::forget('binarylane:regions');
        Cache::forget('binarylane:vpcs');
    }
}
