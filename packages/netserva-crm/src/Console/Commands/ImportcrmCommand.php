<?php

declare(strict_types=1);

namespace NetServa\Crm\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use NetServa\Crm\Models\CrmClient;

class ImportcrmCommand extends Command
{
    protected $signature = 'importcrm
                            {--limit=0 : Limit number of records to import (0 = all)}
                            {--dry-run : Show what would be imported without saving}
                            {--update : Update existing records matched by external_id}
                            {--domains : Also import domain ownership and link to clients}';

    protected $description = 'Import clients from WHMCS API (with optional domain ownership)';

    private string $apiUrl;

    private string $apiIdentifier;

    private string $apiSecret;

    public function handle(): int
    {
        $this->apiUrl = config('services.whmcs.api_url', env('WHMCS_API_URL'));
        $this->apiIdentifier = config('services.whmcs.api_identifier', env('WHMCS_API_IDENTIFIER'));
        $this->apiSecret = config('services.whmcs.api_secret', env('WHMCS_API_SECRET'));

        if (! $this->apiUrl || ! $this->apiIdentifier || ! $this->apiSecret) {
            $this->error('WHMCS API credentials not configured. Add to .env:');
            $this->line('WHMCS_API_URL=https://your-whmcs.com/includes/api.php');
            $this->line('WHMCS_API_IDENTIFIER=your-identifier');
            $this->line('WHMCS_API_SECRET=your-secret');

            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run');
        $update = $this->option('update');
        $limit = (int) $this->option('limit');

        $this->info('Fetching clients from WHMCS...');

        $clients = $this->fetchAllClients($limit);

        if (empty($clients)) {
            $this->warn('No clients found or API error occurred.');

            return self::FAILURE;
        }

        $this->info(sprintf('Found %d clients to process.', count($clients)));

        if ($dryRun) {
            $this->warn('DRY RUN - No changes will be made.');
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;

        $progressBar = $this->output->createProgressBar(count($clients));
        $progressBar->start();

        foreach ($clients as $whmcsClient) {
            $externalId = 'whmcs:'.$whmcsClient['id'];

            $existing = CrmClient::where('external_id', $externalId)->first();

            if ($existing && ! $update) {
                $skipped++;
                $progressBar->advance();

                continue;
            }

            $data = $this->mapClientData($whmcsClient, $externalId);

            if (! $dryRun) {
                if ($existing && $update) {
                    $existing->update($data);
                    $updated++;
                } else {
                    CrmClient::create($data);
                    $imported++;
                }
            } else {
                if ($existing) {
                    $updated++;
                } else {
                    $imported++;
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info(sprintf(
            'Complete: %d imported, %d updated, %d skipped',
            $imported,
            $updated,
            $skipped
        ));

        if ($dryRun) {
            $this->warn('This was a dry run. Run without --dry-run to apply changes.');
        }

        // Import domain ownership if requested
        if ($this->option('domains') && ! $dryRun) {
            $this->newLine();
            $this->importDomainOwnership();
        }

        return self::SUCCESS;
    }

    /**
     * Import domain ownership from WHMCS and link to CRM clients
     */
    private function importDomainOwnership(): void
    {
        if (! class_exists(\App\Models\SwDomain::class)) {
            $this->warn('SwDomain model not found - skipping domain ownership import.');

            return;
        }

        $this->info('Fetching domain ownership from WHMCS...');

        // Get all WHMCS client IDs mapped to CRM client IDs
        $clientMap = CrmClient::whereNotNull('external_id')
            ->where('external_id', 'like', 'whmcs:%')
            ->pluck('id', 'external_id')
            ->mapWithKeys(fn ($crmId, $extId) => [(int) str_replace('whmcs:', '', $extId) => $crmId])
            ->toArray();

        if (empty($clientMap)) {
            $this->warn('No WHMCS clients found in CRM.');

            return;
        }

        $this->line(sprintf('  Found %d WHMCS clients in CRM', count($clientMap)));

        // Fetch all domains from WHMCS
        $domains = $this->fetchAllDomains();

        if (empty($domains)) {
            $this->warn('No domains found in WHMCS.');

            return;
        }

        $this->line(sprintf('  Found %d domains in WHMCS', count($domains)));

        $linked = 0;
        $notFound = 0;
        $noClient = 0;

        $progressBar = $this->output->createProgressBar(count($domains));
        $progressBar->start();

        foreach ($domains as $whmcsDomain) {
            $domainName = strtolower($whmcsDomain['domainname'] ?? '');
            $whmcsClientId = (int) ($whmcsDomain['userid'] ?? 0);

            if (! $domainName) {
                $progressBar->advance();

                continue;
            }

            // Find matching domain in sw_domains
            $swDomain = \App\Models\SwDomain::where('domain_name', $domainName)->first();

            if (! $swDomain) {
                $notFound++;
                $progressBar->advance();

                continue;
            }

            // Find CRM client for this WHMCS client
            $crmClientId = $clientMap[$whmcsClientId] ?? null;

            if (! $crmClientId) {
                $noClient++;
                $progressBar->advance();

                continue;
            }

            // Link domain to client
            $swDomain->update(['customer_id' => $crmClientId]);
            $linked++;

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info(sprintf(
            'Domain linking complete: %d linked, %d not in sw_domains, %d no matching client',
            $linked,
            $notFound,
            $noClient
        ));
    }

    /**
     * Fetch all domains from WHMCS API
     */
    private function fetchAllDomains(): array
    {
        $domains = [];
        $offset = 0;
        $perPage = 250;

        do {
            $response = $this->callApi('GetClientsDomains', [
                'limitstart' => $offset,
                'limitnum' => $perPage,
            ]);

            if (! isset($response['domains']['domain'])) {
                break;
            }

            $batch = $response['domains']['domain'];
            // Handle single result (not wrapped in array)
            if (isset($batch['id'])) {
                $batch = [$batch];
            }
            $domains = array_merge($domains, $batch);

            $this->line(sprintf('  Fetched %d domains (total: %d)', count($batch), count($domains)));

            $offset += $perPage;

        } while (count($batch) === $perPage);

        return $domains;
    }

    private function fetchAllClients(int $limit = 0): array
    {
        $clients = [];
        $offset = 0;
        $perPage = 250;

        do {
            $response = $this->callApi('GetClients', [
                'limitstart' => $offset,
                'limitnum' => $perPage,
            ]);

            if (! isset($response['clients']['client'])) {
                break;
            }

            $batch = $response['clients']['client'];
            $clients = array_merge($clients, $batch);

            $this->line(sprintf('  Fetched %d clients (total: %d)', count($batch), count($clients)));

            $offset += $perPage;

            if ($limit > 0 && count($clients) >= $limit) {
                $clients = array_slice($clients, 0, $limit);

                break;
            }

        } while (count($batch) === $perPage);

        return $clients;
    }

    private function callApi(string $action, array $params = []): array
    {
        $response = Http::asForm()->post($this->apiUrl, array_merge([
            'action' => $action,
            'identifier' => $this->apiIdentifier,
            'secret' => $this->apiSecret,
            'responsetype' => 'json',
        ], $params));

        if (! $response->successful()) {
            $this->error('API request failed: '.$response->status());

            return [];
        }

        $data = $response->json();

        if (($data['result'] ?? '') !== 'success') {
            $this->error('API error: '.($data['message'] ?? 'Unknown error'));

            return [];
        }

        return $data;
    }

    private function mapClientData(array $whmcs, string $externalId): array
    {
        $status = match (strtolower($whmcs['status'] ?? 'active')) {
            'active' => 'active',
            'inactive' => 'suspended',
            'closed' => 'cancelled',
            default => 'active',
        };

        // Decode HTML entities from WHMCS data (handles &amp; &#039; etc.)
        $firstName = $this->decodeField($whmcs['firstname'] ?? '');
        $lastName = $this->decodeField($whmcs['lastname'] ?? '');
        $companyName = $this->decodeField($whmcs['companyname'] ?? '');

        // Name is always first + last name, never company
        $name = trim("$firstName $lastName") ?: $firstName ?: $lastName;

        return [
            'name' => $name ?: 'Unknown',
            'first_name' => $firstName ?: null,
            'last_name' => $lastName ?: null,
            'company_name' => $companyName ?: null,
            'email' => $whmcs['email'] ?? null,
            'home_phone' => $whmcs['phonenumber'] ?? null,
            'address_line_1' => $this->decodeField($whmcs['address1'] ?? '') ?: null,
            'address_line_2' => $this->decodeField($whmcs['address2'] ?? '') ?: null,
            'city' => $this->decodeField($whmcs['city'] ?? '') ?: null,
            'state' => $this->decodeField($whmcs['state'] ?? '') ?: null,
            'postcode' => $whmcs['postcode'] ?? null,
            'country' => $this->mapCountry($whmcs['countrycode'] ?? $whmcs['country'] ?? 'AU'),
            'status' => $status,
            'external_id' => $externalId,
        ];
    }

    /**
     * Decode HTML entities from WHMCS data (handles &amp; &#039; etc.)
     */
    private function decodeField(string $value): string
    {
        return html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function mapCountry(string $country): string
    {
        $map = [
            'Australia' => 'AU',
            'New Zealand' => 'NZ',
            'United States' => 'US',
            'United Kingdom' => 'GB',
            'Canada' => 'CA',
        ];

        if (strlen($country) === 2) {
            return strtoupper($country);
        }

        return $map[$country] ?? 'AU';
    }
}
