<?php

declare(strict_types=1);

namespace NetServa\Crm\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Crm\Services\ClientManagementService;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\text;

class AddcrmCommand extends Command
{
    protected $signature = 'addcrm
        {name? : Client display name}
        {--email= : Primary email address}
        {--first-name= : First name}
        {--last-name= : Last name}
        {--company-name= : Company name (optional, for business clients)}
        {--abn= : Australian Business Number}
        {--acn= : Australian Company Number}
        {--home-phone= : Home phone number}
        {--work-phone= : Work phone number}
        {--address= : Street address}
        {--city= : City}
        {--state= : State/Territory}
        {--postcode= : Postcode}
        {--country=AU : Country code}
        {--status=active : Initial status (active, prospect)}
        {--notes= : Additional notes}
        {--external-id= : External system ID}
        {--dry-run : Show what would be created without saving}';

    protected $description = 'Add a new client (CRUD: addcrm, shcrm, chcrm, delcrm)';

    public function __construct(protected ClientManagementService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // Build client data
        $data = [
            'status' => $this->option('status'),
            'country' => $this->option('country'),
        ];

        // Get name or individual fields
        if ($this->argument('name')) {
            $data['name'] = $this->argument('name');
        }

        // Personal details
        $data['first_name'] = $this->option('first-name');
        $data['last_name'] = $this->option('last-name');

        // Business details (optional)
        $data['company_name'] = $this->option('company-name');
        $data['abn'] = $this->option('abn');
        $data['acn'] = $this->option('acn');

        // Prompt for first name if no name provided
        if (empty($data['name']) && empty($data['first_name']) && empty($data['company_name']) && ! $this->option('no-interaction')) {
            $data['first_name'] = text(
                label: 'First name',
                required: true
            );
        }

        if (empty($data['name']) && empty($data['last_name']) && ! $this->option('no-interaction')) {
            $data['last_name'] = text(
                label: 'Last name',
                required: false
            );
        }

        // Email - required
        $data['email'] = $this->option('email');
        if (! $data['email'] && ! $this->option('no-interaction')) {
            $data['email'] = text(
                label: 'Email address',
                required: true,
                validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Invalid email format'
            );
        }

        // Optional fields
        if ($this->option('home-phone')) {
            $data['home_phone'] = $this->option('home-phone');
        }
        if ($this->option('work-phone')) {
            $data['work_phone'] = $this->option('work-phone');
        }
        if ($this->option('address')) {
            $data['address_line_1'] = $this->option('address');
        }
        if ($this->option('city')) {
            $data['city'] = $this->option('city');
        }
        if ($this->option('state')) {
            $data['state'] = $this->option('state');
        }
        if ($this->option('postcode')) {
            $data['postcode'] = $this->option('postcode');
        }
        if ($this->option('notes')) {
            $data['notes'] = $this->option('notes');
        }
        if ($this->option('external-id')) {
            $data['external_id'] = $this->option('external-id');
        }

        // Filter out null values
        $data = array_filter($data, fn ($v) => $v !== null);

        // Dry run - just show what would be created
        if ($this->option('dry-run')) {
            info('Dry run - would create client with:');
            $this->table(['Field', 'Value'], collect($data)->map(fn ($v, $k) => [$k, is_array($v) ? json_encode($v) : $v])->values()->toArray());

            return self::SUCCESS;
        }

        // Create client
        $result = $this->service->create($data);

        if (! $result['success']) {
            error($result['message']);

            return self::FAILURE;
        }

        $client = $result['client'];
        info("Client created: {$client->name} (#{$client->id})");
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $client->id],
                ['Name', $client->name],
                ['Slug', $client->slug],
                ['Email', $client->email],
                ['Status', $client->status],
                ['Company', $client->company_name ?: '-'],
            ]
        );

        return self::SUCCESS;
    }
}
