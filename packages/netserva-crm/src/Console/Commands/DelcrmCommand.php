<?php

declare(strict_types=1);

namespace NetServa\Crm\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Crm\Services\ClientManagementService;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class DelcrmCommand extends Command
{
    protected $signature = 'delcrm
        {identifier : Client ID, slug, or email}
        {--force : Force delete even if client has assigned resources}
        {--yes : Skip confirmation prompt}';

    protected $description = 'Delete a client (soft delete)';

    public function __construct(protected ClientManagementService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $identifier = $this->argument('identifier');

        // Find client first to show what we're deleting
        $findResult = $this->service->find($identifier);
        if (! $findResult['success']) {
            error($findResult['message']);

            return self::FAILURE;
        }

        $client = $findResult['client'];

        // Show client info
        info("Client to delete: {$client->name}");
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $client->id],
                ['Name', $client->name],
                ['Company', $client->company_name ?: '-'],
                ['Email', $client->email],
                ['Status', ucfirst($client->status)],
                ['VSites', $client->vsite_count],
                ['Domains', $client->domain_count],
            ]
        );

        // Check for assigned resources
        $hasResources = $client->vsite_count > 0 || $client->domain_count > 0;

        if ($hasResources && ! $this->option('force')) {
            warning("Client has {$client->vsite_count} VSite(s) and {$client->domain_count} domain(s) assigned.");
            warning('Use --force to delete anyway (resources will be unassigned).');

            return self::FAILURE;
        }

        // Confirmation
        if (! $this->option('yes') && ! $this->option('no-interaction')) {
            $confirmed = confirm(
                label: "Delete client '{$client->name}'?",
                default: false,
                hint: $hasResources ? 'Resources will be unassigned from this client' : null
            );

            if (! $confirmed) {
                info('Deletion cancelled.');

                return self::SUCCESS;
            }
        }

        // Delete client
        $result = $this->service->delete($identifier, [
            'force' => $this->option('force'),
        ]);

        if (! $result['success']) {
            error($result['message']);
            if (isset($result['hint'])) {
                warning($result['hint']);
            }

            return self::FAILURE;
        }

        info($result['message']);

        return self::SUCCESS;
    }
}
