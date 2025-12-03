<?php

namespace NetServa\Dns\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use NetServa\Dns\Models\DomainRegistrar;
use SoapClient;
use SoapFault;

/**
 * SynergyWholesale API Service
 *
 * Provides domain registration and management via SynergyWholesale SOAP API
 */
class SynergyWholesaleService
{
    protected ?SoapClient $client = null;

    protected string $resellerId;

    protected string $apiKey;

    protected string $wsdlUrl = 'https://api.synergywholesale.com/?wsdl';

    public function __construct(?DomainRegistrar $registrar = null)
    {
        if ($registrar) {
            $this->configure($registrar);
        }
    }

    /**
     * Configure service from a DomainRegistrar model
     */
    public function configure(DomainRegistrar $registrar): self
    {
        if ($registrar->registrar_type !== 'synergywholesale') {
            throw new \InvalidArgumentException('Registrar must be of type synergywholesale');
        }

        $this->wsdlUrl = $registrar->api_endpoint ?: $this->wsdlUrl;
        $this->apiKey = Crypt::decryptString($registrar->api_key_encrypted);
        $this->resellerId = $registrar->additional_config['reseller_id'] ?? '';

        return $this;
    }

    /**
     * Set credentials directly
     */
    public function setCredentials(string $resellerId, string $apiKey, ?string $wsdlUrl = null): self
    {
        $this->resellerId = $resellerId;
        $this->apiKey = $apiKey;

        if ($wsdlUrl) {
            $this->wsdlUrl = $wsdlUrl;
        }

        return $this;
    }

    /**
     * Get or create SOAP client
     */
    protected function getClient(): SoapClient
    {
        if (! $this->client) {
            $this->client = new SoapClient($this->wsdlUrl, [
                'trace' => true,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                    ],
                ]),
            ]);
        }

        return $this->client;
    }

    /**
     * Build authentication array for API calls
     */
    protected function getAuthParams(): array
    {
        return [
            'resellerID' => $this->resellerId,
            'apiKey' => $this->apiKey,
        ];
    }

    /**
     * Execute SOAP call with error handling
     */
    protected function call(string $method, array $params = []): object
    {
        try {
            $client = $this->getClient();
            $params = array_merge($this->getAuthParams(), $params);

            Log::debug("SynergyWholesale API call: {$method}", ['params' => array_diff_key($params, ['apiKey' => ''])]);

            $response = $client->__soapCall($method, [$params]);

            if (isset($response->status) && $response->status === 'ERR') {
                throw new \Exception($response->errorMessage ?? 'Unknown API error');
            }

            return $response;
        } catch (SoapFault $e) {
            Log::error("SynergyWholesale SOAP error: {$e->getMessage()}");
            throw new \Exception("SynergyWholesale API error: {$e->getMessage()}");
        }
    }

    /**
     * Get account balance
     */
    public function getBalance(): array
    {
        $response = $this->call('balanceQuery');

        return [
            'balance' => $response->balance ?? 0,
            'status' => $response->status ?? 'unknown',
        ];
    }

    /**
     * Check domain availability
     */
    public function checkDomain(string $domain): array
    {
        $response = $this->call('checkDomain', [
            'domainName' => $domain,
        ]);

        return [
            'domain' => $domain,
            'available' => ($response->status ?? '') === 'AVAILABLE',
            'status' => $response->status ?? 'unknown',
        ];
    }

    /**
     * Check multiple domains availability
     */
    public function bulkCheckDomain(array $domains): array
    {
        $response = $this->call('bulkCheckDomain', [
            'domainList' => implode(',', $domains),
        ]);

        $results = [];
        if (isset($response->domainList) && is_array($response->domainList)) {
            foreach ($response->domainList as $item) {
                $results[$item->domain] = [
                    'domain' => $item->domain,
                    'available' => $item->available === 'Yes',
                    'status' => $item->available,
                ];
            }
        }

        return $results;
    }

    /**
     * Get domain info
     */
    public function getDomainInfo(string $domain): array
    {
        $response = $this->call('domainInfo', [
            'domainName' => $domain,
        ]);

        return [
            'domain' => $domain,
            'status' => $response->domain_status ?? 'unknown',
            'expiry_date' => $response->domain_expiry ?? null,
            'nameservers' => $this->extractNameservers($response),
            'registrant' => $response->registrant_name ?? null,
            'created_date' => $response->domain_create_date ?? null,
            'auto_renew' => ($response->autoRenew ?? '') === 'Yes',
            'locked' => ($response->domainLocked ?? '') === 'Yes',
            'raw' => $response,
        ];
    }

    /**
     * Extract nameservers from response
     */
    protected function extractNameservers(object $response): array
    {
        $nameservers = [];
        for ($i = 1; $i <= 5; $i++) {
            $key = "ns{$i}";
            if (! empty($response->$key)) {
                $nameservers[] = $response->$key;
            }
        }

        return $nameservers;
    }

    /**
     * List all domains in account
     */
    public function listDomains(): array
    {
        $response = $this->call('listDomains');

        $domains = [];
        if (isset($response->domainList) && is_array($response->domainList)) {
            foreach ($response->domainList as $item) {
                $domains[] = [
                    'domain' => $item->domainName ?? $item,
                    'status' => $item->domainStatus ?? 'unknown',
                    'expiry' => $item->domain_expiry ?? null,
                ];
            }
        }

        return $domains;
    }

    /**
     * Update domain nameservers
     */
    public function updateNameservers(string $domain, array $nameservers): array
    {
        $params = ['domainName' => $domain];

        foreach (array_values($nameservers) as $i => $ns) {
            $params['ns'.($i + 1)] = $ns;
        }

        $response = $this->call('updateNameServers', $params);

        return [
            'success' => ($response->status ?? '') === 'OK',
            'message' => $response->errorMessage ?? 'Nameservers updated',
        ];
    }

    /**
     * Get domain extension (TLD) pricing
     */
    public function getDomainPricing(?string $tld = null): array
    {
        $response = $this->call('getDomainPricing', $tld ? ['tld' => $tld] : []);

        return (array) $response;
    }

    /**
     * Check if domain can be renewed
     */
    public function canRenewDomain(string $domain): array
    {
        $response = $this->call('canRenewDomain', [
            'domainName' => $domain,
        ]);

        return [
            'can_renew' => ($response->canRenew ?? '') === 'Yes',
            'max_years' => $response->maxPeriod ?? 1,
            'current_expiry' => $response->domainExpiry ?? null,
        ];
    }

    /**
     * Renew a domain
     */
    public function renewDomain(string $domain, int $years = 1): array
    {
        $response = $this->call('renewDomain', [
            'domainName' => $domain,
            'years' => $years,
        ]);

        return [
            'success' => ($response->status ?? '') === 'OK',
            'new_expiry' => $response->new_expiry ?? null,
            'message' => $response->errorMessage ?? 'Domain renewed',
        ];
    }

    /**
     * Lock/unlock domain
     */
    public function setDomainLock(string $domain, bool $lock): array
    {
        $method = $lock ? 'lockDomain' : 'unlockDomain';
        $response = $this->call($method, [
            'domainName' => $domain,
        ]);

        return [
            'success' => ($response->status ?? '') === 'OK',
            'locked' => $lock,
            'message' => $response->errorMessage ?? ($lock ? 'Domain locked' : 'Domain unlocked'),
        ];
    }

    /**
     * Get EPP/Auth code for domain transfer
     */
    public function getEppCode(string $domain): array
    {
        $response = $this->call('domainInfo', [
            'domainName' => $domain,
        ]);

        return [
            'domain' => $domain,
            'epp_code' => $response->domainPassword ?? null,
            'success' => ! empty($response->domainPassword),
        ];
    }

    /**
     * Test API connection
     */
    public function testConnection(): array
    {
        try {
            $balance = $this->getBalance();

            return [
                'success' => true,
                'balance' => $balance['balance'],
                'message' => 'Connection successful',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'balance' => null,
                'message' => $e->getMessage(),
            ];
        }
    }
}
