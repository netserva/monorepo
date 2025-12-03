<?php

namespace NetServa\Dns\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use NetServa\Dns\Models\DomainRegistrar;
use NetServa\Dns\Models\DomainRegistration;
use SoapClient;

/**
 * Sync Domains from Registrar
 *
 * Imports active domains from registrar API to local database
 */
class SyncDomainsCommand extends Command
{
    protected $signature = 'dns:sync-domains
                            {--registrar= : Registrar ID or name}
                            {--dry-run : Show what would be synced without making changes}
                            {--prune : Remove domains no longer at registrar}';

    protected $description = 'Sync domains from registrar API to local database';

    public function handle(): int
    {
        $registrar = $this->resolveRegistrar();
        if (! $registrar) {
            return Command::FAILURE;
        }

        $dryRun = $this->option('dry-run');
        $prune = $this->option('prune');

        $this->info("Syncing domains from {$registrar->name}...");

        try {
            $domains = $this->fetchDomains($registrar);

            if (empty($domains)) {
                $this->warn('No active domains found.');

                return Command::SUCCESS;
            }

            $this->info('Found '.count($domains).' active domains.');
            $this->newLine();

            $stats = [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
            ];

            $bar = $this->output->createProgressBar(count($domains));
            $bar->start();

            $syncedDomains = [];

            foreach ($domains as $domain) {
                $syncedDomains[] = $domain['domain'];
                $this->processDomain($domain, $registrar, $dryRun, $stats);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            // Prune deleted domains
            if ($prune) {
                $this->pruneDomains($registrar, $syncedDomains, $dryRun, $stats);
            }

            // Summary
            $this->info('Sync complete:');
            $this->line("  Created: {$stats['created']}");
            $this->line("  Updated: {$stats['updated']}");
            $this->line("  Skipped: {$stats['skipped']}");

            if ($dryRun) {
                $this->newLine();
                $this->warn('DRY RUN - No changes were made');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Sync failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    protected function fetchDomains(DomainRegistrar $registrar): array
    {
        return match ($registrar->registrar_type) {
            'synergywholesale' => $this->fetchSynergyWholesaleDomains($registrar),
            default => throw new \Exception("Sync not implemented for {$registrar->registrar_type}"),
        };
    }

    protected function fetchSynergyWholesaleDomains(DomainRegistrar $registrar): array
    {
        $client = new SoapClient($registrar->api_endpoint, [
            'trace' => true,
            'exceptions' => true,
        ]);

        $response = $client->__soapCall('listDomains', [[
            'resellerID' => $registrar->additional_config['reseller_id'],
            'apiKey' => Crypt::decryptString($registrar->api_key_encrypted),
        ]]);

        $domains = [];
        $now = now();

        foreach ($response->domainList as $d) {
            $data = (array) $d;
            $expiry = Carbon::parse($data['domain_expiry'] ?? '2000-01-01');

            // Only include active (non-expired) domains
            if ($expiry->isFuture()) {
                $domains[] = [
                    'domain' => $data['domainName'],
                    'status' => $data['domain_status'] ?? 'unknown',
                    'expiry_date' => $expiry->toDateString(),
                    'created_date' => isset($data['createdDate']) ? Carbon::parse($data['createdDate'])->toDateString() : null,
                    'nameservers' => $data['nameServers'] ?? [],
                    'auto_renew' => ($data['autoRenew'] ?? 0) == 1,
                    'locked' => ($data['domain_status'] ?? '') === 'clientTransferProhibited',
                    'categories' => $data['categories'] ?? [],
                    'registrant' => isset($data['contacts']) ? ((array) $data['contacts'])['registrant'] ?? null : null,
                ];
            }
        }

        return $domains;
    }

    protected function processDomain(array $domain, DomainRegistrar $registrar, bool $dryRun, array &$stats): void
    {
        $existing = DomainRegistration::where('domain_name', $domain['domain'])->first();

        // Extract category names for tags
        $tags = [];
        if (! empty($domain['categories'])) {
            foreach ($domain['categories'] as $cat) {
                $tags[] = $cat->name ?? $cat['name'] ?? null;
            }
            $tags = array_filter($tags);
        }

        // Build registrant contact string
        $registrantContact = null;
        if (! empty($domain['registrant'])) {
            $r = (array) $domain['registrant'];
            $registrantContact = trim(($r['firstname'] ?? '').' '.($r['lastname'] ?? ''));
            if (! empty($r['organisation'])) {
                $registrantContact = $r['organisation'];
            }
        }

        $data = [
            'domain_registrar_id' => $registrar->id,
            'domain_name' => $domain['domain'],
            'registration_date' => $domain['created_date'],
            'expiry_date' => $domain['expiry_date'],
            'auto_renew' => $domain['auto_renew'],
            'nameservers' => $domain['nameservers'],
            'registrant_contact' => $registrantContact,
            'tags' => $tags,
            'status' => $domain['locked'] ? 'locked' : 'active',
            'metadata' => [
                'synced_at' => now()->toIso8601String(),
                'domain_status' => $domain['status'],
            ],
        ];

        if ($existing) {
            if ($dryRun) {
                $this->line("  Would update: {$domain['domain']}", null, 'v');
            } else {
                $existing->update($data);
            }
            $stats['updated']++;
        } else {
            if ($dryRun) {
                $this->line("  Would create: {$domain['domain']}", null, 'v');
            } else {
                DomainRegistration::create($data);
            }
            $stats['created']++;
        }
    }

    protected function pruneDomains(DomainRegistrar $registrar, array $syncedDomains, bool $dryRun, array &$stats): void
    {
        $orphaned = DomainRegistration::where('domain_registrar_id', $registrar->id)
            ->whereNotIn('domain_name', $syncedDomains)
            ->get();

        if ($orphaned->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->warn("Found {$orphaned->count()} domains no longer at registrar:");

        foreach ($orphaned as $domain) {
            if ($dryRun) {
                $this->line("  Would delete: {$domain->domain_name}");
            } else {
                $domain->delete();
                $this->line("  Deleted: {$domain->domain_name}");
            }
        }

        $stats['pruned'] = $orphaned->count();
    }

    protected function resolveRegistrar(): ?DomainRegistrar
    {
        $identifier = $this->option('registrar');

        if ($identifier) {
            $registrar = DomainRegistrar::where('id', $identifier)
                ->orWhere('name', $identifier)
                ->first();

            if (! $registrar) {
                $this->error("Registrar not found: {$identifier}");

                return null;
            }

            return $registrar;
        }

        // Default to first active registrar
        $registrar = DomainRegistrar::where('status', 'active')->first();

        if (! $registrar) {
            $this->error('No active registrar configured.');

            return null;
        }

        return $registrar;
    }
}
