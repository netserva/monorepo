<?php

namespace NetServa\Fleet\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use NetServa\Fleet\Models\FleetVHost;
use NetServa\Fleet\Models\FleetVNode;
use NetServa\Fleet\Models\FleetVSite;

/**
 * Proxmox API Service
 *
 * Handles API communication with Proxmox VE clusters
 */
class ProxmoxApiService
{
    protected FleetVSite $vsite;

    protected array $credentials;

    protected string $baseUrl;

    protected int $timeout;

    public function __construct(FleetVSite $vsite)
    {
        $this->vsite = $vsite;
        $this->credentials = json_decode(decrypt($vsite->api_credentials), true);
        $this->baseUrl = rtrim($vsite->api_endpoint, '/');
        $this->timeout = config('fleet.discovery.api_timeout', 30);
    }

    /**
     * Test API connectivity
     */
    public function testConnection(): array
    {
        try {
            $response = $this->makeRequest('/version');

            return [
                'success' => true,
                'version' => $response['data']['version'] ?? 'unknown',
                'release' => $response['data']['release'] ?? 'unknown',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Discover all nodes in the cluster
     */
    public function discoverNodes(): array
    {
        $response = $this->makeRequest('/nodes');
        $nodes = [];

        foreach ($response['data'] as $nodeData) {
            if ($nodeData['type'] !== 'node') {
                continue;
            }

            $nodes[] = [
                'name' => $nodeData['node'],
                'status' => $nodeData['status'],
                'uptime' => $nodeData['uptime'] ?? null,
                'cpu_cores' => $nodeData['maxcpu'] ?? null,
                'memory_mb' => isset($nodeData['maxmem']) ? round($nodeData['maxmem'] / 1024 / 1024) : null,
                'memory_used_mb' => isset($nodeData['mem']) ? round($nodeData['mem'] / 1024 / 1024) : null,
                'disk_gb' => isset($nodeData['maxdisk']) ? round($nodeData['maxdisk'] / 1024 / 1024 / 1024) : null,
                'disk_used_gb' => isset($nodeData['disk']) ? round($nodeData['disk'] / 1024 / 1024 / 1024) : null,
                'cpu_usage' => $nodeData['cpu'] ?? null,
            ];
        }

        return $nodes;
    }

    /**
     * Discover all VMs and containers in the cluster
     */
    public function discoverInstances(): array
    {
        $response = $this->makeRequest('/cluster/resources');
        $instances = [];

        foreach ($response['data'] as $resource) {
            if (! in_array($resource['type'], ['lxc', 'qemu'])) {
                continue;
            }

            $instances[] = [
                'vmid' => $resource['vmid'],
                'name' => $resource['name'],
                'node' => $resource['node'],
                'type' => $resource['type'], // 'lxc' or 'qemu'
                'status' => $resource['status'],
                'template' => $resource['template'] ?? 0,
                'uptime' => $resource['uptime'] ?? null,
                'cpu_cores' => $resource['maxcpu'] ?? null,
                'memory_mb' => isset($resource['maxmem']) ? round($resource['maxmem'] / 1024 / 1024) : null,
                'memory_used_mb' => isset($resource['mem']) ? round($resource['mem'] / 1024 / 1024) : null,
                'disk_gb' => isset($resource['maxdisk']) ? round($resource['maxdisk'] / 1024 / 1024 / 1024) : null,
                'disk_used_gb' => isset($resource['disk']) ? round($resource['disk'] / 1024 / 1024 / 1024) : null,
                'cpu_usage' => $resource['cpu'] ?? null,
                'tags' => $resource['tags'] ?? null,
                'network_in' => $resource['netin'] ?? null,
                'network_out' => $resource['netout'] ?? null,
            ];
        }

        return $instances;
    }

    /**
     * Get detailed information about a specific VM/container
     */
    public function getInstanceDetails(string $node, string $type, int $vmid): array
    {
        $endpoint = "nodes/{$node}/{$type}/{$vmid}/config";
        $response = $this->makeRequest($endpoint);

        return $response['data'] ?? [];
    }

    /**
     * Get network configuration for an instance
     */
    public function getInstanceNetwork(string $node, string $type, int $vmid): array
    {
        try {
            $config = $this->getInstanceDetails($node, $type, $vmid);
            $networks = [];

            // Parse network interfaces
            foreach ($config as $key => $value) {
                if (preg_match('/^net\d+$/', $key)) {
                    $networks[] = $this->parseNetworkInterface($value);
                }
            }

            return $networks;
        } catch (Exception $e) {
            Log::warning("Failed to get network config for {$type}/{$vmid}", ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Parse IP addresses from tags (your specific format)
     */
    public function parseIpFromTags(?string $tags): array
    {
        if (! $tags) {
            return [];
        }

        $ips = [];
        $parts = explode(';', $tags);

        foreach ($parts as $part) {
            $part = trim($part);
            // Check if it looks like an IP address
            if (filter_var($part, FILTER_VALIDATE_IP)) {
                $ips[] = $part;
            }
        }

        return $ips;
    }

    /**
     * Create or update VNodes from discovered data
     */
    public function syncNodes(): array
    {
        $discoveredNodes = $this->discoverNodes();
        $results = ['created' => 0, 'updated' => 0, 'errors' => []];

        foreach ($discoveredNodes as $nodeData) {
            try {
                $vnode = FleetVNode::where('vsite_id', $this->vsite->id)
                    ->where('name', $nodeData['name'])
                    ->first();

                if ($vnode) {
                    $vnode->update([
                        'status' => $nodeData['status'] === 'online' ? 'active' : 'inactive',
                        'cpu_cores' => $nodeData['cpu_cores'],
                        'memory_mb' => $nodeData['memory_mb'],
                        'disk_gb' => $nodeData['disk_gb'],
                        'last_discovered_at' => now(),
                        'last_error' => null,
                    ]);
                    $results['updated']++;
                } else {
                    FleetVNode::create([
                        'name' => $nodeData['name'],
                        'slug' => str($nodeData['name'])->slug(),
                        'vsite_id' => $this->vsite->id,
                        'role' => 'compute',
                        'environment' => 'production',
                        'status' => $nodeData['status'] === 'online' ? 'active' : 'inactive',
                        'cpu_cores' => $nodeData['cpu_cores'],
                        'memory_mb' => $nodeData['memory_mb'],
                        'disk_gb' => $nodeData['disk_gb'],
                        'discovery_method' => 'api',
                        'last_discovered_at' => now(),
                        'is_active' => true,
                    ]);
                    $results['created']++;
                }
            } catch (Exception $e) {
                $results['errors'][] = "Node {$nodeData['name']}: {$e->getMessage()}";
            }
        }

        return $results;
    }

    /**
     * Create or update VHosts from discovered instances
     */
    public function syncInstances(): array
    {
        $discoveredInstances = $this->discoverInstances();
        $results = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($discoveredInstances as $instanceData) {
            try {
                // Skip templates
                if ($instanceData['template']) {
                    $results['skipped']++;

                    continue;
                }

                // Find the VNode
                $vnode = FleetVNode::where('vsite_id', $this->vsite->id)
                    ->where('name', $instanceData['node'])
                    ->first();

                if (! $vnode) {
                    $results['errors'][] = "Node {$instanceData['node']} not found for instance {$instanceData['name']}";

                    continue;
                }

                // Generate domain from name (you can customize this logic)
                $domain = $this->generateDomainFromName($instanceData['name']);

                $vhost = FleetVHost::where('vnode_id', $vnode->id)
                    ->where('instance_id', $instanceData['vmid'])
                    ->first();

                $ips = $this->parseIpFromTags($instanceData['tags']);

                if ($vhost) {
                    $vhost->update([
                        'domain' => $domain,
                        'status' => $instanceData['status'] === 'running' ? 'active' : 'inactive',
                        'cpu_cores' => $instanceData['cpu_cores'],
                        'memory_mb' => $instanceData['memory_mb'],
                        'disk_gb' => $instanceData['disk_gb'],
                        'ip_addresses' => $ips,
                        'last_discovered_at' => now(),
                        'last_error' => null,
                    ]);
                    $results['updated']++;
                } else {
                    FleetVHost::create([
                        'domain' => $domain,
                        'slug' => str($domain)->slug(),
                        'vnode_id' => $vnode->id,
                        'instance_type' => $instanceData['type'] === 'lxc' ? 'lxc' : 'vm',
                        'instance_id' => $instanceData['vmid'],
                        'cpu_cores' => $instanceData['cpu_cores'],
                        'memory_mb' => $instanceData['memory_mb'],
                        'disk_gb' => $instanceData['disk_gb'],
                        'ip_addresses' => $ips,
                        'status' => $instanceData['status'] === 'running' ? 'active' : 'inactive',
                        'last_discovered_at' => now(),
                        'environment_vars' => [],
                        'is_active' => true,
                    ]);
                    $results['created']++;
                }
            } catch (Exception $e) {
                $results['errors'][] = "Instance {$instanceData['name']}: {$e->getMessage()}";
            }
        }

        return $results;
    }

    /**
     * Generate domain from instance name
     */
    protected function generateDomainFromName(string $name): string
    {
        // If name already looks like a domain, use it
        if (str_contains($name, '.')) {
            return $name;
        }

        // Otherwise, append your default domain
        return "{$name}.goldcoast.org";
    }

    /**
     * Parse network interface configuration
     */
    protected function parseNetworkInterface(string $netConfig): array
    {
        // Basic parsing - can be enhanced based on your needs
        return [
            'config' => $netConfig,
            // Add more parsing logic here
        ];
    }

    /**
     * Make HTTP request to Proxmox API
     */
    protected function makeRequest(string $endpoint): array
    {
        $url = $this->baseUrl.$endpoint;

        $response = Http::withOptions([
            'verify' => false, // Skip SSL verification for self-signed certs
        ])
            ->timeout($this->timeout)
            ->withHeaders([
                'Authorization' => $this->credentials['full_token'],
            ])
            ->get($url);

        if (! $response->successful()) {
            throw new Exception("API request failed: {$response->status()} - {$response->body()}");
        }

        return $response->json();
    }
}
