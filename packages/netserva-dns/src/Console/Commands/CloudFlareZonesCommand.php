<?php

namespace NetServa\Dns\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Laravel\Prompts\Progress;
use NetServa\Dns\Services\CloudFlareApiException;
use NetServa\Dns\Services\CloudFlareRateLimitException;
use NetServa\Dns\Services\CloudFlareService;

/**
 * CloudFlare Zones Management Command
 *
 * Manage CloudFlare DNS zones with comprehensive operations
 */
class CloudFlareZonesCommand extends Command
{
    protected $signature = 'dns:cloudflare:zones
                           {action : Action to perform (list, show, create, delete, purge, settings, cache-update)}
                           {zone? : Zone name or ID for specific operations}
                           {--format=table : Output format (table, json)}
                           {--all : Show all zones (not just active)}
                           {--detailed : Show detailed zone information}
                           {--dry-run : Show what would be done without executing}
                           {--force : Skip confirmation prompts}';

    protected $description = 'Manage CloudFlare DNS zones';

    protected CloudFlareService $cloudflare;

    public function __construct(CloudFlareService $cloudflare)
    {
        parent::__construct();
        $this->cloudflare = $cloudflare;
    }

    public function handle(): int
    {
        $action = $this->argument('action');
        $zone = $this->argument('zone');
        $isDryRun = $this->option('dry-run');
        $format = $this->option('format');

        if ($isDryRun) {
            $this->info("🔍 DRY RUN - CloudFlare zones {$action}".($zone ? " for {$zone}" : ''));
        }

        try {
            // Test CloudFlare connection first
            $connectionTest = $this->cloudflare->testConnection();
            if (! $connectionTest['success']) {
                $this->error('❌ CloudFlare connection failed: '.$connectionTest['message']);

                return self::FAILURE;
            }

            return match ($action) {
                'list' => $this->listZones($format),
                'show' => $this->showZone($zone, $format),
                'create' => $this->createZone($zone, $isDryRun),
                'delete' => $this->deleteZone($zone, $isDryRun),
                'purge' => $this->purgeZoneCache($zone, $isDryRun),
                'settings' => $this->manageZoneSettings($zone, $isDryRun),
                'cache-update' => $this->updateBashCache($isDryRun),
                default => $this->error("❌ Unknown action: {$action}") ?: self::FAILURE
            };

        } catch (CloudFlareRateLimitException $e) {
            $this->error('❌ CloudFlare rate limit exceeded: '.$e->getMessage());
            $this->warn('💡 Please wait before retrying');

            return self::FAILURE;
        } catch (CloudFlareApiException $e) {
            $this->error('❌ CloudFlare API error: '.$e->getMessage());
            Log::error('CloudFlare zones command failed', [
                'action' => $action,
                'zone' => $zone,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        } catch (Exception $e) {
            $this->error('❌ Command failed: '.$e->getMessage());
            Log::error('CloudFlare zones command exception', [
                'action' => $action,
                'zone' => $zone,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }

    protected function listZones(string $format): int
    {
        $this->info('🌍 Listing CloudFlare zones...');

        $zones = $this->cloudflare->listZones();

        if (empty($zones)) {
            $this->warn('⚠️ No zones found');

            return self::SUCCESS;
        }

        $this->info('📋 Found '.count($zones).' zones');

        if ($format === 'json') {
            $this->line(json_encode($zones, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        // Filter zones if not showing all
        if (! $this->option('all')) {
            $zones = array_filter($zones, fn ($zone) => $zone['status'] === 'active');
        }

        $rows = [];
        foreach ($zones as $zone) {
            $rows[] = [
                $zone['name'] ?? 'N/A',
                $zone['id'] ?? 'N/A',
                $zone['status'] ?? 'unknown',
                $zone['plan']['name'] ?? 'N/A',
                isset($zone['created_on']) ? date('Y-m-d', strtotime($zone['created_on'])) : 'N/A',
                isset($zone['name_servers']) ? implode(', ', array_slice($zone['name_servers'], 0, 2)) : 'N/A',
            ];
        }

        table(
            ['Zone Name', 'Zone ID', 'Status', 'Plan', 'Created', 'Name Servers'],
            $rows
        );

        return self::SUCCESS;
    }

    protected function showZone(?string $zone, string $format): int
    {
        if (! $zone) {
            $zone = text(
                label: 'Enter zone name or ID:',
                placeholder: 'example.com',
                required: true
            );
        }

        $this->info("🔍 Getting zone details for: {$zone}");

        // Try to get zone by name first, then by ID
        $zoneData = $this->cloudflare->getZoneByName($zone) ?? $this->cloudflare->getZone($zone);

        if (! $zoneData) {
            $this->error("❌ Zone '{$zone}' not found");

            return self::FAILURE;
        }

        if ($format === 'json') {
            $this->line(json_encode($zoneData, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info("📋 Zone Details: {$zoneData['name']}");

        $details = [
            ['Property', 'Value'],
            ['Zone ID', $zoneData['id'] ?? 'N/A'],
            ['Zone Name', $zoneData['name'] ?? 'N/A'],
            ['Status', $zoneData['status'] ?? 'unknown'],
            ['Plan', $zoneData['plan']['name'] ?? 'N/A'],
            ['Type', $zoneData['type'] ?? 'N/A'],
            ['Development Mode', isset($zoneData['development_mode']) ? ($zoneData['development_mode'] ? 'On' : 'Off') : 'N/A'],
            ['Created', isset($zoneData['created_on']) ? date('Y-m-d H:i:s', strtotime($zoneData['created_on'])) : 'N/A'],
            ['Modified', isset($zoneData['modified_on']) ? date('Y-m-d H:i:s', strtotime($zoneData['modified_on'])) : 'N/A'],
        ];

        table(['Property', 'Value'], array_slice($details, 1));

        if (isset($zoneData['name_servers'])) {
            $this->info('🌐 Name Servers:');
            foreach ($zoneData['name_servers'] as $ns) {
                $this->line("  • {$ns}");
            }
        }

        if ($this->option('detailed')) {
            $this->showZoneAnalytics($zoneData['id']);
            $this->showZoneSettings($zoneData['id']);
        }

        return self::SUCCESS;
    }

    protected function createZone(?string $zone, bool $isDryRun): int
    {
        if (! $zone) {
            $zone = text(
                label: 'Enter domain name for new zone:',
                placeholder: 'example.com',
                required: true,
                validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_DOMAIN) ? null : 'Please enter a valid domain name'
            );
        }

        $this->info("🆕 Creating CloudFlare zone for: {$zone}");

        if ($isDryRun) {
            $this->info("🔍 DRY RUN - Would create zone: {$zone}");

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            if (! confirm("Create new CloudFlare zone for '{$zone}'?", true)) {
                $this->info('⚠️ Operation cancelled');

                return self::SUCCESS;
            }
        }

        // Check if zone already exists
        $existingZone = $this->cloudflare->getZoneByName($zone);
        if ($existingZone) {
            $this->error("❌ Zone '{$zone}' already exists");

            return self::FAILURE;
        }

        $progress = new Progress('Creating zone...', 3);

        try {
            // Step 1: Validate domain
            $progress->label('Validating domain...');
            if (! filter_var($zone, FILTER_VALIDATE_DOMAIN)) {
                $progress->finish();
                $this->error("❌ Invalid domain name: {$zone}");

                return self::FAILURE;
            }
            $progress->advance();

            // Step 2: Create zone
            $progress->label('Creating zone in CloudFlare...');
            $result = $this->cloudflare->createZone(['name' => $zone]);
            $progress->advance();

            // Step 3: Verify creation
            $progress->label('Verifying zone creation...');
            $createdZone = $this->cloudflare->getZone($result['id']);
            $progress->advance();

            $progress->finish();

            $this->info("✅ Zone '{$zone}' created successfully");
            $this->info("🆔 Zone ID: {$result['id']}");

            if (isset($createdZone['name_servers'])) {
                $this->info('🌐 Name Servers:');
                foreach ($createdZone['name_servers'] as $ns) {
                    $this->line("  • {$ns}");
                }
                $this->warn('💡 Update your domain registrar to use these name servers');
            }

            return self::SUCCESS;

        } catch (Exception $e) {
            $progress->finish();
            throw $e;
        }
    }

    protected function deleteZone(?string $zone, bool $isDryRun): int
    {
        if (! $zone) {
            $zones = $this->cloudflare->listZones();
            $zoneOptions = array_map(fn ($z) => $z['name'], $zones);

            $zone = select(
                label: 'Select zone to delete:',
                options: $zoneOptions,
                required: true
            );
        }

        $zoneData = $this->cloudflare->getZoneByName($zone) ?? $this->cloudflare->getZone($zone);

        if (! $zoneData) {
            $this->error("❌ Zone '{$zone}' not found");

            return self::FAILURE;
        }

        $this->error('⚠️  Zone Deletion Summary');
        $this->table(['Property', 'Value'], [
            ['Zone Name', $zoneData['name']],
            ['Zone ID', $zoneData['id']],
            ['Status', $zoneData['status']],
            ['Created', date('Y-m-d H:i:s', strtotime($zoneData['created_on']))],
        ]);

        if ($isDryRun) {
            $this->info("🔍 DRY RUN - Would delete zone: {$zoneData['name']}");

            return self::SUCCESS;
        }

        $this->warn('⚠️  This will permanently delete the zone and all its DNS records!');

        if (! $this->option('force')) {
            $confirmation = text(
                label: "Type 'DELETE' to confirm zone deletion:",
                required: true
            );

            if ($confirmation !== 'DELETE') {
                $this->info('⚠️ Operation cancelled');

                return self::SUCCESS;
            }
        }

        $progress = new Progress('Deleting zone...', 2);

        try {
            $progress->label('Deleting zone from CloudFlare...');
            $result = $this->cloudflare->deleteZone($zoneData['id']);
            $progress->advance();

            $progress->label('Verifying deletion...');
            $progress->advance();

            $progress->finish();

            $this->info("✅ Zone '{$zoneData['name']}' deleted successfully");

            return self::SUCCESS;

        } catch (Exception $e) {
            $progress->finish();
            throw $e;
        }
    }

    protected function purgeZoneCache(?string $zone, bool $isDryRun): int
    {
        if (! $zone) {
            $zones = $this->cloudflare->listZones();
            $zoneOptions = array_map(fn ($z) => $z['name'], $zones);

            $zone = select(
                label: 'Select zone to purge cache:',
                options: $zoneOptions,
                required: true
            );
        }

        $zoneData = $this->cloudflare->getZoneByName($zone) ?? $this->cloudflare->getZone($zone);

        if (! $zoneData) {
            $this->error("❌ Zone '{$zone}' not found");

            return self::FAILURE;
        }

        $purgeType = select(
            label: 'Select cache purge type:',
            options: [
                'everything' => 'Purge everything',
                'files' => 'Purge specific files',
            ],
            default: 'everything'
        );

        $files = [];
        if ($purgeType === 'files') {
            $filesInput = text(
                label: 'Enter file URLs to purge (comma-separated):',
                placeholder: 'https://example.com/style.css,https://example.com/script.js',
                required: true
            );
            $files = array_map('trim', explode(',', $filesInput));
        }

        if ($isDryRun) {
            $this->info("🔍 DRY RUN - Would purge cache for zone: {$zoneData['name']}");
            if ($purgeType === 'files') {
                $this->info('Files to purge: '.implode(', ', $files));
            }

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $message = $purgeType === 'everything'
                ? "Purge all cache for zone '{$zoneData['name']}'?"
                : 'Purge cache for '.count($files)." files in zone '{$zoneData['name']}'?";

            if (! confirm($message, true)) {
                $this->info('⚠️ Operation cancelled');

                return self::SUCCESS;
            }
        }

        $this->info("🧹 Purging cache for zone: {$zoneData['name']}");

        $result = $this->cloudflare->purgeCache($zoneData['id'], $files);

        $this->info('✅ Cache purge initiated successfully');
        if (isset($result['id'])) {
            $this->info("🆔 Purge ID: {$result['id']}");
        }

        return self::SUCCESS;
    }

    protected function manageZoneSettings(?string $zone, bool $isDryRun): int
    {
        if (! $zone) {
            $zones = $this->cloudflare->listZones();
            $zoneOptions = array_map(fn ($z) => $z['name'], $zones);

            $zone = select(
                label: 'Select zone to manage settings:',
                options: $zoneOptions,
                required: true
            );
        }

        $zoneData = $this->cloudflare->getZoneByName($zone) ?? $this->cloudflare->getZone($zone);

        if (! $zoneData) {
            $this->error("❌ Zone '{$zone}' not found");

            return self::FAILURE;
        }

        $action = select(
            label: 'Select settings action:',
            options: [
                'show' => 'Show current settings',
                'update' => 'Update setting',
            ]
        );

        if ($action === 'show') {
            return $this->showZoneSettings($zoneData['id']);
        }

        // Update setting
        $commonSettings = [
            'ssl' => 'SSL/TLS encryption mode',
            'cache_level' => 'Cache level',
            'development_mode' => 'Development mode',
            'security_level' => 'Security level',
            'browser_cache_ttl' => 'Browser cache TTL',
        ];

        $setting = select(
            label: 'Select setting to update:',
            options: $commonSettings
        );

        $value = match ($setting) {
            'ssl' => select('SSL mode:', ['off', 'flexible', 'full', 'strict']),
            'cache_level' => select('Cache level:', ['aggressive', 'basic', 'simplified']),
            'development_mode' => select('Development mode:', ['on', 'off']),
            'security_level' => select('Security level:', ['off', 'essentially_off', 'low', 'medium', 'high', 'under_attack']),
            'browser_cache_ttl' => select('Browser cache TTL:', ['0', '1800', '3600', '7200', '10800', '14400', '18000', '28800', '43200', '57600', '72000', '86400', '172800', '259200', '345600', '432000', '691200', '1382400', '2073600', '2678400', '5356800', '16070400', '31536000']),
            default => text("Enter value for {$setting}:", required: true)
        };

        if ($isDryRun) {
            $this->info("🔍 DRY RUN - Would update {$setting} to '{$value}' for zone: {$zoneData['name']}");

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            if (! confirm("Update {$setting} to '{$value}' for zone '{$zoneData['name']}'?", true)) {
                $this->info('⚠️ Operation cancelled');

                return self::SUCCESS;
            }
        }

        $this->info("⚙️ Updating zone setting: {$setting}");

        $result = $this->cloudflare->updateZoneSetting($zoneData['id'], $setting, $value);

        $this->info("✅ Setting '{$setting}' updated to '{$value}'");

        return self::SUCCESS;
    }

    protected function showZoneAnalytics(string $zoneId): void
    {
        try {
            $this->info('📊 Zone Analytics:');
            $analytics = $this->cloudflare->getZoneAnalytics($zoneId);

            if (isset($analytics['totals'])) {
                $totals = $analytics['totals'];
                $this->table(['Metric', 'Value'], [
                    ['Requests', number_format($totals['requests']['all'] ?? 0)],
                    ['Cached Requests', number_format($totals['requests']['cached'] ?? 0)],
                    ['Uncached Requests', number_format($totals['requests']['uncached'] ?? 0)],
                    ['Bandwidth (bytes)', number_format($totals['bandwidth']['all'] ?? 0)],
                    ['Threats', number_format($totals['threats']['all'] ?? 0)],
                ]);
            }
        } catch (Exception $e) {
            $this->warn('⚠️ Could not retrieve analytics: '.$e->getMessage());
        }
    }

    protected function showZoneSettings(string $zoneId): int
    {
        try {
            $this->info('⚙️ Zone Settings:');
            $settings = $this->cloudflare->getZoneSettings($zoneId);

            $rows = [];
            foreach ($settings as $setting) {
                $rows[] = [
                    $setting['id'] ?? 'N/A',
                    $setting['value'] ?? 'N/A',
                    $setting['modified_on'] ? date('Y-m-d H:i:s', strtotime($setting['modified_on'])) : 'Never',
                ];
            }

            table(['Setting', 'Value', 'Last Modified'], $rows);

            return self::SUCCESS;

        } catch (Exception $e) {
            $this->warn('⚠️ Could not retrieve settings: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Update bash cache files for CloudFlare domains
     */
    protected function updateBashCache(bool $isDryRun = false): int
    {
        $this->info('🔄 Updating CloudFlare domain cache for bash scripts...');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN - Would update cache files');

            return self::SUCCESS;
        }

        try {
            // Get zones from CloudFlare API
            $zones = $this->cloudflare->listZones();

            if (empty($zones)) {
                $this->warn('⚠️ No domains found in CloudFlare account');

                return self::FAILURE;
            }

            // Prepare cache data
            $domainList = [];
            $jsonData = ['result' => $zones];

            foreach ($zones as $zone) {
                $domainList[] = $zone['name'].'='.$zone['id'];
            }

            // Get cache file paths
            $tmpDir = env('NS', base_path()).'/tmp';
            $txtFile = $tmpDir.'/cf_domains.txt';
            $jsonFile = $tmpDir.'/cf_domains.json';
            $updateFile = $tmpDir.'/cf_last_update';

            // Ensure directory exists
            $cacheDir = dirname($txtFile);
            if (! is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }

            // Write cache files
            file_put_contents($txtFile, implode("\n", $domainList)."\n");
            file_put_contents($jsonFile, json_encode($jsonData, JSON_PRETTY_PRINT));
            file_put_contents($updateFile, time());

            $this->info('✅ CloudFlare cache updated successfully');
            $this->line("📄 Domains file: $txtFile");
            $this->line("📄 JSON file: $jsonFile");
            $this->line("📄 Update timestamp: $updateFile");
            $this->line('📊 Cached '.count($domainList).' domains');

            return self::SUCCESS;

        } catch (Exception $e) {
            $this->error('❌ Failed to update cache: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
