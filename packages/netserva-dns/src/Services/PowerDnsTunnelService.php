<?php

namespace NetServa\Dns\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use NetServa\Core\Services\SshTunnelService;
use NetServa\Dns\Models\DnsProvider;

/**
 * Enhanced PowerDNS Service with SSH Tunnel Support
 *
 * Provides secure PowerDNS API access through SSH tunnels for remote servers.
 * This service integrates with NS SSH Manager for secure tunnel management.
 */
class PowerDnsTunnelService
{
    protected SshTunnelService $tunnelService;

    protected array $activeTunnels = [];

    public function __construct(SshTunnelService $tunnelService)
    {
        $this->tunnelService = $tunnelService;
    }

    /**
     * Execute PowerDNS API call with automatic tunnel management
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $endpoint  API endpoint path
     * @param  string  $method  HTTP method
     * @param  array  $data  Request data
     * @return array API response
     */
    public function apiCall(DnsProvider $provider, string $endpoint, string $method = 'GET', array $data = []): array
    {
        try {
            // Ensure SSH tunnel is active for PowerDNS API
            $tunnelResult = $this->ensurePowerDnsTunnel($provider);

            if (! $tunnelResult['success']) {
                throw new Exception('Failed to establish SSH tunnel: '.$tunnelResult['message']);
            }

            $tunnelEndpoint = $tunnelResult['endpoint'];
            $apiUrl = $tunnelEndpoint.'/api/v1'.$endpoint;

            Log::info('PowerDNS API call through tunnel', [
                'provider' => $provider->name,
                'endpoint' => $endpoint,
                'method' => $method,
                'tunnel_endpoint' => $tunnelEndpoint,
                'api_url' => $apiUrl,
            ]);

            // Make the API request through the SSH tunnel
            $response = Http::withHeaders([
                'X-API-Key' => $provider->api_key,
                'Content-Type' => 'application/json',
                'User-Agent' => 'NS-DNS-Manager/1.0',
            ])
                ->timeout($provider->timeout ?? 30)
                ->retry(3, 1000) // Retry 3 times with 1 second delay
                ->$method($apiUrl, $data);

            if ($response->successful()) {
                $result = [
                    'success' => true,
                    'data' => $response->json() ?? [],
                    'status_code' => $response->status(),
                    'tunnel_used' => true,
                    'tunnel_endpoint' => $tunnelEndpoint,
                ];

                Log::info('PowerDNS API call successful', [
                    'provider' => $provider->name,
                    'endpoint' => $endpoint,
                    'status_code' => $response->status(),
                ]);

                return $result;
            }

            throw new Exception('API request failed: HTTP '.$response->status().' - '.$response->body());
        } catch (Exception $e) {
            Log::error('PowerDNS API call failed', [
                'provider' => $provider->name,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'tunnel_used' => false,
            ];
        }
    }

    /**
     * Ensure SSH tunnel exists for PowerDNS API access
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @return array Tunnel establishment result
     */
    public function ensurePowerDnsTunnel(DnsProvider $provider): array
    {
        // Extract SSH host from provider configuration
        $sshHost = $provider->config['ssh_host'] ?? null;

        if (! $sshHost) {
            return [
                'success' => false,
                'message' => 'No SSH host configured for PowerDNS provider',
            ];
        }

        // Get PowerDNS API port from configuration
        $remotePort = $provider->config['api_port'] ?? 8081;

        // Ensure tunnel is active
        $result = $this->tunnelService->ensureTunnel($sshHost, 'powerdns', $remotePort);

        if ($result['success']) {
            Log::info('PowerDNS SSH tunnel ready', [
                'provider' => $provider->name,
                'ssh_host' => $sshHost,
                'local_port' => $result['local_port'],
                'remote_port' => $remotePort,
                'endpoint' => $result['endpoint'],
                'created' => $result['created'] ?? false,
            ]);
        }

        return $result;
    }

    /**
     * Test PowerDNS connectivity through SSH tunnel
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @return array Connection test result
     */
    public function testConnection(DnsProvider $provider): array
    {
        try {
            $result = $this->apiCall($provider, '/servers');

            if ($result['success']) {
                $servers = $result['data'] ?? [];
                $serverCount = count($servers);

                Log::info('PowerDNS connection test successful', [
                    'provider' => $provider->name,
                    'server_count' => $serverCount,
                    'tunnel_endpoint' => $result['tunnel_endpoint'] ?? null,
                ]);

                return [
                    'success' => true,
                    'message' => "Connected to PowerDNS successfully ($serverCount servers found)",
                    'servers' => $servers,
                    'tunnel_used' => $result['tunnel_used'] ?? false,
                ];
            }

            return [
                'success' => false,
                'message' => 'PowerDNS connection test failed: '.($result['error'] ?? 'Unknown error'),
            ];

        } catch (Exception $e) {
            Log::error('PowerDNS connection test exception', [
                'provider' => $provider->name,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Connection test failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get all DNS zones from PowerDNS
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @return array Zones list
     */
    public function getZones(DnsProvider $provider): array
    {
        $result = $this->apiCall($provider, '/servers/localhost/zones');

        if ($result['success']) {
            return $result['data'];
        }

        Log::error('Failed to fetch PowerDNS zones', [
            'provider' => $provider->name,
            'error' => $result['error'] ?? 'Unknown error',
        ]);

        return [];
    }

    /**
     * Get specific DNS zone from PowerDNS
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $zoneName  Zone name
     * @return array Zone data
     */
    public function getZone(DnsProvider $provider, string $zoneName): array
    {
        $result = $this->apiCall($provider, "/servers/localhost/zones/$zoneName");

        if ($result['success']) {
            return $result['data'];
        }

        Log::warning('Failed to fetch PowerDNS zone', [
            'provider' => $provider->name,
            'zone' => $zoneName,
            'error' => $result['error'] ?? 'Unknown error',
        ]);

        return [];
    }

    /**
     * Create DNS zone in PowerDNS
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  array  $zoneData  Zone configuration
     * @return array Creation result
     */
    public function createZone(DnsProvider $provider, array $zoneData): array
    {
        $result = $this->apiCall($provider, '/servers/localhost/zones', 'POST', $zoneData);

        if ($result['success']) {
            Log::info('PowerDNS zone created', [
                'provider' => $provider->name,
                'zone' => $zoneData['name'] ?? 'unknown',
                'tunnel_used' => $result['tunnel_used'],
            ]);

            return [
                'success' => true,
                'message' => 'Zone created successfully',
                'data' => $result['data'],
            ];
        }

        Log::error('Failed to create PowerDNS zone', [
            'provider' => $provider->name,
            'zone_data' => $zoneData,
            'error' => $result['error'],
        ]);

        return [
            'success' => false,
            'message' => 'Failed to create zone: '.$result['error'],
        ];
    }

    /**
     * Update DNS zone in PowerDNS
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $zoneName  Zone name
     * @param  array  $updateData  Update data
     * @return array Update result
     */
    public function updateZone(DnsProvider $provider, string $zoneName, array $updateData): array
    {
        $result = $this->apiCall($provider, "/servers/localhost/zones/$zoneName", 'PUT', $updateData);

        if ($result['success']) {
            Log::info('PowerDNS zone updated', [
                'provider' => $provider->name,
                'zone' => $zoneName,
                'tunnel_used' => $result['tunnel_used'],
            ]);

            return [
                'success' => true,
                'message' => 'Zone updated successfully',
                'data' => $result['data'],
            ];
        }

        Log::error('Failed to update PowerDNS zone', [
            'provider' => $provider->name,
            'zone' => $zoneName,
            'error' => $result['error'],
        ]);

        return [
            'success' => false,
            'message' => 'Failed to update zone: '.$result['error'],
        ];
    }

    /**
     * Delete DNS zone from PowerDNS
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $zoneName  Zone name
     * @return array Deletion result
     */
    public function deleteZone(DnsProvider $provider, string $zoneName): array
    {
        $result = $this->apiCall($provider, "/servers/localhost/zones/$zoneName", 'DELETE');

        if ($result['success']) {
            Log::info('PowerDNS zone deleted', [
                'provider' => $provider->name,
                'zone' => $zoneName,
                'tunnel_used' => $result['tunnel_used'],
            ]);

            return [
                'success' => true,
                'message' => 'Zone deleted successfully',
            ];
        }

        Log::error('Failed to delete PowerDNS zone', [
            'provider' => $provider->name,
            'zone' => $zoneName,
            'error' => $result['error'],
        ]);

        return [
            'success' => false,
            'message' => 'Failed to delete zone: '.$result['error'],
        ];
    }

    /**
     * Update DNS records in PowerDNS using RRSET patch
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string  $zoneName  Zone name
     * @param  array  $rrsets  Record sets to update
     * @return array Update result
     */
    public function updateRecords(DnsProvider $provider, string $zoneName, array $rrsets): array
    {
        $updateData = ['rrsets' => $rrsets];

        $result = $this->apiCall($provider, "/servers/localhost/zones/$zoneName", 'PATCH', $updateData);

        if ($result['success']) {
            Log::info('PowerDNS records updated', [
                'provider' => $provider->name,
                'zone' => $zoneName,
                'rrset_count' => count($rrsets),
                'tunnel_used' => $result['tunnel_used'],
            ]);

            return [
                'success' => true,
                'message' => 'Records updated successfully',
                'records_updated' => count($rrsets),
            ];
        }

        Log::error('Failed to update PowerDNS records', [
            'provider' => $provider->name,
            'zone' => $zoneName,
            'rrsets' => $rrsets,
            'error' => $result['error'],
        ]);

        return [
            'success' => false,
            'message' => 'Failed to update records: '.$result['error'],
        ];
    }

    /**
     * Get PowerDNS server statistics
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @return array Server statistics
     */
    public function getServerStats(DnsProvider $provider): array
    {
        $result = $this->apiCall($provider, '/servers/localhost/statistics');

        if ($result['success']) {
            return [
                'success' => true,
                'stats' => $result['data'],
                'tunnel_used' => $result['tunnel_used'],
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to get server stats: '.$result['error'],
        ];
    }

    /**
     * Flush PowerDNS cache
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @param  string|null  $domain  Specific domain to flush (null for all)
     * @return array Flush result
     */
    public function flushCache(DnsProvider $provider, ?string $domain = null): array
    {
        $endpoint = '/servers/localhost/cache/flush';
        if ($domain) {
            $endpoint .= "?domain=$domain";
        }

        $result = $this->apiCall($provider, $endpoint, 'PUT');

        if ($result['success']) {
            Log::info('PowerDNS cache flushed', [
                'provider' => $provider->name,
                'domain' => $domain ?? 'all',
                'tunnel_used' => $result['tunnel_used'],
            ]);

            return [
                'success' => true,
                'message' => $domain ? "Cache flushed for $domain" : 'All cache flushed',
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to flush cache: '.$result['error'],
        ];
    }

    /**
     * Get tunnel status for PowerDNS provider
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @return array Tunnel status
     */
    public function getTunnelStatus(DnsProvider $provider): array
    {
        $sshHost = $provider->config['ssh_host'] ?? null;

        if (! $sshHost) {
            return [
                'active' => false,
                'message' => 'No SSH host configured',
            ];
        }

        $localPort = $this->tunnelService->generateLocalPort($sshHost, 'powerdns');
        $isActive = $this->tunnelService->isTunnelActive($sshHost, $localPort);

        return [
            'active' => $isActive,
            'ssh_host' => $sshHost,
            'local_port' => $localPort,
            'remote_port' => $provider->config['api_port'] ?? 8081,
            'endpoint' => $isActive ? "http://localhost:$localPort" : null,
            'message' => $isActive ? 'SSH tunnel is active' : 'SSH tunnel is inactive',
        ];
    }

    /**
     * Close SSH tunnel for PowerDNS provider
     *
     * @param  DnsProvider  $provider  DNS provider configuration
     * @return array Close result
     */
    public function closeTunnel(DnsProvider $provider): array
    {
        $sshHost = $provider->config['ssh_host'] ?? null;

        if (! $sshHost) {
            return [
                'success' => false,
                'message' => 'No SSH host configured',
            ];
        }

        $localPort = $this->tunnelService->generateLocalPort($sshHost, 'powerdns');

        return $this->tunnelService->closeTunnel($sshHost, $localPort);
    }

    /**
     * Validate PowerDNS provider configuration
     *
     * @param  array  $config  Provider configuration
     * @return array Validation result
     */
    public function validateConfig(array $config): array
    {
        $errors = [];

        // Required fields
        if (empty($config['ssh_host'])) {
            $errors[] = 'SSH host is required for PowerDNS with tunneling';
        }

        if (empty($config['api_key'])) {
            $errors[] = 'PowerDNS API key is required';
        }

        // Optional but recommended fields
        $warnings = [];

        if (empty($config['api_port'])) {
            $warnings[] = 'API port not specified, using default 8081';
        }

        if (empty($config['timeout'])) {
            $warnings[] = 'Timeout not specified, using default 30 seconds';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}
