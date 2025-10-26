<?php

namespace App\Console\Commands;

use App\Models\SwDomain;
use App\Services\SynergyWholesaleService;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class ChswCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chsw {domain : Domain name to modify}
                            {--action= : Action to perform (ns, renew, lock, unlock, id-protect, auth-code, glue, metadata)}
                            {--ns=* : Nameservers (for ns action)}
                            {--years=1 : Years to renew (for renew action)}
                            {--auto-renew : Enable auto-renewal}
                            {--no-auto-renew : Disable auto-renewal}
                            {--id-protect : Enable ID protection}
                            {--no-id-protect : Disable ID protection}
                            {--sync : Sync domain info after changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Modify Synergy Wholesale domain settings';

    /**
     * Execute the console command.
     */
    public function handle(SynergyWholesaleService $sw): int
    {
        $domainName = $this->argument('domain');

        // Get domain from local DB
        $domain = SwDomain::where('domain_name', $domainName)->first();

        if (! $domain) {
            $this->error("Domain not found in local database: {$domainName}");
            $this->line('Run "php artisan shsw --sync" to sync from API');

            return 1;
        }

        $this->info("Modifying domain: {$domainName}");
        $this->line("Current status: {$domain->lifecycle_status}");
        $this->newLine();

        // Determine action
        $action = $this->option('action');

        if (! $action && ! $this->option('no-interaction')) {
            $action = select(
                label: 'Select action',
                options: [
                    'ns' => 'Update nameservers',
                    'renew' => 'Renew domain',
                    'lock' => 'Lock domain (transfer lock)',
                    'unlock' => 'Unlock domain',
                    'id-protect' => 'Toggle ID protection',
                    'auto-renew' => 'Toggle auto-renewal',
                    'auth-code' => 'Generate new EPP auth code',
                    'glue' => 'Manage glue records',
                    'metadata' => 'Manage metadata',
                ]
            );
        }

        if (! $action) {
            $this->error('No action specified. Use --action or run interactively');

            return 1;
        }

        // Execute action
        return match ($action) {
            'ns' => $this->updateNameservers($domain, $sw),
            'renew' => $this->renewDomain($domain, $sw),
            'lock' => $this->lockDomain($domain, $sw),
            'unlock' => $this->unlockDomain($domain, $sw),
            'id-protect' => $this->toggleIdProtection($domain, $sw),
            'auto-renew' => $this->toggleAutoRenew($domain, $sw),
            'auth-code' => $this->generateAuthCode($domain, $sw),
            'glue' => $this->manageGlueRecords($domain, $sw),
            'metadata' => $this->manageMetadata($domain),
            default => $this->error("Unknown action: {$action}") ?: 1,
        };
    }

    /**
     * Update nameservers
     */
    private function updateNameservers(SwDomain $domain, SynergyWholesaleService $sw): int
    {
        $nameservers = $this->option('ns');

        if (empty($nameservers) && ! $this->option('no-interaction')) {
            $this->line('Enter nameservers (leave empty to finish):');
            while (count($nameservers) < 13) {
                $ns = text("Nameserver ".(count($nameservers) + 1).' (optional):');
                if (empty($ns)) {
                    break;
                }
                $nameservers[] = $ns;
            }
        }

        if (empty($nameservers)) {
            $this->error('No nameservers provided');

            return 1;
        }

        if (count($nameservers) < 2) {
            $this->error('At least 2 nameservers required');

            return 1;
        }

        $this->info('Current nameservers:');
        foreach ($domain->nameservers ?? [] as $ns) {
            $this->line("  • {$ns}");
        }

        $this->newLine();
        $this->info('New nameservers:');
        foreach ($nameservers as $ns) {
            $this->line("  • {$ns}");
        }

        if (! $this->option('no-interaction')) {
            if (! confirm('Update nameservers?', true)) {
                return 0;
            }
        }

        try {
            $sw->updateNameservers($domain->domain_name, $nameservers);
            $this->info('✓ Nameservers updated successfully');

            // Update local record
            $domain->nameservers = $nameservers;
            $domain->save();

            return $this->syncIfRequested($domain, $sw);
        } catch (\Exception $e) {
            $this->error("Failed to update nameservers: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Renew domain
     */
    private function renewDomain(SwDomain $domain, SynergyWholesaleService $sw): int
    {
        $years = (int) $this->option('years');

        if ($years < 1 || $years > 10) {
            $this->error('Years must be between 1 and 10');

            return 1;
        }

        $this->line("Current expiry: {$domain->domain_expiry?->format('Y-m-d')}");
        $newExpiry = $domain->domain_expiry?->addYears($years);
        $this->line("New expiry: {$newExpiry?->format('Y-m-d')}");

        if (! $this->option('no-interaction')) {
            if (! confirm("Renew domain for {$years} year(s)?", true)) {
                return 0;
            }
        }

        try {
            $result = $sw->renewDomain($domain->domain_name, $years);
            $this->info("✓ Domain renewed for {$years} year(s)");

            if (isset($result['orderId'])) {
                $this->line("Order ID: {$result['orderId']}");
            }

            return $this->syncIfRequested($domain, $sw);
        } catch (\Exception $e) {
            $this->error("Failed to renew domain: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Lock domain
     */
    private function lockDomain(SwDomain $domain, SynergyWholesaleService $sw): int
    {
        try {
            $sw->lockDomain($domain->domain_name);
            $this->info('✓ Domain locked (transfer protection enabled)');

            return $this->syncIfRequested($domain, $sw);
        } catch (\Exception $e) {
            $this->error("Failed to lock domain: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Unlock domain
     */
    private function unlockDomain(SwDomain $domain, SynergyWholesaleService $sw): int
    {
        $this->warn('Unlocking domain will allow transfers. Proceed with caution.');

        if (! $this->option('no-interaction')) {
            if (! confirm('Unlock domain?', false)) {
                return 0;
            }
        }

        try {
            $sw->unlockDomain($domain->domain_name);
            $this->info('✓ Domain unlocked (transfers enabled)');

            return $this->syncIfRequested($domain, $sw);
        } catch (\Exception $e) {
            $this->error("Failed to unlock domain: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Toggle ID protection
     */
    private function toggleIdProtection(SwDomain $domain, SynergyWholesaleService $sw): int
    {
        $enable = $this->option('id-protect') ? true : ($this->option('no-id-protect') ? false : null);

        if ($enable === null && ! $this->option('no-interaction')) {
            $current = $domain->id_protection_enabled ? 'enabled' : 'disabled';
            $enable = confirm("ID protection currently {$current}. Enable?", ! $domain->id_protection_enabled);
        }

        if ($enable === null) {
            $this->error('Must specify --id-protect or --no-id-protect');

            return 1;
        }

        try {
            $sw->setIdProtection($domain->domain_name, $enable);
            $status = $enable ? 'enabled' : 'disabled';
            $this->info("✓ ID protection {$status}");

            $domain->id_protection_enabled = $enable;
            $domain->save();

            return $this->syncIfRequested($domain, $sw);
        } catch (\Exception $e) {
            $this->error("Failed to update ID protection: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Toggle auto-renewal
     */
    private function toggleAutoRenew(SwDomain $domain, SynergyWholesaleService $sw): int
    {
        $enable = $this->option('auto-renew') ? true : ($this->option('no-auto-renew') ? false : null);

        if ($enable === null && ! $this->option('no-interaction')) {
            $current = $domain->auto_renew ? 'enabled' : 'disabled';
            $enable = confirm("Auto-renewal currently {$current}. Enable?", ! $domain->auto_renew);
        }

        if ($enable === null) {
            $this->error('Must specify --auto-renew or --no-auto-renew');

            return 1;
        }

        try {
            $sw->setAutoRenew($domain->domain_name, $enable);
            $status = $enable ? 'enabled' : 'disabled';
            $this->info("✓ Auto-renewal {$status}");

            $domain->auto_renew = $enable;
            $domain->save();

            return $this->syncIfRequested($domain, $sw);
        } catch (\Exception $e) {
            $this->error("Failed to update auto-renewal: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Generate new EPP auth code
     */
    private function generateAuthCode(SwDomain $domain, SynergyWholesaleService $sw): int
    {
        $this->warn('Generating new auth code will invalidate the current one.');

        if (! $this->option('no-interaction')) {
            if (! confirm('Generate new EPP auth code?', false)) {
                return 0;
            }
        }

        try {
            $result = $sw->generateAuthCode($domain->domain_name);
            $this->info('✓ New EPP auth code generated');

            if (isset($result['authCode'])) {
                $this->line("Auth Code: {$result['authCode']}");
            }

            return $this->syncIfRequested($domain, $sw);
        } catch (\Exception $e) {
            $this->error("Failed to generate auth code: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Manage glue records
     */
    private function manageGlueRecords(SwDomain $domain, SynergyWholesaleService $sw): int
    {
        $this->info('Current glue records:');
        $glueRecords = $domain->glueRecords;

        if ($glueRecords->isEmpty()) {
            $this->line('  (none)');
        } else {
            foreach ($glueRecords as $glue) {
                $this->line("  • {$glue->hostname} → ".implode(', ', $glue->ip_addresses));
            }
        }

        $this->newLine();

        $glueAction = select(
            label: 'Glue record action',
            options: [
                'sync' => 'Sync from API',
                'add' => 'Add glue record',
                'delete' => 'Delete glue record',
            ]
        );

        return match ($glueAction) {
            'sync' => $this->syncGlueRecords($domain, $sw),
            'add' => $this->addGlueRecord($domain, $sw),
            'delete' => $this->deleteGlueRecord($domain, $sw),
            default => 0,
        };
    }

    /**
     * Sync glue records from API
     */
    private function syncGlueRecords(SwDomain $domain, SynergyWholesaleService $sw): int
    {
        try {
            $count = $domain->syncGlueRecordsFromAPI($sw);
            $this->info("✓ Synced {$count} glue record(s)");

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to sync glue records: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Add glue record
     */
    private function addGlueRecord(SwDomain $domain, SynergyWholesaleService $sw): int
    {
        $hostname = text('Hostname (e.g., ns1.example.com):');
        $ipAddresses = [];

        $this->line('Enter IP addresses (leave empty to finish):');
        while (true) {
            $ip = text('IP address:');
            if (empty($ip)) {
                break;
            }
            if (! filter_var($ip, FILTER_VALIDATE_IP)) {
                $this->error('Invalid IP address');

                continue;
            }
            $ipAddresses[] = $ip;
        }

        if (empty($ipAddresses)) {
            $this->error('At least one IP address required');

            return 1;
        }

        try {
            $sw->addGlueRecord($domain->domain_name, $hostname, $ipAddresses);
            $this->info("✓ Glue record added: {$hostname}");

            // Sync to update local cache
            return $this->syncGlueRecords($domain, $sw);
        } catch (\Exception $e) {
            $this->error("Failed to add glue record: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Delete glue record
     */
    private function deleteGlueRecord(SwDomain $domain, SynergyWholesaleService $sw): int
    {
        $glueRecords = $domain->glueRecords;

        if ($glueRecords->isEmpty()) {
            $this->warn('No glue records to delete');

            return 0;
        }

        $options = $glueRecords->mapWithKeys(function ($glue) {
            return [$glue->hostname => $glue->hostname.' ('.implode(', ', $glue->ip_addresses).')'];
        })->toArray();

        $hostname = select('Select glue record to delete:', $options);

        if (! confirm("Delete glue record {$hostname}?", false)) {
            return 0;
        }

        try {
            $sw->deleteGlueRecord($domain->domain_name, $hostname);
            $this->info("✓ Glue record deleted: {$hostname}");

            // Sync to update local cache
            return $this->syncGlueRecords($domain, $sw);
        } catch (\Exception $e) {
            $this->error("Failed to delete glue record: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Manage metadata
     */
    private function manageMetadata(SwDomain $domain): int
    {
        $this->info('Current metadata:');
        $metadata = $domain->metadata;

        if ($metadata->isEmpty()) {
            $this->line('  (none)');
        } else {
            foreach ($metadata as $meta) {
                $this->line("  • {$meta->key}: {$meta->value}");
            }
        }

        $this->newLine();

        $metaAction = select(
            label: 'Metadata action',
            options: [
                'set' => 'Set metadata value',
                'delete' => 'Delete metadata key',
            ]
        );

        if ($metaAction === 'set') {
            $key = text('Key:');
            $value = text('Value:');

            $domain->setMeta($key, $value);
            $this->info("✓ Metadata set: {$key} = {$value}");
        } elseif ($metaAction === 'delete') {
            if ($metadata->isEmpty()) {
                $this->warn('No metadata to delete');

                return 0;
            }

            $options = $metadata->mapWithKeys(function ($meta) {
                return [$meta->key => "{$meta->key}: {$meta->value}"];
            })->toArray();

            $key = select('Select key to delete:', $options);

            if (confirm("Delete metadata key '{$key}'?", false)) {
                $domain->deleteMeta($key);
                $this->info("✓ Metadata deleted: {$key}");
            }
        }

        return 0;
    }

    /**
     * Sync domain if --sync flag is present
     */
    private function syncIfRequested(SwDomain $domain, SynergyWholesaleService $sw): int
    {
        if ($this->option('sync')) {
            $this->newLine();
            $this->line('Syncing domain info...');

            try {
                SwDomain::syncFromAPI($domain->domain_name, $sw);
                $this->info('✓ Domain synced');
            } catch (\Exception $e) {
                $this->warn("Warning: Sync failed: {$e->getMessage()}");
            }
        }

        return 0;
    }
}
