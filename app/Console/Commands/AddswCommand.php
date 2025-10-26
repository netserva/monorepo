<?php

namespace App\Console\Commands;

use App\Models\SwDomain;
use App\Services\SynergyWholesaleService;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class AddswCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'addsw {domain : Domain name to register}
                            {--years=1 : Registration period in years}
                            {--auto-renew : Enable auto-renewal}
                            {--id-protect : Enable ID protection}
                            {--ns=* : Nameservers (can specify multiple)}
                            {--sync : Sync domain info after registration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register a new domain via Synergy Wholesale';

    /**
     * Execute the console command.
     */
    public function handle(SynergyWholesaleService $sw): int
    {
        $domainName = $this->argument('domain');

        // Check if domain exists locally
        if (SwDomain::where('domain_name', $domainName)->exists()) {
            $this->error("Domain already exists in local database: {$domainName}");
            $this->info('Use "chsw" to modify existing domain or "php artisan shsw --sync" to update from API');

            return 1;
        }

        // Validate domain name
        if (! $this->isValidDomain($domainName)) {
            $this->error("Invalid domain name: {$domainName}");

            return 1;
        }

        $this->info("Registering domain: {$domainName}");
        $this->newLine();

        // Check availability first
        $this->line('Checking availability...');

        try {
            $availability = $sw->checkDomainAvailability($domainName);

            if (! $availability['available']) {
                $this->error('Domain is not available for registration');
                $this->line("Status: {$availability['status']}");

                return 1;
            }

            $this->info('✓ Domain is available');

            if (isset($availability['price'])) {
                $this->line("Price: \${$availability['price']}");
            }

            $this->newLine();
        } catch (\Exception $e) {
            $this->error("Failed to check availability: {$e->getMessage()}");

            return 1;
        }

        // Gather registration details
        $years = (int) $this->option('years');
        $autoRenew = $this->option('auto-renew');
        $idProtect = $this->option('id-protect');
        $nameservers = $this->option('ns');

        // Interactive prompts if not provided
        if (! $autoRenew && ! $this->option('no-interaction')) {
            $autoRenew = confirm(
                label: 'Enable auto-renewal?',
                default: false
            );
        }

        if (! $idProtect && ! $this->option('no-interaction')) {
            $idProtect = confirm(
                label: 'Enable ID protection (WHOIS privacy)?',
                default: true
            );
        }

        if (empty($nameservers) && ! $this->option('no-interaction')) {
            $nsChoice = select(
                label: 'Nameserver configuration',
                options: [
                    'default' => 'Use SW default nameservers',
                    'custom' => 'Specify custom nameservers',
                    'none' => 'No nameservers (configure later)',
                ],
                default: 'default'
            );

            if ($nsChoice === 'custom') {
                $this->line('Enter nameservers (one per line, empty line to finish):');
                while (true) {
                    $ns = text('Nameserver:');
                    if (empty($ns)) {
                        break;
                    }
                    $nameservers[] = $ns;
                }
            }
        }

        // Confirm registration
        $this->newLine();
        $this->info('Registration Summary:');
        $this->line("Domain: {$domainName}");
        $this->line("Period: {$years} year(s)");
        $this->line('Auto-Renew: '.($autoRenew ? 'Yes' : 'No'));
        $this->line('ID Protection: '.($idProtect ? 'Yes' : 'No'));
        if (! empty($nameservers)) {
            $this->line('Nameservers: '.implode(', ', $nameservers));
        }
        $this->newLine();

        if (! $this->option('no-interaction')) {
            if (! confirm('Proceed with registration?', true)) {
                $this->warn('Registration cancelled');

                return 0;
            }
        }

        // Register domain
        try {
            $this->line('Registering domain...');

            $params = [
                'years' => $years,
                'autoRenew' => $autoRenew,
                'idProtect' => $idProtect,
            ];

            if (! empty($nameservers)) {
                $params['nameServers'] = $nameservers;
            }

            $result = $sw->registerDomain($domainName, $params);

            $this->info('✓ Domain registered successfully!');

            if (isset($result['orderId'])) {
                $this->line("Order ID: {$result['orderId']}");
            }

            // Sync domain info if requested
            if ($this->option('sync')) {
                $this->newLine();
                $this->line('Syncing domain info...');

                try {
                    $domain = SwDomain::syncFromAPI($domainName, $sw);
                    $this->info("✓ Domain synced: {$domain->domain_name}");
                    $this->line("Status: {$domain->lifecycle_status}");
                    $this->line("ROID: {$domain->domain_roid}");
                } catch (\Exception $e) {
                    $this->warn("Warning: Failed to sync domain info: {$e->getMessage()}");
                    $this->line('You can sync later with: php artisan shsw --sync');
                }
            } else {
                $this->newLine();
                $this->line('To sync domain info, run: php artisan shsw --sync');
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Registration failed: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Validate domain name format
     */
    private function isValidDomain(string $domain): bool
    {
        $pattern = '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i';

        return (bool) preg_match($pattern, $domain);
    }
}
