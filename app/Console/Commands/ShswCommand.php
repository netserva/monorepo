<?php

namespace App\Console\Commands;

use App\Models\SwDomain;
use App\Services\SynergyWholesaleService;
use Illuminate\Console\Command;

use function Laravel\Prompts\table;

class ShswCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shsw {search? : Search pattern (regex) or exact domain name}
                            {--search= : Search pattern (regex) for domain names}
                            {--status=active : Filter by lifecycle status (active, transferred_away, all, etc.)}
                            {--expiring= : Show domains expiring in N days}
                            {--glue : Show only domains with glue records}
                            {--tld= : Filter by TLD (com, net, au, etc.)}
                            {--limit=25 : Limit results}
                            {--json : Output as JSON}
                            {--text : Plain text output (no table borders, script-friendly)}
                            {--ns : Show nameservers (4 columns: domain, ns1, ns2, ns3)}
                            {--sync : Sync all domains from Synergy Wholesale API to local cache}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show Synergy Wholesale domain(s)';

    /**
     * Execute the console command.
     */
    public function handle(SynergyWholesaleService $sw): int
    {
        // Handle --sync flag first
        if ($this->option('sync')) {
            return $this->syncDomains($sw);
        }

        $search = $this->argument('search') ?: $this->option('search');

        // If search looks like exact domain name, try to show single domain
        if ($search && str_contains($search, '.') && ! preg_match('/[*\[\]^$]/', $search)) {
            $domain = SwDomain::where('domain_name', $search)->first();
            if ($domain) {
                return $this->showDomain($search);
            }
        }

        // Show domain list (with optional search filter)
        return $this->listDomains($search);
    }

    /**
     * Show details for a specific domain
     */
    private function showDomain(string $domainName): int
    {
        $domain = SwDomain::where('domain_name', $domainName)->first();

        if (! $domain) {
            $this->error("Domain not found: {$domainName}");

            return 1;
        }

        // Load relationships
        $domain->load('glueRecords', 'metadata', 'additionalFields');

        if ($this->option('json')) {
            $this->line(json_encode($domain->toArray(), JSON_PRETTY_PRINT));

            return 0;
        }

        // Display domain details
        $this->info("Domain: {$domain->domain_name}");
        $this->newLine();

        // Core info
        table(
            ['Field', 'Value'],
            [
                ['ROID', $domain->domain_roid ?? 'N/A'],
                ['Registry ID', $domain->registry_id ?? 'N/A'],
                ['Lifecycle Status', $domain->lifecycle_status],
                ['Domain Status', $domain->domain_status ?? 'N/A'],
                ['Registrant', $domain->registrant ?? 'N/A'],
            ]
        );

        $this->newLine();

        // Dates
        $this->line('<fg=cyan>Dates:</>');
        table(
            ['Field', 'Value'],
            [
                ['Expiry', $domain->domain_expiry?->format('Y-m-d H:i:s') ?? 'N/A'],
                ['Registered', $domain->domain_registered?->format('Y-m-d H:i:s') ?? 'N/A'],
                ['Created', $domain->created_date?->format('Y-m-d H:i:s') ?? 'N/A'],
                ['Last Synced', $domain->last_synced_at?->format('Y-m-d H:i:s') ?? 'N/A'],
            ]
        );

        $this->newLine();

        // Security & Settings
        $this->line('<fg=cyan>Security & Settings:</>');
        table(
            ['Field', 'Value'],
            [
                ['EPP Auth Code', $domain->domain_password ? '****** (present)' : 'N/A'],
                ['ID Protection', $domain->id_protection_enabled ? 'Yes' : 'No'],
                ['Auto Renew', $domain->auto_renew ? 'Yes' : 'No'],
                ['Do Not Renew', $domain->do_not_renew ? 'Yes' : 'No'],
                ['Is Premium', $domain->is_premium ? 'Yes' : 'No'],
            ]
        );

        $this->newLine();

        // DNS
        $this->line('<fg=cyan>DNS Configuration:</>');
        table(
            ['Field', 'Value'],
            [
                ['Config Type', $domain->dns_config_type ?? 'N/A'],
                ['DNS Management', $domain->dns_management_enabled ? 'Yes' : 'No'],
                ['Email Forwarding', $domain->email_forwarding_enabled ? 'Yes' : 'No'],
            ]
        );

        // Nameservers
        if ($domain->nameservers) {
            $this->newLine();
            $this->line('<fg=cyan>Nameservers:</>');
            foreach ($domain->nameservers as $ns) {
                $this->line("  • {$ns}");
            }
        }

        // Glue Records
        if ($domain->glueRecords->isNotEmpty()) {
            $this->newLine();
            $this->line('<fg=cyan>Glue Records:</>');

            $glueData = $domain->glueRecords->map(function ($glue) {
                $status = $glue->is_stale
                    ? '<fg=yellow>STALE</>'
                    : ($glue->is_synced ? 'Active' : 'Pending');

                return [
                    $glue->hostname,
                    implode(', ', $glue->ip_addresses),
                    $status,
                ];
            })->toArray();

            table(['Hostname', 'IP Addresses', 'Status'], $glueData);

            // Show warning for stale records
            if ($domain->glueRecords->where('is_stale', true)->isNotEmpty()) {
                $this->newLine();
                $this->warn('⚠  STALE glue records detected!');
                $this->line('These exist in Synergy Wholesale but not in the registry.');
                $this->line('Delete manually via: https://manage.synergywholesale.com/');
            }
        }

        // DNSSEC
        if ($domain->ds_data) {
            $this->newLine();
            $this->line('<fg=cyan>DNSSEC:</>');
            $this->line('  DS Data: Present');
        }

        // ICANN
        if ($domain->icann_verification_date_end || $domain->icann_status) {
            $this->newLine();
            $this->line('<fg=cyan>ICANN:</>');
            table(
                ['Field', 'Value'],
                [
                    ['Status', $domain->icann_status ?? 'N/A'],
                    ['Verification End', $domain->icann_verification_date_end?->format('Y-m-d H:i:s') ?? 'N/A'],
                ]
            );
        }

        // Metadata
        if ($domain->metadata->isNotEmpty()) {
            $this->newLine();
            $this->line('<fg=cyan>Metadata:</>');
            $metaData = $domain->metadata->map(function ($meta) {
                return [$meta->key, $meta->value];
            })->toArray();

            table(['Key', 'Value'], $metaData);
        }

        // Additional Fields (.au, etc.)
        if ($domain->additionalFields->isNotEmpty()) {
            $this->newLine();
            $this->line('<fg=cyan>Additional Fields:</>');
            $fieldsData = $domain->additionalFields->map(function ($field) {
                return [$field->field_name, $field->field_value];
            })->toArray();

            table(['Field', 'Value'], $fieldsData);
        }

        return 0;
    }

    /**
     * List domains based on filters
     */
    private function listDomains(?string $search = null): int
    {
        $query = SwDomain::query();

        // Apply search filter (convert to SQL LIKE pattern)
        if ($search) {
            // Convert wildcards (* or %) to SQL LIKE pattern
            $likePattern = str_replace('*', '%', $search);

            // If no wildcards present, search for domains containing the pattern
            if (! str_contains($likePattern, '%')) {
                $likePattern = '%'.$likePattern.'%';
            }

            $query->where('domain_name', 'LIKE', $likePattern);
        }

        // Apply filters
        $status = $this->option('status');
        if ($status && $status !== 'all') {
            $query->where('lifecycle_status', $status);
        }

        if ($expiring = $this->option('expiring')) {
            $query->where('lifecycle_status', 'active')
                ->whereNotNull('domain_expiry')
                ->whereRaw('julianday(domain_expiry) - julianday(?) <= ?', [now(), (int) $expiring])
                ->orderBy('domain_expiry');
        }

        if ($this->option('glue')) {
            $query->has('glueRecords');
        }

        if ($tld = $this->option('tld')) {
            $query->where('domain_name', 'like', "%.{$tld}");
        }

        // Get results
        $limit = (int) $this->option('limit');
        $domains = $query->orderBy('domain_name')->limit($limit)->get();

        if ($domains->isEmpty()) {
            $this->warn('No domains found matching criteria');

            return 0;
        }

        if ($this->option('json')) {
            $this->line(json_encode($domains->toArray(), JSON_PRETTY_PRINT));

            return 0;
        }

        // Nameserver view (--ns option)
        if ($this->option('ns')) {
            return $this->displayNameservers($domains);
        }

        // Plain text output (script-friendly)
        if ($this->option('text')) {
            foreach ($domains as $domain) {
                $expiryDays = $domain->domain_expiry
                    ? (int) now()->diffInDays($domain->domain_expiry, false)
                    : null;

                printf(
                    "%-24s\t%s\t%s\t%s\t%d\t%s\n",
                    $domain->domain_name,
                    $domain->domain_expiry?->format('Y-m-d') ?? 'N/A',
                    $expiryDays !== null ? ($expiryDays >= 0 ? $expiryDays : 'EXPIRED') : 'N/A',
                    $domain->auto_renew ? 'Yes' : 'No',
                    $domain->glueRecords->count(),
                    $domain->lifecycle_status
                );
            }

            return 0;
        }

        // Prepare table data
        $tableData = $domains->map(function ($domain) {
            $expiryDays = $domain->domain_expiry
                ? (int) now()->diffInDays($domain->domain_expiry, false)
                : null;

            return [
                $domain->domain_name,
                $domain->domain_expiry?->format('Y-m-d') ?? 'N/A',
                $expiryDays !== null ? ($expiryDays >= 0 ? str_pad($expiryDays, 4, ' ', STR_PAD_LEFT) : 'EXPIRED') : '  N/A',
                $domain->auto_renew ? 'Yes' : 'No',
                $domain->glueRecords->count(),
                $domain->lifecycle_status,
            ];
        })->toArray();

        table(
            ['Domain', 'Expiry', 'Days', 'Renew', 'Glue', 'Status'],
            $tableData
        );

        return 0;
    }

    /**
     * Display nameservers for domains in 4-column format
     */
    private function displayNameservers($domains): int
    {
        $tableData = $domains->map(function ($domain) {
            $ns = $domain->nameservers ?? [];

            return [
                $domain->domain_name,
                $ns[0] ?? '',
                $ns[1] ?? '',
                $ns[2] ?? '',
            ];
        })->toArray();

        table(
            ['Domain', 'NS1', 'NS2', 'NS3'],
            $tableData
        );

        return 0;
    }

    /**
     * Sync all domains from Synergy Wholesale API to local cache
     */
    private function syncDomains(SynergyWholesaleService $sw): int
    {
        $this->info('Syncing all domains from Synergy Wholesale API to local cache...');
        $this->warn('This will take several minutes for 400+ domains...');
        $this->newLine();

        $progressBar = $this->output->createProgressBar(1);
        $progressBar->start();

        $stats = SwDomain::syncAllFromAPI(
            $sw,
            function ($current, $total, $domain) use ($progressBar) {
                if ($current === 1) {
                    $progressBar->setMaxSteps($total);
                }
                $progressBar->setMessage($domain->domain_name);
                $progressBar->advance();
            }
        );

        $progressBar->finish();
        $this->newLine(2);

        $this->info('✓ Sync complete!');
        $this->line("  Active domains: {$stats['total']}");
        $this->line("  Synced: {$stats['synced']}");
        if ($stats['errors'] > 0) {
            $this->line("  Errors: {$stats['errors']}");
        }

        return 0;
    }
}
