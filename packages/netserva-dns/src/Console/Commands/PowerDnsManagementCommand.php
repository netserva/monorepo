<?php

namespace NetServa\Dns\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Dns\Models\DnsProvider;
use NetServa\Dns\Services\PowerDnsService;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Enhanced PowerDNS Management Command
 *
 * Provides comprehensive PowerDNS management including advanced operations,
 * DNSSEC management, bulk operations, monitoring, and health checks.
 */
class PowerDnsManagementCommand extends Command
{
    protected $signature = 'dns:powerdns-management
                            {action : Action to perform}
                            {provider? : Provider name or ID}
                            {zone? : Zone name for zone-specific actions}
                            {--key-id= : Cryptokey ID for key operations}
                            {--key-type= : Key type (ksk|zsk) for key generation}
                            {--algorithm= : Key algorithm for key generation}
                            {--export-file= : File path for export operations}
                            {--import-file= : File path for import operations}
                            {--since= : Unix timestamp for change monitoring}
                            {--dry-run : Perform dry run without making changes}
                            {--no-cache : Skip cache for statistics}
                            {--format=table : Output format (table|json)}';

    protected $description = 'Advanced PowerDNS management with DNSSEC, monitoring, and bulk operations';

    protected PowerDnsService $powerDnsService;

    public function __construct(PowerDnsService $powerDnsService)
    {
        parent::__construct();
        $this->powerDnsService = $powerDnsService;
    }

    public function handle(): int
    {
        $action = $this->argument('action');
        $availableActions = $this->getAvailableActions();

        if (! in_array($action, array_keys($availableActions))) {
            $this->error("❌ Unknown action: $action");
            $this->newLine();
            $this->info('📋 Available actions:');

            foreach ($availableActions as $actionKey => $description) {
                $this->line("  <comment>$actionKey</comment> - $description");
            }

            return self::FAILURE;
        }

        $provider = $this->getProvider();
        if (! $provider) {
            return self::FAILURE;
        }

        try {
            $methodName = 'handle'.str_replace('-', '', ucwords($action, '-'));

            if (method_exists($this, $methodName)) {
                return $this->$methodName($provider);
            }

            $this->error("❌ Action handler not implemented: $action");

            return self::FAILURE;

        } catch (\Exception $e) {
            $this->error("❌ Command failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function getAvailableActions(): array
    {
        return [
            'health-check' => 'Perform comprehensive health check',
            'advanced-stats' => 'Get enhanced server statistics',
            'dnssec-status' => 'Check DNSSEC status for zone',
            'dnssec-enable' => 'Enable DNSSEC for zone',
            'dnssec-validate' => 'Validate DNSSEC configuration',
            'key-generate' => 'Generate new DNSSEC key',
            'key-activate' => 'Activate DNSSEC key',
            'key-deactivate' => 'Deactivate DNSSEC key',
            'key-delete' => 'Delete DNSSEC key',
            'zone-rectify' => 'Rectify zone (fix DNSSEC signatures)',
            'zone-export' => 'Export zone to BIND format',
            'zone-import' => 'Import zones from files',
            'zone-notify' => 'Notify zone slaves',
            'zone-metadata' => 'Manage zone metadata',
            'zone-changes' => 'Monitor zone changes',
            'server-config' => 'Get server configuration',
            'search-logs' => 'Search PowerDNS logs',
            'bulk-import' => 'Bulk import multiple zones',
        ];
    }

    protected function handleHealthCheck(DnsProvider $provider): int
    {
        $this->info("🏥 Performing health check for PowerDNS provider: {$provider->name}");

        $this->withProgressBar(['Checking connectivity', 'Testing zones', 'Analyzing statistics'], function ($bar) use ($provider, &$result) {
            $result = $this->powerDnsService->healthCheck($provider);
            $bar->advance();
            sleep(1); // Simulate work
            $bar->advance();
            sleep(1); // Simulate work
            $bar->advance();
        });

        $this->newLine(2);

        if (! $result['success']) {
            $this->error('❌ Health check failed');

            return self::FAILURE;
        }

        $health = $result['health'];
        $status = $health['overall_status'];

        // Display overall status
        $statusIcon = match ($status) {
            'healthy' => '✅',
            'warning' => '⚠️',
            'unhealthy' => '❌',
            default => '❓'
        };

        $this->info("$statusIcon Overall Status: ".strtoupper($status));
        $this->newLine();

        // Display individual checks
        $this->info('🔍 Individual Checks:');
        foreach ($health['checks'] as $check => $passed) {
            $icon = $passed ? '✅' : '❌';
            $this->line("  $icon ".str_replace('_', ' ', ucfirst($check)));
        }

        if (isset($health['zone_count'])) {
            $this->line("  📊 Zones accessible: {$health['zone_count']}");
        }

        // Display warnings
        if (! empty($health['warnings'])) {
            $this->newLine();
            $this->warn('⚠️ Warnings:');
            foreach ($health['warnings'] as $warning) {
                $this->line("  • $warning");
            }
        }

        // Display errors
        if (! empty($health['errors'])) {
            $this->newLine();
            $this->error('❌ Errors:');
            foreach ($health['errors'] as $error) {
                $this->line("  • $error");
            }
        }

        return self::SUCCESS;
    }

    protected function handleAdvancedStats(DnsProvider $provider): int
    {
        $useCache = ! $this->option('no-cache');
        $format = $this->option('format');

        $this->info('📊 Retrieving advanced PowerDNS statistics...');

        if (! $useCache) {
            $this->line('🔄 Cache disabled - fetching fresh data');
        }

        $result = $this->powerDnsService->getAdvancedStats($provider, $useCache);

        if (! $result['success']) {
            $this->error('❌ Failed to get statistics');

            return self::FAILURE;
        }

        if ($format === 'json') {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $stats = $result['stats'];
        $this->info('📈 PowerDNS Advanced Statistics');
        $this->line("📅 Retrieved: {$result['timestamp']}");

        if ($result['tunnel_used']) {
            $this->line('🔒 Via SSH tunnel');
        }

        $this->newLine();

        foreach ($stats as $category => $categoryStats) {
            if (empty($categoryStats)) {
                continue;
            }

            $categoryIcon = match ($category) {
                'performance' => '⚡',
                'cache' => '💾',
                'queries' => '🔍',
                'answers' => '📤',
                'security' => '🔒',
                'database' => '🗄️',
                default => '📊'
            };

            $this->info("$categoryIcon ".ucfirst($category).' Statistics:');

            $this->table(
                ['Metric', 'Value'],
                collect($categoryStats)->map(function ($value, $name) {
                    return [ucfirst(str_replace('-', ' ', $name)), number_format($value)];
                })->toArray()
            );

            $this->newLine();
        }

        return self::SUCCESS;
    }

    protected function handleDnssecStatus(DnsProvider $provider): int
    {
        $zoneName = $this->getZoneName();
        if (! $zoneName) {
            return self::FAILURE;
        }

        $this->info("🔐 Checking DNSSEC status for zone: $zoneName");

        $result = $this->powerDnsService->getZoneCryptokeys($provider, $zoneName);

        if (! $result['success']) {
            $this->error("❌ Failed to get DNSSEC status: {$result['message']}");

            return self::FAILURE;
        }

        $keys = $result['cryptokeys'];

        if (empty($keys)) {
            $this->warn("⚠️ DNSSEC is not enabled for zone: $zoneName");
            $this->line('💡 Use dns:powerdns-management dnssec-enable to enable DNSSEC');

            return self::SUCCESS;
        }

        $this->info("✅ DNSSEC is enabled for zone: $zoneName");
        $this->newLine();

        $this->table(
            ['Key ID', 'Type', 'Algorithm', 'Active', 'Published', 'Content'],
            collect($keys)->map(function ($key) {
                return [
                    $key['id'] ?? 'N/A',
                    strtoupper($key['keytype'] ?? 'N/A'),
                    $key['algorithm'] ?? 'N/A',
                    ($key['active'] ?? false) ? '✅' : '❌',
                    ($key['published'] ?? false) ? '✅' : '❌',
                    substr($key['content'] ?? '', 0, 50).'...',
                ];
            })->toArray()
        );

        return self::SUCCESS;
    }

    protected function handleDnssecValidate(DnsProvider $provider): int
    {
        $zoneName = $this->getZoneName();
        if (! $zoneName) {
            return self::FAILURE;
        }

        $this->info("🔍 Validating DNSSEC configuration for zone: $zoneName");

        $result = $this->powerDnsService->validateDnssec($provider, $zoneName);

        if (! $result['success']) {
            $this->error("❌ DNSSEC validation failed: {$result['message']}");

            return self::FAILURE;
        }

        $validation = $result['validation'];

        // Overall status
        if ($validation['dnssec_enabled']) {
            $this->info("✅ DNSSEC is enabled for zone: {$validation['zone']}");
        } else {
            $this->warn("⚠️ DNSSEC is not enabled for zone: {$validation['zone']}");
        }

        $this->newLine();

        // Key summary
        $this->info('📊 Key Summary:');
        $this->line("  🔑 Active keys: {$validation['active_keys']}");
        $this->line("  💤 Inactive keys: {$validation['inactive_keys']}");

        if (! empty($validation['key_types'])) {
            $this->newLine();
            $this->info('🗝️ Key Types:');
            foreach ($validation['key_types'] as $type => $count) {
                $this->line('  '.strtoupper($type).": $count");
            }
        }

        // Issues
        if (! empty($validation['issues'])) {
            $this->newLine();
            $this->error('❌ Issues Found:');
            foreach ($validation['issues'] as $issue) {
                $this->line("  • $issue");
            }
        }

        // Recommendations
        if (! empty($validation['recommendations'])) {
            $this->newLine();
            $this->info('💡 Recommendations:');
            foreach ($validation['recommendations'] as $recommendation) {
                $this->line("  • $recommendation");
            }
        }

        return self::SUCCESS;
    }

    protected function handleKeyGenerate(DnsProvider $provider): int
    {
        $zoneName = $this->getZoneName();
        if (! $zoneName) {
            return self::FAILURE;
        }

        $keyType = $this->option('key-type') ?: select(
            'Select key type:',
            ['ksk' => 'Key Signing Key (KSK)', 'zsk' => 'Zone Signing Key (ZSK)'],
            'ksk'
        );

        $algorithm = $this->option('algorithm') ?: select(
            'Select algorithm:',
            [
                'ecdsa256' => 'ECDSA P-256 (Recommended)',
                'ecdsa384' => 'ECDSA P-384',
                'rsasha256' => 'RSA SHA-256',
                'rsasha512' => 'RSA SHA-512',
            ],
            'ecdsa256'
        );

        $active = confirm('Activate key immediately?', true);

        $this->info("🔑 Generating $keyType key for zone: $zoneName");
        $this->line("🔧 Algorithm: $algorithm");
        $this->line('⚡ Active: '.($active ? 'Yes' : 'No'));

        if (! confirm('Proceed with key generation?')) {
            $this->info('Key generation cancelled');

            return self::SUCCESS;
        }

        $options = [
            'keytype' => $keyType,
            'algorithm' => $algorithm,
            'active' => $active,
        ];

        $result = $this->powerDnsService->generateDnssecKey($provider, $zoneName, $options);

        if ($result['success']) {
            $this->info("✅ {$result['message']}");

            if (isset($result['key']['id'])) {
                $this->line("🆔 Key ID: {$result['key']['id']}");
            }

            return self::SUCCESS;
        }

        $this->error("❌ {$result['message']}");

        return self::FAILURE;
    }

    protected function handleKeyActivate(DnsProvider $provider): int
    {
        return $this->handleKeyToggle($provider, true);
    }

    protected function handleKeyDeactivate(DnsProvider $provider): int
    {
        return $this->handleKeyToggle($provider, false);
    }

    protected function handleKeyToggle(DnsProvider $provider, bool $active): int
    {
        $zoneName = $this->getZoneName();
        if (! $zoneName) {
            return self::FAILURE;
        }

        $keyId = $this->option('key-id');

        if (! $keyId) {
            // Get available keys
            $keysResult = $this->powerDnsService->getZoneCryptokeys($provider, $zoneName);

            if (! $keysResult['success'] || empty($keysResult['cryptokeys'])) {
                $this->error('❌ No DNSSEC keys found for this zone');

                return self::FAILURE;
            }

            $keyOptions = collect($keysResult['cryptokeys'])->mapWithKeys(function ($key) {
                $status = ($key['active'] ?? false) ? 'Active' : 'Inactive';
                $type = strtoupper($key['keytype'] ?? 'Unknown');

                return [$key['id'] => "Key {$key['id']} ($type, $status)"];
            })->toArray();

            $keyId = select('Select key to '.($active ? 'activate' : 'deactivate').':', $keyOptions);
        }

        $action = $active ? 'activate' : 'deactivate';
        $this->info("🔑 Attempting to $action key $keyId for zone: $zoneName");

        $result = $this->powerDnsService->setKeyActive($provider, $zoneName, (int) $keyId, $active);

        if ($result['success']) {
            $this->info("✅ {$result['message']}");

            return self::SUCCESS;
        }

        $this->error("❌ {$result['message']}");

        return self::FAILURE;
    }

    protected function handleZoneExport(DnsProvider $provider): int
    {
        $zoneName = $this->getZoneName();
        if (! $zoneName) {
            return self::FAILURE;
        }

        $exportFile = $this->option('export-file') ?: storage_path("app/dns_exports/{$zoneName}.zone");

        $this->info("📤 Exporting zone: $zoneName");
        $this->line("💾 Export file: $exportFile");

        $result = $this->powerDnsService->exportZone($provider, $zoneName);

        if (! $result['success']) {
            $this->error("❌ Export failed: {$result['message']}");

            return self::FAILURE;
        }

        // Ensure directory exists
        $directory = dirname($exportFile);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Write zone data to file
        file_put_contents($exportFile, $result['zone_data']);

        $this->info('✅ Zone exported successfully');
        $this->line("📄 File: $exportFile");
        $this->line('📊 Size: '.number_format(strlen($result['zone_data'])).' bytes');

        return self::SUCCESS;
    }

    protected function handleBulkImport(DnsProvider $provider): int
    {
        $importPath = $this->option('import-file') ?: text('Enter path to zone files directory:');
        $dryRun = $this->option('dry-run');

        if (! is_dir($importPath)) {
            $this->error("❌ Directory not found: $importPath");

            return self::FAILURE;
        }

        $zoneFiles = glob("$importPath/*.zone");

        if (empty($zoneFiles)) {
            $this->warn("⚠️ No .zone files found in: $importPath");

            return self::SUCCESS;
        }

        $this->info('📥 Found '.count($zoneFiles).' zone files');

        if ($dryRun) {
            $this->line('🔍 Performing dry run - no changes will be made');
        }

        if (! $dryRun && ! confirm('Proceed with bulk import?')) {
            $this->info('Bulk import cancelled');

            return self::SUCCESS;
        }

        // Prepare zone files data
        $zoneData = [];
        foreach ($zoneFiles as $file) {
            $zoneName = basename($file, '.zone');
            $zoneData[$zoneName] = file_get_contents($file);
        }

        $result = $this->powerDnsService->bulkImportZones($provider, $zoneData, $dryRun);

        $this->newLine();
        $this->info('📊 Bulk Import Results:');
        $this->line("✅ Success: {$result['imported']}");
        $this->line("❌ Failed: {$result['failed']}");

        if (! empty($result['details'])) {
            $this->newLine();
            $this->table(
                ['Zone', 'Status', 'Message'],
                collect($result['details'])->map(function ($detail, $zone) {
                    $icon = match ($detail['status']) {
                        'imported', 'valid' => '✅',
                        'error', 'invalid' => '❌',
                        default => '⚠️'
                    };

                    return [$zone, $icon.' '.$detail['status'], $detail['message']];
                })->toArray()
            );
        }

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }

    protected function getProvider(): ?DnsProvider
    {
        $providerInput = $this->argument('provider');

        if (! $providerInput) {
            $providers = DnsProvider::where('provider', 'powerdns')
                ->where('is_active', true)
                ->get();

            if ($providers->isEmpty()) {
                $this->error('❌ No active PowerDNS providers found');

                return null;
            }

            if ($providers->count() === 1) {
                return $providers->first();
            }

            $options = $providers->mapWithKeys(function ($provider) {
                return [$provider->id => "{$provider->name} (ID: {$provider->id})"];
            })->toArray();

            $providerId = select('Select PowerDNS provider:', $options);

            return $providers->find($providerId);
        }

        $provider = DnsProvider::where('provider', 'powerdns')
            ->where('is_active', true)
            ->where(function ($query) use ($providerInput) {
                $query->where('id', $providerInput)
                    ->orWhere('name', $providerInput);
            })
            ->first();

        if (! $provider) {
            $this->error("❌ PowerDNS provider not found: $providerInput");

            return null;
        }

        return $provider;
    }

    protected function getZoneName(): ?string
    {
        $zoneName = $this->argument('zone');

        if (! $zoneName) {
            $zoneName = text('Enter zone name:');
        }

        if (empty($zoneName)) {
            $this->error('❌ Zone name is required');

            return null;
        }

        return $zoneName;
    }
}
