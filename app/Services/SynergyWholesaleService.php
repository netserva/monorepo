<?php

namespace App\Services;

use SoapClient;
use SoapFault;
use Illuminate\Support\Facades\Log;

/**
 * Synergy Wholesale API Service
 *
 * Provides integration with Synergy Wholesale domain registrar API
 * for NetServa 3.0 fleet management.
 *
 * @see https://synergywholesale.com/support-centre/api-updates/
 */
class SynergyWholesaleService
{
    private SoapClient $client;
    private string $resellerID;
    private string $apiKey;
    private const API_ENDPOINT = 'https://api.synergywholesale.com/server.php?wsdl';

    public function __construct()
    {
        $this->resellerID = config('services.synergy_wholesale.reseller_id');
        $this->apiKey = config('services.synergy_wholesale.api_key');

        if (empty($this->resellerID) || empty($this->apiKey)) {
            throw new \RuntimeException('Synergy Wholesale credentials not configured');
        }

        $this->initializeSoapClient();
    }

    private function initializeSoapClient(): void
    {
        try {
            $this->client = new SoapClient(self::API_ENDPOINT, [
                'trace' => true,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'connection_timeout' => 30,
            ]);
        } catch (SoapFault $e) {
            throw new \RuntimeException("Failed to initialize Synergy Wholesale SOAP client: {$e->getMessage()}");
        }
    }

    /**
     * Execute an API command
     */
    private function execute(string $command, array $params = []): array
    {
        $request = array_merge([
            'resellerID' => $this->resellerID,
            'apiKey' => $this->apiKey,
        ], $params);

        try {
            $response = $this->client->$command($request);

            Log::debug("SW API Call: {$command}", [
                'request' => $params,
                'response' => $response,
            ]);

            return (array) $response;

        } catch (SoapFault $e) {
            Log::error("SW API Error: {$command}", [
                'error' => $e->getMessage(),
                'request' => $params,
            ]);

            throw new \RuntimeException("Synergy Wholesale API error [{$command}]: {$e->getMessage()}");
        }
    }

    /**
     * List all domains under this reseller account
     */
    public function listDomains(): array
    {
        return $this->execute('listDomains');
    }

    /**
     * Get detailed information about a domain
     */
    public function getDomainInfo(string $domain): array
    {
        return $this->execute('domainInfo', [
            'domainName' => $domain,
        ]);
    }

    /**
     * Get bulk domain information
     */
    public function getBulkDomainInfo(array $domains): array
    {
        return $this->execute('bulkDomainInfo', [
            'domainList' => $domains,
        ]);
    }

    /**
     * Update nameservers for a domain
     *
     * @param string $domain
     * @param array $nameservers Array of nameserver hostnames (ns1.example.com, etc)
     */
    public function updateNameservers(string $domain, array $nameservers): array
    {
        return $this->execute('updateNameServers', [
            'domainName' => $domain,
            'nameServers' => $nameservers,
        ]);
    }

    /**
     * Add a child host (glue record)
     *
     * @param string $domainName The domain name (e.g., netserva.org)
     * @param string $host Hostname (e.g., ns1.netserva.org)
     * @param array $ips Array of IP addresses
     */
    public function addChildHost(string $domainName, string $host, array $ips): array
    {
        return $this->execute('addHost', [
            'domainName' => $domainName,
            'host' => $host,
            'ips' => $ips,
        ]);
    }

    /**
     * Delete a child host (glue record)
     */
    public function deleteChildHost(string $domainName, string $host): array
    {
        return $this->execute('deleteHost', [
            'domainName' => $domainName,
            'host' => $host,
        ]);
    }

    /**
     * Delete a glue record for a specific domain
     *
     * @param string $domainName The domain name (e.g., netserva.org)
     * @param string $hostname The hostname to delete (e.g., ns1.netserva.org)
     */
    public function deleteGlueRecord(string $domainName, string $hostname): array
    {
        return $this->execute('deleteHost', [
            'domainName' => $domainName,
            'host' => $hostname,
        ]);
    }

    /**
     * Add IP address to existing child host
     */
    public function addChildHostIP(string $domainName, string $host, string $ip): array
    {
        return $this->execute('addHostIP', [
            'domainName' => $domainName,
            'host' => $host,
            'ipAddress' => $ip,
        ]);
    }

    /**
     * Remove IP address from child host
     */
    public function deleteChildHostIP(string $domainName, string $host, string $ip): array
    {
        return $this->execute('deleteHostIP', [
            'domainName' => $domainName,
            'host' => $host,
            'ipAddress' => $ip,
        ]);
    }

    /**
     * Get nameservers for a domain
     */
    public function getNameservers(string $domain): array
    {
        $info = $this->getDomainInfo($domain);
        return $info['nameServers'] ?? [];
    }

    /**
     * Check if domain is using custom nameservers
     */
    public function isUsingCustomNameservers(string $domain): bool
    {
        $info = $this->getDomainInfo($domain);
        return ($info['dnsConfigType'] ?? 0) == 1; // 1 = Custom nameservers
    }

    /**
     * List all child hosts (glue records) for a domain
     */
    public function listAllHosts(string $domain): array
    {
        return $this->execute('listAllHosts', [
            'domainName' => $domain,
        ]);
    }
}
