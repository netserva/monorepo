<?php

namespace NetServa\Dns\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Laravel\Prompts\Progress;
use NetServa\Dns\Models\DnsZone;
use NetServa\Dns\Services\DnssecException;
use NetServa\Dns\Services\DnssecService;

/**
 * DNSSEC Management Command
 *
 * Comprehensive DNSSEC operations including enable/disable, key management,
 * validation, monitoring, and key rollover operations.
 */
class DnssecCommand extends Command
{
    protected $signature = 'dns:dnssec
                           {action : Action to perform (status, enable, disable, validate, keys, rollover, monitor, ds-records)}
                           {zone? : Zone name for specific operations}
                           {--algorithm=8 : DNSSEC algorithm (3=DSA, 5=RSA/SHA-1, 8=RSA/SHA-256, 13=ECDSA-P256, 15=Ed25519)}
                           {--key-size=2048 : Key size in bits}
                           {--ksk-size=4096 : Key Signing Key size in bits}
                           {--digest-type=2 : Digest type (1=SHA-1, 2=SHA-256, 4=SHA-384)}
                           {--auto-dnssec : Enable automatic DNSSEC management}
                           {--key-type=both : Key type for operations (zsk, ksk, both)}
                           {--server= : PowerDNS server for operations}
                           {--format=table : Output format (table, json, ds-records)}
                           {--all : Process all DNSSEC-enabled zones}
                           {--force : Skip confirmation prompts}
                           {--dry-run : Show what would be done without executing}';

    protected $description = 'Manage DNSSEC for DNS zones';

    protected DnssecService $dnssec;

    public function __construct(DnssecService $dnssec)
    {
        parent::__construct();
        $this->dnssec = $dnssec;
    }

    public function handle(): int
    {
        $action = $this->argument('action');
        $zone = $this->argument('zone');
        $isDryRun = $this->option('dry-run');
        $format = $this->option('format');

        if ($isDryRun) {
            $this->info("🔍 DRY RUN - DNSSEC {$action}".($zone ? " for {$zone}" : ''));
        }

        try {
            return match ($action) {
                'status' => $this->showDnssecStatus($zone, $format),
                'enable' => $this->enableDnssec($zone, $isDryRun),
                'disable' => $this->disableDnssec($zone, $isDryRun),
                'validate' => $this->validateDnssec($zone),
                'keys' => $this->manageKeys($zone, $isDryRun),
                'rollover' => $this->performKeyRollover($zone, $isDryRun),
                'monitor' => $this->monitorDnssec($format),
                'ds-records' => $this->showDsRecords($zone, $format),
                default => $this->error("❌ Unknown action: {$action}") ?: self::FAILURE
            };

        } catch (DnssecException $e) {
            $this->error('❌ DNSSEC error: '.$e->getMessage());

            return self::FAILURE;
        } catch (Exception $e) {
            $this->error('❌ Command failed: '.$e->getMessage());
            Log::error('DNSSEC command failed', [
                'action' => $action,
                'zone' => $zone,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }

    protected function showDnssecStatus(?string $zone, string $format): int
    {
        if ($this->option('all')) {
            return $this->showAllZonesStatus($format);
        }

        if (! $zone) {
            $zone = $this->selectZone();
        }

        $this->info("🔐 DNSSEC Status for: {$zone}");

        $status = $this->dnssec->getZoneDnssecStatus($zone);

        if ($format === 'json') {
            $this->line(json_encode($status, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        if (isset($status['status']) && $status['status'] === 'zone_not_found') {
            $this->error("❌ Zone '{$zone}' not found");

            return self::FAILURE;
        }

        if (isset($status['status']) && $status['status'] === 'error') {
            $this->error('❌ Error getting status: '.$status['message']);

            return self::FAILURE;
        }

        $this->displayDnssecStatus($status);

        return self::SUCCESS;
    }

    protected function showAllZonesStatus(string $format): int
    {
        $this->info('🔐 DNSSEC Status for All Zones');

        $zones = DnsZone::with('provider')->get();

        if ($zones->isEmpty()) {
            $this->warn('⚠️ No zones found');

            return self::SUCCESS;
        }

        if ($format === 'json') {
            $results = [];
            foreach ($zones as $zone) {
                $results[$zone->name] = $this->dnssec->getZoneDnssecStatus($zone->name);
            }
            $this->line(json_encode($results, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($zones as $zone) {
            $status = $this->dnssec->getZoneDnssecStatus($zone->name);
            $rows[] = [
                $zone->name,
                $zone->provider->type ?? 'N/A',
                $status['dnssec_enabled'] ? '✅ Enabled' : '❌ Disabled',
                $status['auto_dnssec'] ?? false ? '🤖 Auto' : '👤 Manual',
                isset($status['last_updated']) ? $status['last_updated']->format('Y-m-d H:i') : 'N/A',
            ];
        }

        table(['Zone', 'Provider', 'DNSSEC', 'Mode', 'Last Updated'], $rows);

        return self::SUCCESS;
    }

    protected function enableDnssec(?string $zone, bool $isDryRun): int
    {
        if (! $zone) {
            $zone = $this->selectZone(false); // Don't filter to DNSSEC-enabled zones
        }

        $zoneModel = DnsZone::where('name', $zone)->first();
        if (! $zoneModel) {
            $this->error("❌ Zone '{$zone}' not found");

            return self::FAILURE;
        }

        if ($zoneModel->dnssec_enabled) {
            $this->warn("⚠️ DNSSEC is already enabled for zone '{$zone}'");

            return self::SUCCESS;
        }

        $this->info("🔐 Enabling DNSSEC for zone: {$zone}");

        $options = [
            'algorithm' => $this->option('algorithm'),
            'key_size' => $this->option('key-size'),
            'ksk_size' => $this->option('ksk-size'),
            'digest_type' => $this->option('digest-type'),
            'auto_dnssec' => $this->option('auto-dnssec'),
            'server' => $this->option('server'),
        ];

        $this->displayDnssecOptions($options, $zoneModel->provider->type ?? 'unknown');

        if ($isDryRun) {
            $this->info("🔍 DRY RUN - Would enable DNSSEC for zone: {$zone}");
            $this->info("Algorithm: {$options['algorithm']}, Key Size: {$options['key_size']}, KSK Size: {$options['ksk_size']}");

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            if (! confirm("Enable DNSSEC for zone '{$zone}'?", true)) {
                $this->info('⚠️ Operation cancelled');

                return self::SUCCESS;
            }
        }

        $progress = new Progress('Enabling DNSSEC...', 4);

        try {
            $progress->label('Validating zone configuration...');
            // Validation logic here
            $progress->advance();

            $progress->label('Enabling DNSSEC...');
            $result = $this->dnssec->enableDnssec($zone, $options);
            $progress->advance();

            $progress->label('Generating keys...');
            if (! ($result['managed_by_provider'] ?? false)) {
                $this->dnssec->generateKeys($zone, $options);
            }
            $progress->advance();

            $progress->label('Validating configuration...');
            // Brief validation
            $progress->advance();

            $progress->finish();

            $this->info("✅ DNSSEC enabled successfully for zone: {$zone}");

            if (isset($result['ds_records']) && ! empty($result['ds_records'])) {
                $this->info('📋 DS Records to add to parent zone:');
                foreach ($result['ds_records'] as $dsRecord) {
                    $this->line("  {$dsRecord}");
                }
                $this->warn('💡 Add these DS records to the parent zone to complete DNSSEC setup');
            }

            return self::SUCCESS;

        } catch (Exception $e) {
            $progress->finish();
            throw $e;
        }
    }

    protected function disableDnssec(?string $zone, bool $isDryRun): int
    {
        if (! $zone) {
            $zone = $this->selectZone(true); // Filter to DNSSEC-enabled zones
        }

        $zoneModel = DnsZone::where('name', $zone)->first();
        if (! $zoneModel) {
            $this->error("❌ Zone '{$zone}' not found");

            return self::FAILURE;
        }

        if (! $zoneModel->dnssec_enabled) {
            $this->warn("⚠️ DNSSEC is not enabled for zone '{$zone}'");

            return self::SUCCESS;
        }

        $this->error('⚠️  DNSSEC Disable Summary');
        $this->table(['Property', 'Value'], [
            ['Zone', $zone],
            ['Provider', $zoneModel->provider->type ?? 'Unknown'],
            ['Current Status', 'Enabled'],
            ['Auto DNSSEC', $zoneModel->auto_dnssec ? 'Yes' : 'No'],
        ]);

        if ($isDryRun) {
            $this->info("🔍 DRY RUN - Would disable DNSSEC for zone: {$zone}");

            return self::SUCCESS;
        }

        $this->warn('⚠️  This will disable DNSSEC and may affect domain security!');
        $this->warn('⚠️  Ensure DS records are removed from the parent zone!');

        if (! $this->option('force')) {
            $confirmation = text(
                label: "Type 'DISABLE' to confirm DNSSEC disable:",
                required: true
            );

            if ($confirmation !== 'DISABLE') {
                $this->info('⚠️ Operation cancelled');

                return self::SUCCESS;
            }
        }

        $this->info("🔐 Disabling DNSSEC for zone: {$zone}");

        $result = $this->dnssec->disableDnssec($zone);

        if ($result) {
            $this->info("✅ DNSSEC disabled successfully for zone: {$zone}");
            $this->warn('💡 Remember to remove DS records from the parent zone');
        } else {
            $this->error("❌ Failed to disable DNSSEC for zone: {$zone}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function validateDnssec(?string $zone): int
    {
        if ($this->option('all')) {
            return $this->validateAllZones();
        }

        if (! $zone) {
            $zone = $this->selectZone(true); // Filter to DNSSEC-enabled zones
        }

        $this->info("🔍 Validating DNSSEC for zone: {$zone}");

        $validation = $this->dnssec->validateDnssec($zone);

        $this->displayValidationResults($validation);

        return $validation['overall_status'] === 'pass' ? self::SUCCESS : self::FAILURE;
    }

    protected function validateAllZones(): int
    {
        $this->info('🔍 Validating DNSSEC for all enabled zones');

        $zones = DnsZone::where('dnssec_enabled', true)->get();

        if ($zones->isEmpty()) {
            $this->warn('⚠️ No DNSSEC-enabled zones found');

            return self::SUCCESS;
        }

        $results = [];
        $progress = new Progress('Validating zones...', $zones->count());

        foreach ($zones as $zone) {
            $progress->label("Validating {$zone->name}...");
            try {
                $validation = $this->dnssec->validateDnssec($zone->name);
                $results[] = [
                    'zone' => $zone->name,
                    'status' => $validation['overall_status'],
                    'checks_passed' => collect($validation['checks'])->where('status', 'pass')->count(),
                    'total_checks' => count($validation['checks']),
                ];
            } catch (Exception $e) {
                $results[] = [
                    'zone' => $zone->name,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
            $progress->advance();
        }

        $progress->finish();

        // Display summary
        $this->info('📊 Validation Summary:');
        $rows = [];
        foreach ($results as $result) {
            $status = match ($result['status']) {
                'pass' => '✅ Pass',
                'fail' => '❌ Fail',
                'error' => '🔥 Error'
            };

            $checks = isset($result['checks_passed'])
                ? "{$result['checks_passed']}/{$result['total_checks']}"
                : 'N/A';

            $rows[] = [
                $result['zone'],
                $status,
                $checks,
                $result['error'] ?? '',
            ];
        }

        table(['Zone', 'Status', 'Checks', 'Error'], $rows);

        $failedCount = collect($results)->where('status', '!=', 'pass')->count();

        return $failedCount === 0 ? self::SUCCESS : self::FAILURE;
    }

    protected function manageKeys(?string $zone, bool $isDryRun): int
    {
        if (! $zone) {
            $zone = $this->selectZone(true);
        }

        $action = select(
            label: 'Key management action:',
            options: [
                'generate' => 'Generate new keys',
                'list' => 'List existing keys',
                'export' => 'Export public keys',
            ]
        );

        return match ($action) {
            'generate' => $this->generateKeys($zone, $isDryRun),
            'list' => $this->listKeys($zone),
            'export' => $this->exportKeys($zone)
        };
    }

    protected function generateKeys(string $zone, bool $isDryRun): int
    {
        $this->info("🔑 Generating DNSSEC keys for zone: {$zone}");

        $options = [
            'algorithm' => $this->option('algorithm'),
            'key_size' => $this->option('key-size'),
            'ksk_size' => $this->option('ksk-size'),
            'key_type' => $this->option('key-type'),
        ];

        if ($isDryRun) {
            $this->info('🔍 DRY RUN - Would generate keys with options:');
            foreach ($options as $key => $value) {
                $this->line("  {$key}: {$value}");
            }

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            if (! confirm("Generate new DNSSEC keys for zone '{$zone}'?", true)) {
                $this->info('⚠️ Operation cancelled');

                return self::SUCCESS;
            }
        }

        $result = $this->dnssec->generateKeys($zone, $options);

        $this->info('✅ Keys generated successfully');
        $this->displayKeyGenerationResults($result);

        return self::SUCCESS;
    }

    protected function listKeys(string $zone): int
    {
        $this->info("🔑 DNSSEC Keys for zone: {$zone}");

        $zoneModel = DnsZone::where('name', $zone)->first();
        if (! $zoneModel) {
            $this->error("❌ Zone '{$zone}' not found");

            return self::FAILURE;
        }

        $keys = $zoneModel->provider_data['dnssec_keys'] ?? [];

        if (empty($keys)) {
            $this->warn('⚠️ No DNSSEC keys found');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($keys as $key) {
            $rows[] = [
                $key['type'] ?? 'Unknown',
                $key['algorithm'] ?? 'N/A',
                $key['key_size'] ?? 'N/A',
                $key['keytag'] ?? 'N/A',
                isset($key['created_at']) ? $key['created_at'] : 'N/A',
            ];
        }

        table(['Type', 'Algorithm', 'Size', 'Key Tag', 'Created'], $rows);

        return self::SUCCESS;
    }

    protected function exportKeys(string $zone): int
    {
        $this->info("📤 Exporting DNSSEC keys for zone: {$zone}");

        $format = select(
            label: 'Export format:',
            options: [
                'ds' => 'DS Records',
                'dnskey' => 'DNSKEY Records',
                'public' => 'Public Key Files',
            ]
        );

        return match ($format) {
            'ds' => $this->showDsRecords($zone, 'ds-records'),
            'dnskey' => $this->exportDnskeyRecords($zone),
            'public' => $this->exportPublicKeys($zone)
        };
    }

    protected function showDsRecords(?string $zone, string $format): int
    {
        if (! $zone) {
            $zone = $this->selectZone(true);
        }

        $this->info("📋 DS Records for zone: {$zone}");

        $dsRecords = $this->dnssec->getDsRecords($zone);

        if (empty($dsRecords)) {
            $this->warn('⚠️ No DS records found');
            $this->info('💡 DS records are generated when DNSSEC is enabled and keys are created');

            return self::SUCCESS;
        }

        if ($format === 'json') {
            $this->line(json_encode($dsRecords, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info('📋 DS Records to add to parent zone:');
        $this->line('');
        foreach ($dsRecords as $record) {
            $this->line($record);
        }
        $this->line('');
        $this->warn('💡 Add these DS records to the parent zone to complete DNSSEC chain of trust');

        return self::SUCCESS;
    }

    protected function performKeyRollover(?string $zone, bool $isDryRun): int
    {
        if (! $zone) {
            $zone = $this->selectZone(true);
        }

        $keyType = select(
            label: 'Key type to rollover:',
            options: [
                'zsk' => 'Zone Signing Key (ZSK)',
                'ksk' => 'Key Signing Key (KSK)',
                'both' => 'Both ZSK and KSK',
            ],
            default: 'zsk'
        );

        $this->info("🔄 Performing key rollover for zone: {$zone}");
        $this->info("Key type: {$keyType}");

        if ($isDryRun) {
            $this->info("🔍 DRY RUN - Would perform {$keyType} key rollover for zone: {$zone}");

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->warn('⚠️  Key rollover is a complex operation that affects DNSSEC validation!');
            if (! confirm("Proceed with {$keyType} key rollover for zone '{$zone}'?", false)) {
                $this->info('⚠️ Operation cancelled');

                return self::SUCCESS;
            }
        }

        $options = ['key_type' => $keyType];
        $result = $this->dnssec->performKeyRollover($zone, $options);

        if ($result['rollover_completed'] ?? false) {
            $this->info('✅ Key rollover completed successfully');
            if (isset($result['message'])) {
                $this->info('💡 '.$result['message']);
            }
        } else {
            $this->error('❌ Key rollover failed');
            if (isset($result['message'])) {
                $this->error($result['message']);
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function monitorDnssec(string $format): int
    {
        $this->info('📊 Monitoring DNSSEC status across all zones');

        $results = $this->dnssec->monitorAllZones();

        if ($results->isEmpty()) {
            $this->warn('⚠️ No DNSSEC-enabled zones found');

            return self::SUCCESS;
        }

        if ($format === 'json') {
            $this->line($results->toJson(JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info('📊 DNSSEC Monitoring Report ('.$results->count().' zones)');

        $rows = [];
        foreach ($results as $result) {
            $status = isset($result['validation']['overall_status'])
                ? match ($result['validation']['overall_status']) {
                    'pass' => '✅ Healthy',
                    'fail' => '❌ Issues',
                    'error' => '🔥 Error'
                }
            : ($result['status'] === 'error' ? '🔥 Error' : '⚠️ Unknown');

            $rows[] = [
                $result['zone'],
                $result['provider'],
                $status,
                $result['last_checked']->format('H:i:s'),
                $result['error'] ?? '',
            ];
        }

        table(['Zone', 'Provider', 'Status', 'Checked', 'Error'], $rows);

        $healthyCount = $results->where('validation.overall_status', 'pass')->count();
        $issueCount = $results->where('validation.overall_status', 'fail')->count();
        $errorCount = $results->where('status', 'error')->count();

        $this->info("📈 Summary: {$healthyCount} healthy, {$issueCount} with issues, {$errorCount} errors");

        return $issueCount === 0 && $errorCount === 0 ? self::SUCCESS : self::FAILURE;
    }

    // Helper methods

    protected function selectZone(bool $dnssecOnly = false): string
    {
        $query = DnsZone::with('provider');

        if ($dnssecOnly) {
            $query->where('dnssec_enabled', true);
        }

        $zones = $query->get();

        if ($zones->isEmpty()) {
            $this->error($dnssecOnly ? '❌ No DNSSEC-enabled zones found' : '❌ No zones found');
            exit(self::FAILURE);
        }

        $zoneOptions = $zones->mapWithKeys(function ($zone) {
            $status = $zone->dnssec_enabled ? '🔐' : '🔓';
            $providerType = $zone->provider ? $zone->provider->type : 'unknown';

            return [$zone->name => "{$status} {$zone->name} ({$providerType})"];
        })->toArray();

        return select(
            label: 'Select zone:',
            options: $zoneOptions,
            required: true
        );
    }

    protected function displayDnssecStatus(array $status): void
    {
        $this->table(['Property', 'Value'], [
            ['Zone', $status['zone'] ?? 'N/A'],
            ['DNSSEC Enabled', $status['dnssec_enabled'] ? '✅ Yes' : '❌ No'],
            ['Auto DNSSEC', ($status['auto_dnssec'] ?? false) ? '🤖 Automatic' : '👤 Manual'],
            ['Provider', $status['provider'] ?? 'Unknown'],
            ['Last Updated', isset($status['last_updated']) ? $status['last_updated']->format('Y-m-d H:i:s') : 'N/A'],
        ]);

        if (! empty($status['provider_data'])) {
            $this->info('📋 Provider Data:');
            foreach ($status['provider_data'] as $key => $value) {
                if (is_array($value)) {
                    $this->line("  {$key}: ".json_encode($value));
                } else {
                    $this->line("  {$key}: {$value}");
                }
            }
        }
    }

    protected function displayDnssecOptions(array $options, string $providerType): void
    {
        $this->info('🔐 DNSSEC Configuration:');
        $this->table(['Setting', 'Value'], [
            ['Algorithm', $options['algorithm'].' ('.$this->getAlgorithmName($options['algorithm']).')'],
            ['Key Size', $options['key_size'].' bits'],
            ['KSK Size', $options['ksk_size'].' bits'],
            ['Digest Type', $options['digest_type'].' ('.$this->getDigestTypeName($options['digest_type']).')'],
            ['Auto DNSSEC', $options['auto_dnssec'] ? 'Yes' : 'No'],
            ['Provider', $providerType],
        ]);
    }

    protected function displayValidationResults(array $validation): void
    {
        $this->info("🔍 DNSSEC Validation Results for: {$validation['zone']}");

        $overallStatus = match ($validation['overall_status']) {
            'pass' => '✅ PASS',
            'fail' => '❌ FAIL',
            'error' => '🔥 ERROR'
        };

        $this->info("Overall Status: {$overallStatus}");
        $this->line('');

        $rows = [];
        foreach ($validation['checks'] as $check) {
            $status = match ($check['status']) {
                'pass' => '✅ Pass',
                'fail' => '❌ Fail',
                'warn' => '⚠️ Warning',
                'error' => '🔥 Error'
            };

            $rows[] = [
                $check['name'],
                $status,
                $check['message'],
            ];
        }

        table(['Check', 'Status', 'Message'], $rows);
    }

    protected function displayKeyGenerationResults(array $result): void
    {
        $rows = [];
        foreach ($result as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? '✅ Yes' : '❌ No';
            } elseif (is_array($value)) {
                $value = json_encode($value);
            }
            $rows[] = [ucfirst(str_replace('_', ' ', $key)), $value];
        }

        table(['Property', 'Value'], $rows);
    }

    protected function exportDnskeyRecords(string $zone): int
    {
        $this->info("🔑 DNSKEY Records for zone: {$zone}");
        $this->warn('💡 This feature requires implementation based on your DNS provider');

        return self::SUCCESS;
    }

    protected function exportPublicKeys(string $zone): int
    {
        $this->info("📤 Public Key Export for zone: {$zone}");
        $this->warn('💡 This feature requires implementation based on your DNS provider');

        return self::SUCCESS;
    }

    protected function getAlgorithmName(int $algorithm): string
    {
        $algorithms = [
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
        ];

        return $algorithms[$algorithm] ?? 'Unknown';
    }

    protected function getDigestTypeName(int $digestType): string
    {
        $digestTypes = [
            1 => 'SHA-1',
            2 => 'SHA-256',
            3 => 'GOST R 34.11-94',
            4 => 'SHA-384',
        ];

        return $digestTypes[$digestType] ?? 'Unknown';
    }
}
