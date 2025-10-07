<?php

namespace NetServa\Dns\Services;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use NetServa\Cli\Services\RemoteExecutionService;
use NetServa\Dns\Models\DnsZone;

/**
 * DNSSEC Management Service
 *
 * Comprehensive DNSSEC key management, validation, and monitoring service
 * supporting multiple DNS providers and DNSSEC operations.
 */
class DnssecService
{
    protected RemoteExecutionService $remoteExecution;

    protected CloudFlareService $cloudflare;

    protected array $config;

    public function __construct(
        RemoteExecutionService $remoteExecution,
        CloudFlareService $cloudflare
    ) {
        $this->remoteExecution = $remoteExecution;
        $this->cloudflare = $cloudflare;
        $this->config = config('dns.dnssec', [
            'algorithms' => [
                3 => 'DSA',
                5 => 'RSA/SHA-1',
                6 => 'DSA-NSEC3-SHA1',
                7 => 'RSASHA1-NSEC3-SHA1',
                8 => 'RSA/SHA-256',
                10 => 'RSA/SHA-512',
                13 => 'ECDSA Curve P-256 with SHA-256',
                14 => 'ECDSA Curve P-384 with SHA-384',
                15 => 'Ed25519',
                16 => 'Ed448',
            ],
            'digest_types' => [
                1 => 'SHA-1',
                2 => 'SHA-256',
                3 => 'GOST R 34.11-94',
                4 => 'SHA-384',
            ],
            'default_algorithm' => 8, // RSA/SHA-256
            'default_digest_type' => 2, // SHA-256
            'key_size' => 2048,
            'ksk_size' => 4096,
            'key_rollover_warning_days' => 30,
            'cache_ttl' => 3600,
        ]);
    }

    /**
     * Get DNSSEC status for a zone
     */
    public function getZoneDnssecStatus(string $zoneName): array
    {
        $cacheKey = "dnssec:status:{$zoneName}";

        return Cache::remember($cacheKey, $this->config['cache_ttl'], function () use ($zoneName) {
            return $this->fetchZoneDnssecStatus($zoneName);
        });
    }

    /**
     * Enable DNSSEC for a zone
     */
    public function enableDnssec(string $zoneName, array $options = []): array
    {
        $zone = DnsZone::where('name', $zoneName)->first();
        if (! $zone) {
            throw new DnssecException("Zone '{$zoneName}' not found");
        }

        $provider = $zone->provider;
        if (! $provider) {
            throw new DnssecException("No DNS provider configured for zone '{$zoneName}'");
        }

        Log::info("Enabling DNSSEC for zone: {$zoneName}", [
            'provider' => $provider->type,
            'options' => $options,
        ]);

        $result = match ($provider->type) {
            'cloudflare' => $this->enableCloudFlareDnssec($zone, $options),
            'powerdns' => $this->enablePowerDnsDnssec($zone, $options),
            'bind9' => $this->enableBind9Dnssec($zone, $options),
            default => throw new DnssecException("DNSSEC not supported for provider: {$provider->type}")
        };

        // Update zone model
        $zone->update([
            'dnssec_enabled' => true,
            'auto_dnssec' => $options['auto_dnssec'] ?? true,
            'provider_data' => array_merge($zone->provider_data ?? [], $result),
        ]);

        $this->clearCache($zoneName);

        return $result;
    }

    /**
     * Disable DNSSEC for a zone
     */
    public function disableDnssec(string $zoneName): bool
    {
        $zone = DnsZone::where('name', $zoneName)->first();
        if (! $zone) {
            throw new DnssecException("Zone '{$zoneName}' not found");
        }

        $provider = $zone->provider;
        if (! $provider) {
            throw new DnssecException("No DNS provider configured for zone '{$zoneName}'");
        }

        Log::info("Disabling DNSSEC for zone: {$zoneName}", [
            'provider' => $provider->type,
        ]);

        $result = match ($provider->type) {
            'cloudflare' => $this->disableCloudFlareDnssec($zone),
            'powerdns' => $this->disablePowerDnsDnssec($zone),
            'bind9' => $this->disableBind9Dnssec($zone),
            default => throw new DnssecException("DNSSEC not supported for provider: {$provider->type}")
        };

        // Update zone model
        $zone->update([
            'dnssec_enabled' => false,
            'auto_dnssec' => false,
            'provider_data' => array_merge($zone->provider_data ?? [], ['dnssec_disabled_at' => now()]),
        ]);

        $this->clearCache($zoneName);

        return $result;
    }

    /**
     * Generate DNSSEC keys for a zone
     */
    public function generateKeys(string $zoneName, array $options = []): array
    {
        $zone = DnsZone::where('name', $zoneName)->first();
        if (! $zone) {
            throw new DnssecException("Zone '{$zoneName}' not found");
        }

        $algorithm = $options['algorithm'] ?? $this->config['default_algorithm'];
        $keySize = $options['key_size'] ?? $this->config['key_size'];
        $kskSize = $options['ksk_size'] ?? $this->config['ksk_size'];

        Log::info("Generating DNSSEC keys for zone: {$zoneName}", [
            'algorithm' => $algorithm,
            'key_size' => $keySize,
            'ksk_size' => $kskSize,
        ]);

        return match ($zone->provider->type) {
            'powerdns' => $this->generatePowerDnsKeys($zone, $algorithm, $keySize, $kskSize),
            'bind9' => $this->generateBind9Keys($zone, $algorithm, $keySize, $kskSize),
            'cloudflare' => $this->generateCloudFlareKeys($zone, $algorithm),
            default => throw new DnssecException("Key generation not supported for provider: {$zone->provider->type}")
        };
    }

    /**
     * Get DS records for a zone
     */
    public function getDsRecords(string $zoneName): array
    {
        $zone = DnsZone::where('name', $zoneName)->first();
        if (! $zone) {
            throw new DnssecException("Zone '{$zoneName}' not found");
        }

        if (! $zone->dnssec_enabled) {
            throw new DnssecException("DNSSEC is not enabled for zone '{$zoneName}'");
        }

        $cacheKey = "dnssec:ds_records:{$zoneName}";

        return Cache::remember($cacheKey, $this->config['cache_ttl'], function () use ($zone) {
            return $this->fetchDsRecords($zone);
        });
    }

    /**
     * Validate DNSSEC configuration for a zone
     */
    public function validateDnssec(string $zoneName): array
    {
        $zone = DnsZone::where('name', $zoneName)->first();
        if (! $zone) {
            throw new DnssecException("Zone '{$zoneName}' not found");
        }

        Log::info("Validating DNSSEC for zone: {$zoneName}");

        $validationResults = [
            'zone' => $zoneName,
            'dnssec_enabled' => $zone->dnssec_enabled,
            'validation_time' => now(),
            'checks' => [],
        ];

        // Check 1: DNSSEC enabled in zone
        $validationResults['checks']['zone_enabled'] = [
            'name' => 'Zone DNSSEC Enabled',
            'status' => $zone->dnssec_enabled ? 'pass' : 'fail',
            'message' => $zone->dnssec_enabled
                ? 'DNSSEC is enabled for this zone'
                : 'DNSSEC is not enabled for this zone',
        ];

        if (! $zone->dnssec_enabled) {
            $validationResults['overall_status'] = 'fail';

            return $validationResults;
        }

        // Check 2: DS records in parent zone
        $validationResults['checks']['ds_records'] = $this->validateDsRecords($zoneName);

        // Check 3: DNSKEY records
        $validationResults['checks']['dnskey_records'] = $this->validateDnskeyRecords($zoneName);

        // Check 4: RRSIG records
        $validationResults['checks']['rrsig_records'] = $this->validateRrsigRecords($zoneName);

        // Check 5: NSEC/NSEC3 records
        $validationResults['checks']['nsec_records'] = $this->validateNsecRecords($zoneName);

        // Check 6: Key rollover status
        $validationResults['checks']['key_rollover'] = $this->validateKeyRollover($zoneName);

        // Overall status
        $allPassed = collect($validationResults['checks'])->every(fn ($check) => $check['status'] === 'pass');
        $validationResults['overall_status'] = $allPassed ? 'pass' : 'fail';

        return $validationResults;
    }

    /**
     * Perform key rollover for a zone
     */
    public function performKeyRollover(string $zoneName, array $options = []): array
    {
        $zone = DnsZone::where('name', $zoneName)->first();
        if (! $zone) {
            throw new DnssecException("Zone '{$zoneName}' not found");
        }

        if (! $zone->dnssec_enabled) {
            throw new DnssecException("DNSSEC is not enabled for zone '{$zoneName}'");
        }

        Log::info("Performing key rollover for zone: {$zoneName}", $options);

        return match ($zone->provider->type) {
            'powerdns' => $this->performPowerDnsKeyRollover($zone, $options),
            'bind9' => $this->performBind9KeyRollover($zone, $options),
            'cloudflare' => $this->performCloudFlareKeyRollover($zone, $options),
            default => throw new DnssecException("Key rollover not supported for provider: {$zone->provider->type}")
        };
    }

    /**
     * Monitor DNSSEC status across all zones
     */
    public function monitorAllZones(): Collection
    {
        $zones = DnsZone::where('dnssec_enabled', true)->get();
        $results = collect();

        foreach ($zones as $zone) {
            try {
                $status = $this->getZoneDnssecStatus($zone->name);
                $validation = $this->validateDnssec($zone->name);

                $results->push([
                    'zone' => $zone->name,
                    'provider' => $zone->provider->type ?? 'unknown',
                    'status' => $status,
                    'validation' => $validation,
                    'last_checked' => now(),
                ]);
            } catch (Exception $e) {
                $results->push([
                    'zone' => $zone->name,
                    'provider' => $zone->provider->type ?? 'unknown',
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'last_checked' => now(),
                ]);
            }
        }

        return $results;
    }

    // Provider-specific implementations

    protected function enableCloudFlareDnssec(DnsZone $zone, array $options): array
    {
        // CloudFlare DNSSEC implementation
        $zoneData = $this->cloudflare->getZoneByName($zone->name);
        if (! $zoneData) {
            throw new DnssecException("Zone not found in CloudFlare: {$zone->name}");
        }

        // CloudFlare automatically manages DNSSEC - just need to enable it
        $result = $this->cloudflare->updateZoneSetting($zoneData['id'], 'dnssec', 'active');

        return [
            'dnssec_enabled' => true,
            'provider' => 'cloudflare',
            'managed_by_provider' => true,
            'enabled_at' => now(),
            'ds_records' => $this->fetchCloudFlareDsRecords($zoneData['id']),
        ];
    }

    protected function disableCloudFlareDnssec(DnsZone $zone): bool
    {
        $zoneData = $this->cloudflare->getZoneByName($zone->name);
        if (! $zoneData) {
            throw new DnssecException("Zone not found in CloudFlare: {$zone->name}");
        }

        $this->cloudflare->updateZoneSetting($zoneData['id'], 'dnssec', 'disabled');

        return true;
    }

    protected function enablePowerDnsDnssec(DnsZone $zone, array $options): array
    {
        // PowerDNS DNSSEC implementation via API
        $server = $options['server'] ?? 'localhost';
        $apiKey = $options['api_key'] ?? config('dns.powerdns.api_key');

        $result = $this->remoteExecution->executeAsRoot($server,
            "pdnsutil secure-zone {$zone->name}"
        );

        if (! $result['success']) {
            throw new DnssecException('Failed to enable DNSSEC: '.$result['output']);
        }

        return [
            'dnssec_enabled' => true,
            'provider' => 'powerdns',
            'server' => $server,
            'enabled_at' => now(),
            'keys_generated' => true,
        ];
    }

    protected function disablePowerDnsDnssec(DnsZone $zone): bool
    {
        $server = $zone->provider->connection_config['server'] ?? 'localhost';

        $result = $this->remoteExecution->executeAsRoot($server,
            "pdnsutil disable-dnssec {$zone->name}"
        );

        return $result['success'];
    }

    protected function generatePowerDnsKeys(DnsZone $zone, int $algorithm, int $keySize, int $kskSize): array
    {
        $server = $zone->provider->connection_config['server'] ?? 'localhost';

        // Generate ZSK (Zone Signing Key)
        $zskResult = $this->remoteExecution->executeAsRoot($server,
            "pdnsutil add-zone-key {$zone->name} zsk {$algorithm} {$keySize}"
        );

        // Generate KSK (Key Signing Key)
        $kskResult = $this->remoteExecution->executeAsRoot($server,
            "pdnsutil add-zone-key {$zone->name} ksk {$algorithm} {$kskSize}"
        );

        if (! $zskResult['success'] || ! $kskResult['success']) {
            throw new DnssecException('Failed to generate DNSSEC keys');
        }

        return [
            'zsk_generated' => $zskResult['success'],
            'ksk_generated' => $kskResult['success'],
            'algorithm' => $algorithm,
            'key_size' => $keySize,
            'ksk_size' => $kskSize,
            'generated_at' => now(),
        ];
    }

    protected function generateBind9Keys(DnsZone $zone, int $algorithm, int $keySize, int $kskSize): array
    {
        $server = $zone->provider->connection_config['server'] ?? 'localhost';
        $keyDir = $zone->provider->connection_config['key_directory'] ?? '/etc/bind/keys';

        // Generate ZSK
        $zskResult = $this->remoteExecution->executeAsRoot($server,
            "cd {$keyDir} && dnssec-keygen -a {$algorithm} -b {$keySize} {$zone->name}"
        );

        // Generate KSK
        $kskResult = $this->remoteExecution->executeAsRoot($server,
            "cd {$keyDir} && dnssec-keygen -a {$algorithm} -b {$kskSize} -f KSK {$zone->name}"
        );

        if (! $zskResult['success'] || ! $kskResult['success']) {
            throw new DnssecException('Failed to generate DNSSEC keys');
        }

        return [
            'zsk_generated' => $zskResult['success'],
            'ksk_generated' => $kskResult['success'],
            'algorithm' => $algorithm,
            'key_size' => $keySize,
            'ksk_size' => $kskSize,
            'key_directory' => $keyDir,
            'generated_at' => now(),
        ];
    }

    protected function generateCloudFlareKeys(DnsZone $zone, int $algorithm): array
    {
        // CloudFlare manages keys automatically
        return [
            'managed_by_provider' => true,
            'algorithm' => $algorithm,
            'provider' => 'cloudflare',
            'generated_at' => now(),
        ];
    }

    // Validation methods

    protected function validateDsRecords(string $zoneName): array
    {
        try {
            // Query parent zone for DS records
            $result = $this->remoteExecution->executeAsRoot('localhost',
                "dig +short DS {$zoneName} @8.8.8.8"
            );

            $dsRecords = array_filter(explode("\n", trim($result['output'])));

            return [
                'name' => 'DS Records in Parent Zone',
                'status' => ! empty($dsRecords) ? 'pass' : 'fail',
                'message' => ! empty($dsRecords)
                    ? 'DS records found in parent zone'
                    : 'No DS records found in parent zone',
                'records' => $dsRecords,
            ];
        } catch (Exception $e) {
            return [
                'name' => 'DS Records in Parent Zone',
                'status' => 'error',
                'message' => 'Failed to check DS records: '.$e->getMessage(),
            ];
        }
    }

    protected function validateDnskeyRecords(string $zoneName): array
    {
        try {
            $result = $this->remoteExecution->executeAsRoot('localhost',
                "dig +short DNSKEY {$zoneName} @8.8.8.8"
            );

            $dnskeyRecords = array_filter(explode("\n", trim($result['output'])));

            return [
                'name' => 'DNSKEY Records',
                'status' => ! empty($dnskeyRecords) ? 'pass' : 'fail',
                'message' => ! empty($dnskeyRecords)
                    ? 'DNSKEY records found'
                    : 'No DNSKEY records found',
                'records' => $dnskeyRecords,
            ];
        } catch (Exception $e) {
            return [
                'name' => 'DNSKEY Records',
                'status' => 'error',
                'message' => 'Failed to check DNSKEY records: '.$e->getMessage(),
            ];
        }
    }

    protected function validateRrsigRecords(string $zoneName): array
    {
        try {
            $result = $this->remoteExecution->executeAsRoot('localhost',
                "dig +short RRSIG {$zoneName} @8.8.8.8"
            );

            $rrsigRecords = array_filter(explode("\n", trim($result['output'])));

            return [
                'name' => 'RRSIG Records',
                'status' => ! empty($rrsigRecords) ? 'pass' : 'fail',
                'message' => ! empty($rrsigRecords)
                    ? 'RRSIG records found'
                    : 'No RRSIG records found',
                'records' => $rrsigRecords,
            ];
        } catch (Exception $e) {
            return [
                'name' => 'RRSIG Records',
                'status' => 'error',
                'message' => 'Failed to check RRSIG records: '.$e->getMessage(),
            ];
        }
    }

    protected function validateNsecRecords(string $zoneName): array
    {
        try {
            $result = $this->remoteExecution->executeAsRoot('localhost',
                "dig +short NSEC {$zoneName} @8.8.8.8"
            );

            $nsecRecords = array_filter(explode("\n", trim($result['output'])));

            return [
                'name' => 'NSEC/NSEC3 Records',
                'status' => ! empty($nsecRecords) ? 'pass' : 'warn',
                'message' => ! empty($nsecRecords)
                    ? 'NSEC records found'
                    : 'No NSEC records found (may use NSEC3)',
                'records' => $nsecRecords,
            ];
        } catch (Exception $e) {
            return [
                'name' => 'NSEC/NSEC3 Records',
                'status' => 'error',
                'message' => 'Failed to check NSEC records: '.$e->getMessage(),
            ];
        }
    }

    protected function validateKeyRollover(string $zoneName): array
    {
        // Check key ages and warn if rollover is needed
        try {
            $zone = DnsZone::where('name', $zoneName)->first();
            $keyData = $zone->provider_data['dnssec_keys'] ?? [];

            $warningDays = $this->config['key_rollover_warning_days'];
            $oldKeys = collect($keyData)->filter(function ($key) use ($warningDays) {
                $createdAt = $key['created_at'] ?? null;
                if (! $createdAt) {
                    return false;
                }

                return now()->diffInDays($createdAt) > (365 - $warningDays);
            });

            return [
                'name' => 'Key Rollover Status',
                'status' => $oldKeys->isEmpty() ? 'pass' : 'warn',
                'message' => $oldKeys->isEmpty()
                    ? 'Keys are within rollover timeframe'
                    : "Keys approaching rollover deadline ({$oldKeys->count()} keys)",
                'old_keys' => $oldKeys->values()->toArray(),
            ];
        } catch (Exception $e) {
            return [
                'name' => 'Key Rollover Status',
                'status' => 'error',
                'message' => 'Failed to check key rollover status: '.$e->getMessage(),
            ];
        }
    }

    // Helper methods

    protected function fetchZoneDnssecStatus(string $zoneName): array
    {
        try {
            $zone = DnsZone::where('name', $zoneName)->first();
            if (! $zone) {
                return ['status' => 'zone_not_found'];
            }

            return [
                'zone' => $zoneName,
                'dnssec_enabled' => $zone->dnssec_enabled,
                'auto_dnssec' => $zone->auto_dnssec,
                'provider' => $zone->provider->type ?? 'unknown',
                'last_updated' => $zone->updated_at,
                'provider_data' => $zone->provider_data ?? [],
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function fetchDsRecords(DnsZone $zone): array
    {
        return match ($zone->provider->type) {
            'cloudflare' => $this->fetchCloudFlareDsRecords($zone->provider_data['zone_id'] ?? null),
            'powerdns' => $this->fetchPowerDnsDsRecords($zone),
            'bind9' => $this->fetchBind9DsRecords($zone),
            default => []
        };
    }

    protected function fetchCloudFlareDsRecords(?string $zoneId): array
    {
        if (! $zoneId) {
            return [];
        }

        try {
            // CloudFlare API call to get DNSSEC details
            $dnssecInfo = $this->cloudflare->getZoneDnssecInfo($zoneId);

            return $dnssecInfo['ds_records'] ?? [];
        } catch (Exception $e) {
            Log::warning('Failed to fetch CloudFlare DS records', ['error' => $e->getMessage()]);

            return [];
        }
    }

    protected function fetchPowerDnsDsRecords(DnsZone $zone): array
    {
        $server = $zone->provider->connection_config['server'] ?? 'localhost';

        try {
            $result = $this->remoteExecution->executeAsRoot($server,
                "pdnsutil show-zone {$zone->name} | grep DS"
            );

            if ($result['success']) {
                return array_filter(explode("\n", trim($result['output'])));
            }
        } catch (Exception $e) {
            Log::warning('Failed to fetch PowerDNS DS records', ['error' => $e->getMessage()]);
        }

        return [];
    }

    protected function fetchBind9DsRecords(DnsZone $zone): array
    {
        $server = $zone->provider->connection_config['server'] ?? 'localhost';
        $keyDir = $zone->provider->connection_config['key_directory'] ?? '/etc/bind/keys';

        try {
            $result = $this->remoteExecution->executeAsRoot($server,
                "cd {$keyDir} && dnssec-dsfromkey -2 K{$zone->name}.*.key"
            );

            if ($result['success']) {
                return array_filter(explode("\n", trim($result['output'])));
            }
        } catch (Exception $e) {
            Log::warning('Failed to fetch BIND9 DS records', ['error' => $e->getMessage()]);
        }

        return [];
    }

    protected function performPowerDnsKeyRollover(DnsZone $zone, array $options): array
    {
        $server = $zone->provider->connection_config['server'] ?? 'localhost';
        $keyType = $options['key_type'] ?? 'zsk'; // zsk or ksk

        $result = $this->remoteExecution->executeAsRoot($server,
            "pdnsutil activate-zone-key {$zone->name} && pdnsutil deactivate-zone-key {$zone->name} old-key-id"
        );

        return [
            'rollover_completed' => $result['success'],
            'key_type' => $keyType,
            'rollover_time' => now(),
            'server' => $server,
        ];
    }

    protected function performBind9KeyRollover(DnsZone $zone, array $options): array
    {
        // BIND9 key rollover implementation
        return [
            'rollover_completed' => false,
            'message' => 'BIND9 key rollover requires manual implementation',
        ];
    }

    protected function performCloudFlareKeyRollover(DnsZone $zone, array $options): array
    {
        // CloudFlare manages key rollover automatically
        return [
            'rollover_completed' => true,
            'managed_by_provider' => true,
            'message' => 'CloudFlare manages key rollover automatically',
        ];
    }

    protected function clearCache(string $zoneName): void
    {
        $patterns = [
            "dnssec:status:{$zoneName}",
            "dnssec:ds_records:{$zoneName}",
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }
}

/**
 * DNSSEC Exception
 */
class DnssecException extends Exception
{
    //
}
