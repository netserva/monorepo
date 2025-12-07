<?php

declare(strict_types=1);

namespace NetServa\Crm\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Crm\CrmServiceProvider;
use NetServa\Crm\Services\ClientManagementService;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

class ChcrmCommand extends Command
{
    protected $signature = 'chcrm
        {identifier : Client ID, slug, or email}
        {--name= : Update client name}
        {--email= : Update email address}
        {--first-name= : Update first name}
        {--last-name= : Update last name}
        {--company-name= : Update company name}
        {--abn= : Update Australian Business Number}
        {--acn= : Update Australian Company Number}
        {--home-phone= : Update home phone number}
        {--work-phone= : Update work phone number}
        {--address= : Update street address}
        {--city= : Update city}
        {--state= : Update state/territory}
        {--postcode= : Update postcode}
        {--country= : Update country code}
        {--status= : Update status (active, prospect, suspended, cancelled)}
        {--notes= : Update notes}
        {--external-id= : Update external system ID}
        {--assign-vsite= : Assign VSite by ID to this client}
        {--unassign-vsite= : Unassign VSite by ID from this client}
        {--assign-domain= : Assign domain by ID to this client}
        {--unassign-domain= : Unassign domain by ID from this client}';

    protected $description = 'Update a client or assign/unassign resources';

    public function __construct(protected ClientManagementService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $identifier = $this->argument('identifier');

        // Handle VSite assignment
        if ($this->option('assign-vsite')) {
            return $this->assignVsite($identifier, (int) $this->option('assign-vsite'));
        }

        if ($this->option('unassign-vsite')) {
            return $this->unassignVsite((int) $this->option('unassign-vsite'));
        }

        // Handle domain assignment
        if ($this->option('assign-domain')) {
            return $this->assignDomain($identifier, (int) $this->option('assign-domain'));
        }

        if ($this->option('unassign-domain')) {
            return $this->unassignDomain((int) $this->option('unassign-domain'));
        }

        // Build updates array from options
        $updates = [];

        $fieldMap = [
            'name' => 'name',
            'email' => 'email',
            'first-name' => 'first_name',
            'last-name' => 'last_name',
            'company-name' => 'company_name',
            'abn' => 'abn',
            'acn' => 'acn',
            'home-phone' => 'home_phone',
            'work-phone' => 'work_phone',
            'address' => 'address_line_1',
            'city' => 'city',
            'state' => 'state',
            'postcode' => 'postcode',
            'country' => 'country',
            'status' => 'status',
            'notes' => 'notes',
            'external-id' => 'external_id',
        ];

        foreach ($fieldMap as $option => $field) {
            $value = $this->option($option);
            if ($value !== null) {
                $updates[$field] = $value;
            }
        }

        if (empty($updates)) {
            error('No updates specified. Use --help to see available options.');

            return self::FAILURE;
        }

        // Validate status if provided
        if (isset($updates['status']) && ! in_array($updates['status'], ['active', 'prospect', 'suspended', 'cancelled'])) {
            error('Invalid status. Must be one of: active, prospect, suspended, cancelled');

            return self::FAILURE;
        }

        $result = $this->service->update($identifier, $updates);

        if (! $result['success']) {
            error($result['message']);

            return self::FAILURE;
        }

        $client = $result['client'];
        info("Client updated: {$client->name}");

        // Show updated fields
        $this->table(
            ['Field', 'New Value'],
            collect($updates)->map(fn ($v, $k) => [$k, $v])->values()->toArray()
        );

        return self::SUCCESS;
    }

    protected function assignVsite(string $identifier, int $vsiteId): int
    {
        if (! CrmServiceProvider::hasFleetIntegration()) {
            error('Fleet integration is not available. Install netserva/fleet to enable VSite assignment.');

            return self::FAILURE;
        }

        // Get client ID
        $findResult = $this->service->find($identifier);
        if (! $findResult['success']) {
            error($findResult['message']);

            return self::FAILURE;
        }

        $result = $this->service->assignVsite($findResult['client']->id, $vsiteId);

        if (! $result['success']) {
            error($result['message']);

            return self::FAILURE;
        }

        info($result['message']);

        return self::SUCCESS;
    }

    protected function unassignVsite(int $vsiteId): int
    {
        if (! CrmServiceProvider::hasFleetIntegration()) {
            error('Fleet integration is not available.');

            return self::FAILURE;
        }

        $result = $this->service->unassignVsite($vsiteId);

        if (! $result['success']) {
            error($result['message']);

            return self::FAILURE;
        }

        info($result['message']);

        return self::SUCCESS;
    }

    protected function assignDomain(string $identifier, int $domainId): int
    {
        if (! CrmServiceProvider::hasDomainIntegration()) {
            error('Domain integration is not available. SwDomain model not found.');

            return self::FAILURE;
        }

        // Get client ID
        $findResult = $this->service->find($identifier);
        if (! $findResult['success']) {
            error($findResult['message']);

            return self::FAILURE;
        }

        $result = $this->service->assignDomain($findResult['client']->id, $domainId);

        if (! $result['success']) {
            error($result['message']);

            return self::FAILURE;
        }

        info($result['message']);

        return self::SUCCESS;
    }

    protected function unassignDomain(int $domainId): int
    {
        if (! CrmServiceProvider::hasDomainIntegration()) {
            error('Domain integration is not available.');

            return self::FAILURE;
        }

        $result = $this->service->unassignDomain($domainId);

        if (! $result['success']) {
            error($result['message']);

            return self::FAILURE;
        }

        info($result['message']);

        return self::SUCCESS;
    }
}
