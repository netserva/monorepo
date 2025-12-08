<?php

namespace App\Models;

use App\Services\SynergyWholesaleService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Synergy Wholesale Domain Cache Model
 *
 * Local cache of domain data from Synergy Wholesale API
 * to avoid constant API calls for console/Filament access.
 */
class SwDomain extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_name',
        'domain_roid',
        'registry_id',
        'domain_status',
        'lifecycle_status',
        'domain_expiry',
        'domain_registered',
        'created_date',
        'registration_period_years',
        'registrant',
        'domain_password',
        'nameservers',
        'ds_data',
        'dns_config_type',
        'categories',
        'dns_management_enabled',
        'email_forwarding_enabled',
        'id_protection_enabled',
        'is_premium',
        'auto_renew',
        'do_not_renew',
        'id_protect',
        'bulk_in_progress',
        'icann_verification_date_end',
        'icann_status',
        'contacts',
        'raw_response',
        'is_active',
        'is_synced',
        'error_message',
        'last_synced_at',
        'grace_period_days',
        'grace_period_fee',
        'redemption_period_days',
        'redemption_period_fee',
        'customer_id',
    ];

    protected $casts = [
        'nameservers' => 'array',
        'ds_data' => 'array',
        'categories' => 'array',
        'contacts' => 'array',
        'raw_response' => 'array',
        'auto_renew' => 'boolean',
        'do_not_renew' => 'boolean',
        'id_protect' => 'boolean',
        'bulk_in_progress' => 'boolean',
        'dns_management_enabled' => 'boolean',
        'email_forwarding_enabled' => 'boolean',
        'id_protection_enabled' => 'boolean',
        'is_premium' => 'boolean',
        'is_active' => 'boolean',
        'is_synced' => 'boolean',
        'domain_expiry' => 'datetime',
        'domain_registered' => 'datetime',
        'created_date' => 'datetime',
        'icann_verification_date_end' => 'datetime',
        'last_synced_at' => 'datetime',
        'grace_period_fee' => 'decimal:2',
        'redemption_period_fee' => 'decimal:2',
    ];

    /**
     * Sync a single domain from SW API
     */
    public static function syncFromAPI(string $domainName, SynergyWholesaleService $sw): self
    {
        try {
            $data = $sw->getDomainInfo($domainName);

            // Check for API errors
            if (isset($data['status']) && str_starts_with($data['status'], 'ERR_')) {
                // Categorize the error into lifecycle status
                $lifecycleStatus = static::categorizeErrorToLifecycle($data['status'], $data['errorMessage'] ?? '');

                return static::updateOrCreate(
                    ['domain_name' => $domainName],
                    [
                        'domain_status' => static::categorizeErrorStatus($data['status'], $data['errorMessage'] ?? ''),
                        'lifecycle_status' => $lifecycleStatus,
                        'is_active' => false,
                        'is_synced' => true,
                        'error_message' => $data['errorMessage'] ?? $data['status'],
                        'last_synced_at' => now(),
                    ]
                );
            }

            // Extract nameservers
            $nameservers = [];
            if (isset($data['nameServers'])) {
                $nsArray = is_array($data['nameServers']) ? $data['nameServers'] : [$data['nameServers']];
                $nameservers = array_map(
                    fn ($ns) => is_object($ns) ? $ns->nameServer : $ns,
                    $nsArray
                );
            }

            // Map DNS config type to feature flags
            $dnsConfigType = $data['dnsConfig'] ?? 0;
            $dnsManagement = in_array($dnsConfigType, [2, 4]); // 2=DNS+Email+URL, 4=DNS only
            $emailForwarding = $dnsConfigType == 2; // Only type 2 has email forwarding

            // Determine lifecycle status from domain_status
            $lifecycleStatus = static::mapDomainStatusToLifecycle($data['domain_status'] ?? null);

            // Parse categories
            $categories = [];
            if (isset($data['categories']) && ! empty($data['categories'])) {
                $categories = is_array($data['categories']) ? $data['categories'] : [$data['categories']];
            }

            // Parse DNSSEC data
            $dsData = [];
            if (isset($data['DSData']) && ! empty($data['DSData'])) {
                $dsData = is_array($data['DSData']) ? $data['DSData'] : [$data['DSData']];
            }

            // Helper function to parse dates safely
            $parseDate = function ($value) {
                if (empty($value) || $value === 'N/A' || $value === 'null') {
                    return null;
                }
                try {
                    return Carbon::parse($value);
                } catch (\Exception $e) {
                    return null;
                }
            };

            $domain = static::updateOrCreate(
                ['domain_name' => $domainName],
                [
                    'domain_roid' => $data['domainRoid'] ?? null,
                    'registry_id' => $data['registryID'] ?? null,
                    'domain_status' => $data['domain_status'] ?? null,
                    'lifecycle_status' => $lifecycleStatus,
                    'domain_expiry' => $parseDate($data['domain_expiry'] ?? null),
                    'domain_registered' => $parseDate($data['domain_registered'] ?? null),
                    'created_date' => $parseDate($data['createdDate'] ?? null),
                    'registrant' => $data['registrant'] ?? null,
                    'domain_password' => ($data['domainPassword'] ?? null) === 'N/A' ? null : ($data['domainPassword'] ?? null),
                    'nameservers' => $nameservers,
                    'ds_data' => $dsData,
                    'dns_config_type' => $dnsConfigType,
                    'categories' => $categories,
                    'dns_management_enabled' => $dnsManagement,
                    'email_forwarding_enabled' => $emailForwarding,
                    'id_protection_enabled' => $data['idProtect'] ?? false,
                    'auto_renew' => $data['autoRenew'] ?? false,
                    'id_protect' => $data['idProtect'] ?? false,
                    'bulk_in_progress' => $data['bulkInProgress'] ?? false,
                    'icann_verification_date_end' => $parseDate($data['icannVerificationDateEnd'] ?? null),
                    'icann_status' => $data['icannStatus'] ?? null,
                    'contacts' => [
                        'registrant' => $data['contacts']['registrant'] ?? null,
                        'tech' => $data['contacts']['tech'] ?? null,
                        'admin' => $data['contacts']['admin'] ?? null,
                        'billing' => $data['contacts']['billing'] ?? null,
                    ],
                    'raw_response' => $data,
                    'is_active' => true,
                    'is_synced' => true,
                    'error_message' => null,
                    'last_synced_at' => now(),
                ]
            );

            // Sync glue records for this domain
            $domain->syncGlueRecordsFromAPI($sw);

            return $domain;

        } catch (\Exception $e) {
            return static::updateOrCreate(
                ['domain_name' => $domainName],
                [
                    'is_active' => false,
                    'error_message' => $e->getMessage(),
                    'last_synced_at' => now(),
                ]
            );
        }
    }

    /**
     * Sync all domains from SW API
     *
     * Only syncs active domains (clientTransferProhibited status) from the API.
     * This filters at the API level to avoid fetching 150+ transferred_away domains.
     */
    public static function syncAllFromAPI(SynergyWholesaleService $sw, ?callable $progressCallback = null): array
    {
        // Use filtered API call - only fetch active domains
        $listResult = $sw->listActiveDomains();

        if (! isset($listResult['domainList'])) {
            throw new \RuntimeException('Unable to fetch domain list from Synergy Wholesale');
        }

        $domains = is_array($listResult['domainList']) ? $listResult['domainList'] : [$listResult['domainList']];
        $total = count($domains);
        $synced = 0;
        $errors = 0;

        foreach ($domains as $index => $domain) {
            $domainName = is_object($domain) ? $domain->domainName : ($domain['domainName'] ?? $domain);

            $result = static::syncFromAPI($domainName, $sw);

            if ($result->is_active) {
                $synced++;
            } else {
                $errors++;
            }

            if ($progressCallback) {
                $progressCallback($index + 1, $total, $result);
            }
        }

        return [
            'total' => $total,
            'synced' => $synced,
            'errors' => $errors,
        ];
    }

    /**
     * Get active domains (legacy - uses is_active boolean)
     */
    public static function active()
    {
        return static::where('is_active', true);
    }

    /**
     * Get inactive/error domains (legacy - uses is_active boolean)
     */
    public static function inactive()
    {
        return static::where('is_active', false);
    }

    /**
     * Get domains by lifecycle status
     */
    public static function byLifecycleStatus(string $status)
    {
        return static::where('lifecycle_status', $status);
    }

    /**
     * Get truly active domains (lifecycle status = active)
     */
    public static function lifecycleActive()
    {
        return static::where('lifecycle_status', 'active');
    }

    /**
     * Get transferred away domains
     */
    public static function transferredAway()
    {
        return static::where('lifecycle_status', 'transferred_away');
    }

    /**
     * Get domains in grace period
     */
    public static function inGracePeriod()
    {
        return static::where('lifecycle_status', 'grace');
    }

    /**
     * Get domains in redemption period
     */
    public static function inRedemptionPeriod()
    {
        return static::where('lifecycle_status', 'redemption');
    }

    /**
     * Get expired domains
     */
    public static function expired()
    {
        return static::where('lifecycle_status', 'expired');
    }

    /**
     * Get pending domains (any pending state)
     */
    public static function pending()
    {
        return static::whereIn('lifecycle_status', [
            'pending',
            'pending_registration',
            'pending_transfer',
        ]);
    }

    /**
     * Get domains expiring soon
     */
    public static function expiringSoon(int $days = 30)
    {
        return static::lifecycleActive()
            ->where('domain_expiry', '<=', now()->addDays($days))
            ->where('domain_expiry', '>=', now());
    }

    /**
     * Get domains using specific nameservers
     */
    public static function usingNameservers(string $pattern)
    {
        return static::lifecycleActive()
            ->where('nameservers', 'like', "%{$pattern}%");
    }

    /**
     * Format nameservers for display
     */
    public function getNameserversDisplayAttribute(): string
    {
        if (empty($this->nameservers)) {
            return 'N/A';
        }

        return implode(', ', array_slice($this->nameservers, 0, 2)).
               (count($this->nameservers) > 2 ? '...' : '');
    }

    /**
     * Get days until expiry
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (! $this->domain_expiry) {
            return null;
        }

        return now()->diffInDays($this->domain_expiry, false);
    }

    /**
     * Check if domain is expiring soon
     */
    public function isExpiringSoon(int $days = 30): bool
    {
        $daysUntil = $this->days_until_expiry;

        return $daysUntil !== null && $daysUntil >= 0 && $daysUntil <= $days;
    }

    /**
     * Categorize error status from API error messages
     *
     * Maps generic API error codes to human-readable statuses
     * to match what the Synergy Wholesale web interface displays.
     */
    private static function categorizeErrorStatus(string $status, string $errorMessage): string
    {
        return match (true) {
            // Domain transferred to another registrar
            str_contains($errorMessage, 'Does Not Exist') => 'Transferred Away',

            // Domain expired
            str_contains($errorMessage, 'Expired') => 'Expired',

            // Domain suspended by registrar
            str_contains($errorMessage, 'Suspended') => 'Suspended',

            // Domain is in transfer process
            str_contains($errorMessage, 'Pending') => 'Pending Transfer',

            // Domain deleted/dropped
            str_contains($errorMessage, 'Deleted') => 'Deleted',

            // Generic access/permission issues
            str_contains($status, 'ERR_DOMAININFO_FAILED') => 'Unavailable',

            // Fallback for any other error
            default => 'Inactive',
        };
    }

    /**
     * Map API error to lifecycle status enum
     */
    private static function categorizeErrorToLifecycle(string $status, string $errorMessage): string
    {
        return match (true) {
            str_contains($errorMessage, 'Does Not Exist') => 'transferred_away',
            str_contains($errorMessage, 'Expired') => 'expired',
            str_contains($errorMessage, 'Pending') => 'pending_transfer',
            default => 'cancelled',
        };
    }

    /**
     * Map SW domain_status field to lifecycle status enum
     *
     * SW uses values like:
     * - clientTransferProhibited (normal active)
     * - pendingCreate, pendingTransfer, etc.
     * - redemptionPeriod
     */
    private static function mapDomainStatusToLifecycle(?string $domainStatus): string
    {
        if (empty($domainStatus)) {
            return 'active';
        }

        return match (true) {
            // Active/locked states
            str_contains($domainStatus, 'clientTransferProhibited') => 'active',
            str_contains($domainStatus, 'serverTransferProhibited') => 'active',
            str_contains($domainStatus, 'ok') => 'active',

            // Pending states
            str_contains($domainStatus, 'pendingCreate') => 'pending_registration',
            str_contains($domainStatus, 'pendingTransfer') => 'pending_transfer',
            str_contains($domainStatus, 'pending') => 'pending',

            // Redemption period
            str_contains($domainStatus, 'redemptionPeriod') => 'redemption',

            // Grace period (addGracePeriod, autoRenewGracePeriod, etc.)
            str_contains($domainStatus, 'GracePeriod') => 'grace',

            // Default to active
            default => 'active',
        };
    }

    /**
     * =================================================================
     * RELATIONSHIPS (inspired by WHMCS schema patterns)
     * =================================================================
     */

    /**
     * Get domain metadata (key-value pairs)
     */
    public function metadata(): HasMany
    {
        return $this->hasMany(DomainMetadata::class, 'sw_domain_id');
    }

    /**
     * Get glue records for this domain
     */
    public function glueRecords(): HasMany
    {
        return $this->hasMany(GlueRecord::class, 'sw_domain_id');
    }

    /**
     * Get additional fields (for .au domains)
     */
    public function additionalFields(): HasMany
    {
        return $this->hasMany(DomainAdditionalField::class, 'sw_domain_id');
    }

    /**
     * =================================================================
     * METADATA HELPERS (inspired by WHMCS tbldomains_extra)
     * =================================================================
     */

    /**
     * Get specific metadata value
     */
    public function getMeta(string $key, $default = null)
    {
        $meta = $this->metadata()->where('key', $key)->first();

        return $meta ? $meta->value : $default;
    }

    /**
     * Set specific metadata value
     */
    public function setMeta(string $key, $value): void
    {
        $this->metadata()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Delete specific metadata key
     */
    public function deleteMeta(string $key): void
    {
        $this->metadata()->where('key', $key)->delete();
    }

    /**
     * =================================================================
     * GLUE RECORD HELPERS
     * =================================================================
     */

    /**
     * Check if domain has active glue records
     */
    public function hasGlueRecords(): bool
    {
        return $this->glueRecords()->count() > 0;
    }

    /**
     * Sync glue records from API
     *
     * Note: Stale records (is_stale=true) are preserved but not updated.
     * These represent glue records that exist in SW's web UI but not in registry.
     * User must delete these manually via SW web interface.
     */
    public function syncGlueRecordsFromAPI(SynergyWholesaleService $sw): int
    {
        try {
            $result = $sw->listAllHosts($this->domain_name);

            if (! isset($result['hosts'])) {
                // No hosts returned - delete all non-stale glue records
                $this->glueRecords()->where('is_stale', false)->delete();

                return 0;
            }

            $hosts = is_array($result['hosts']) ? $result['hosts'] : [$result['hosts']];
            $synced = 0;
            $syncedHostnames = [];

            foreach ($hosts as $host) {
                $hostname = is_object($host) ? $host->hostName : $host['hostName'];
                $ips = is_object($host) ? (array) $host->ip : ($host['ip'] ?? []);

                // Check if this glue record is marked as stale
                $existingGlue = $this->glueRecords()->where('hostname', $hostname)->first();

                if ($existingGlue && $existingGlue->is_stale) {
                    // Preserve stale glue records but don't update them
                    // User should delete these via SW web interface
                    $syncedHostnames[] = $hostname;

                    continue;
                }

                $this->glueRecords()->updateOrCreate(
                    ['hostname' => $hostname],
                    [
                        'ip_addresses' => $ips,
                        'is_synced' => true,
                        'is_stale' => false,
                        'last_synced_at' => now(),
                        'sync_error' => null,
                    ]
                );

                $syncedHostnames[] = $hostname;
                $synced++;
            }

            // Delete glue records that are no longer in the API response (except stale ones)
            $this->glueRecords()
                ->where('is_stale', false)
                ->whereNotIn('hostname', $syncedHostnames)
                ->delete();

            return $synced;
        } catch (\Exception $e) {
            // Log error but don't fail
            return 0;
        }
    }

    /**
     * =================================================================
     * .AU DOMAIN HELPERS
     * =================================================================
     */

    /**
     * Check if this is an Australian (.au) domain
     */
    public function isAuDomain(): bool
    {
        return str_ends_with($this->domain_name, '.au');
    }

    /**
     * Get .au eligibility type
     */
    public function getAuEligibilityType(): ?string
    {
        return $this->additionalFields()
            ->where('field_name', DomainAdditionalField::AU_ELIGIBILITY_TYPE)
            ->value('field_value');
    }

    /**
     * Set .au eligibility fields
     */
    public function setAuEligibilityFields(array $fields): void
    {
        foreach ($fields as $fieldName => $fieldValue) {
            $this->additionalFields()->updateOrCreate(
                ['field_name' => $fieldName],
                ['field_value' => $fieldValue]
            );
        }
    }
}
