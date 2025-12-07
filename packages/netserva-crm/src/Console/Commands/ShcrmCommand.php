<?php

declare(strict_types=1);

namespace NetServa\Crm\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Crm\CrmServiceProvider;
use NetServa\Crm\Services\ClientManagementService;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

class ShcrmCommand extends Command
{
    protected $signature = 'shcrm
        {identifier? : Client ID, slug, or email (omit to list all)}
        {--status= : Filter by status (active, prospect, suspended, cancelled)}
        {--business : Filter to business clients only}
        {--personal : Filter to personal clients only}
        {--search= : Search by name, email, or company}
        {--with-vsites : Include VSites in output}
        {--with-domains : Include domains in output}
        {--limit=25 : Maximum number of clients to show}
        {--stats : Show statistics only}';

    protected $description = 'Show client(s) - list all or show details for one';

    public function __construct(protected ClientManagementService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // Stats only mode
        if ($this->option('stats')) {
            return $this->showStats();
        }

        // Single client detail view
        if ($this->argument('identifier')) {
            return $this->showClient($this->argument('identifier'));
        }

        // List all clients with filters
        return $this->listClients();
    }

    protected function showStats(): int
    {
        $stats = $this->service->getStats();

        info('Client Statistics');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Clients', $stats['total']],
                ['Active', $stats['active']],
                ['Prospects', $stats['prospect']],
                ['Suspended', $stats['suspended']],
                ['Cancelled', $stats['cancelled']],
                ['Business', $stats['business']],
                ['Personal', $stats['personal']],
            ]
        );

        note('Integration Status');
        $this->table(
            ['Integration', 'Available'],
            [
                ['Fleet', $stats['integrations']['fleet'] ? 'Yes' : 'No'],
                ['Domains', $stats['integrations']['domains'] ? 'Yes' : 'No'],
                ['Core', $stats['integrations']['core'] ? 'Yes' : 'No'],
            ]
        );

        return self::SUCCESS;
    }

    protected function showClient(string|int $identifier): int
    {
        $result = $this->service->show($identifier, [
            'with_vsites' => $this->option('with-vsites'),
            'with_domains' => $this->option('with-domains'),
        ]);

        if (! $result['success']) {
            error($result['message']);

            return self::FAILURE;
        }

        $client = $result['client'];

        info("Client: {$client->name}");

        // Basic info
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $client->id],
                ['Name', $client->name],
                ['Slug', $client->slug],
                ['Status', ucfirst($client->status)],
                ['Email', $client->email],
                ['Home Phone', $client->home_phone ?: '-'],
                ['Work Phone', $client->work_phone ?: '-'],
            ]
        );

        // Personal details
        if ($client->first_name || $client->last_name) {
            $this->table(
                ['Personal Details', 'Value'],
                [
                    ['First Name', $client->first_name ?: '-'],
                    ['Last Name', $client->last_name ?: '-'],
                ]
            );
        }

        // Business details (if applicable)
        if ($client->company_name || $client->abn || $client->acn) {
            $this->table(
                ['Business Details', 'Value'],
                [
                    ['Company Name', $client->company_name ?: '-'],
                    ['ABN', $client->formatted_abn ?: '-'],
                    ['ACN', $client->acn ?: '-'],
                ]
            );
        }

        // Address
        if ($client->full_address) {
            note('Address: '.$client->full_address);
        }

        // Stats
        note('Resource Counts');
        $this->table(
            ['Resource', 'Count', 'Available'],
            [
                ['VSites', $result['stats']['vsites_count'], $result['integrations']['fleet'] ? 'Yes' : 'No'],
                ['VNodes', $result['stats']['vnodes_count'], $result['integrations']['fleet'] ? 'Yes' : 'No'],
                ['VHosts', $result['stats']['vhosts_count'], $result['integrations']['fleet'] ? 'Yes' : 'No'],
                ['Domains', $result['stats']['domains_count'], $result['integrations']['domains'] ? 'Yes' : 'No'],
            ]
        );

        // VSites if requested
        if ($this->option('with-vsites') && isset($result['vsites']) && $result['vsites']->isNotEmpty()) {
            note('VSites');
            $this->table(
                ['ID', 'Name', 'Provider', 'Status'],
                $result['vsites']->map(fn ($v) => [
                    $v->id,
                    $v->name,
                    $v->provider ?? '-',
                    $v->status ?? 'active',
                ])->toArray()
            );
        }

        // Domains if requested
        if ($this->option('with-domains') && isset($result['domains']) && $result['domains']->isNotEmpty()) {
            note('Domains');
            $this->table(
                ['ID', 'Domain', 'Status', 'Expiry'],
                $result['domains']->map(fn ($d) => [
                    $d->id,
                    $d->domain_name,
                    $d->lifecycle_status ?? '-',
                    $d->domain_expiry?->format('Y-m-d') ?? '-',
                ])->toArray()
            );
        }

        // Notes
        if ($client->notes) {
            note('Notes: '.$client->notes);
        }

        // External ID
        if ($client->external_id) {
            note('External ID: '.$client->external_id);
        }

        return self::SUCCESS;
    }

    protected function listClients(): int
    {
        $filters = [
            'with_counts' => true,
            'limit' => (int) $this->option('limit'),
        ];

        if ($this->option('status')) {
            $filters['status'] = $this->option('status');
        }

        if ($this->option('business')) {
            $filters['business'] = true;
        } elseif ($this->option('personal')) {
            $filters['business'] = false;
        }

        if ($this->option('search')) {
            $filters['search'] = $this->option('search');
        }

        $clients = $this->service->list($filters);

        if ($clients->isEmpty()) {
            info('No clients found.');

            return self::SUCCESS;
        }

        info("Clients ({$clients->count()})");

        $hasFleet = CrmServiceProvider::hasFleetIntegration();
        $hasDomains = CrmServiceProvider::hasDomainIntegration();

        $headers = ['ID', 'Name', 'Company', 'Email', 'Status'];
        if ($hasFleet) {
            $headers[] = 'VSites';
        }
        if ($hasDomains) {
            $headers[] = 'Domains';
        }

        $rows = $clients->map(function ($c) use ($hasFleet, $hasDomains) {
            $row = [
                $c->id,
                $c->name,
                $c->company_name ?: '-',
                $c->email,
                ucfirst($c->status),
            ];

            if ($hasFleet) {
                $row[] = $c->vsites_count ?? 0;
            }
            if ($hasDomains) {
                $row[] = $c->domains_count ?? 0;
            }

            return $row;
        })->toArray();

        $this->table($headers, $rows);

        return self::SUCCESS;
    }
}
