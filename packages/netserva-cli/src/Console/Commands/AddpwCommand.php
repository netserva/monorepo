<?php

namespace NetServa\Cli\Console\Commands;

use NetServa\Cli\Services\VHostResolverService;
use NetServa\Fleet\Models\FleetVHost;
use NetServa\Fleet\Models\FleetVHostCredential;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Add Password/Credential Command - NetServa 3.0 Database-First
 *
 * Create new credentials for VHosts (email accounts, admin logins, etc.)
 * Smart VHost resolution: addpw cloud.goldcoast.org mail admin@cloud.goldcoast.org
 */
class AddpwCommand extends BaseNetServaCommand
{
    protected $signature = 'addpw {domain_or_vnode? : Domain name or VNode (smart detection)}
                           {service_or_domain? : Service type or Domain (if first arg is VNode)}
                           {account_or_domain? : Account name or Domain (if first two args are VSite/VNode)}
                           {account_name? : Account name (if full VSite/VNode/Domain provided)}
                           {--service= : Service type (mail|ssh|wordpress|private|ftp|phpmyadmin|hcp)}
                           {--username= : Login username}
                           {--password= : Password (generates secure one if not provided)}
                           {--url= : Admin URL or access URL}
                           {--port= : Port number (for SSH/FTP)}
                           {--path= : Path (for SSH/FTP)}
                           {--notes= : Additional notes}
                           {--interactive : Interactive mode with prompts}
                           {--dry-run : Show what would be created}';

    protected $description = 'Add credentials for virtual hosts (NetServa 3.0 Database-First)';

    protected VHostResolverService $resolver;

    public function __construct(VHostResolverService $resolver)
    {
        parent::__construct();
        $this->resolver = $resolver;
    }

    public function handle(): int
    {
        return $this->executeWithContext(function () {
            if ($this->option('interactive')) {
                return $this->handleInteractive();
            }

            // Parse smart arguments for domain resolution
            $args = $this->parseSmartArguments();

            if (! $args['domain']) {
                $this->error('❌ Domain required. Use one of:');
                $this->line('  • addpw cloud.goldcoast.org mail admin@cloud.goldcoast.org');
                $this->line('  • addpw mgo cloud.goldcoast.org wordpress admin');
                $this->line('  • addpw --interactive');

                return 1;
            }

            // Resolve VHost
            try {
                $context = $this->resolver->resolveVHost(
                    $args['domain'],
                    $args['vnode'] ?? null,
                    $args['vsite'] ?? null
                );

                $vhost = FleetVHost::where('domain', $context['vhost'])
                    ->whereHas('vnode', function ($q) use ($context) {
                        $q->where('name', $context['vnode']);
                    })->first();

                if (! $vhost) {
                    $this->error("❌ VHost {$context['vhost']} not found in database");

                    return 1;
                }

                $this->line("🔍 Resolved: <fg=cyan>{$context['vsite']}</fg=cyan>/<fg=yellow>{$context['vnode']}</fg=yellow>/<fg=green>{$context['vhost']}</fg=green>");

                return $this->createCredential($vhost, $args);

            } catch (\Exception $e) {
                $this->error("❌ {$e->getMessage()}");

                return 1;
            }
        });
    }

    /**
     * Parse smart arguments supporting email-first syntax
     */
    protected function parseSmartArguments(): array
    {
        $arg1 = $this->argument('domain_or_vnode');
        $arg2 = $this->argument('service_or_domain');
        $arg3 = $this->argument('account_or_domain');
        $arg4 = $this->argument('account_name');

        // Extract service type from arguments or options
        $serviceType = $this->option('service');

        // Pattern 1: Email-first syntax - addpw admin@cloud.goldcoast.org password
        if ($arg1 && str_contains($arg1, '@')) {
            // Extract domain from email and infer mail service
            $domain = substr($arg1, strpos($arg1, '@') + 1);

            return [
                'domain' => $domain,
                'vnode' => null,
                'vsite' => null,
                'service_type' => 'mail',
                'account_name' => $arg1,
            ];
        }

        // Pattern 2: addpw domain service account
        if ($arg1 && str_contains($arg1, '.') && $arg2 && $arg3) {
            return [
                'domain' => $arg1,
                'vnode' => null,
                'vsite' => null,
                'service_type' => $serviceType ?: $arg2,
                'account_name' => $arg3,
            ];
        }

        // Pattern 3: addpw vnode domain service account
        if ($arg1 && $arg2 && str_contains($arg2, '.') && $arg3 && $arg4) {
            return [
                'domain' => $arg2,
                'vnode' => $arg1,
                'vsite' => null,
                'service_type' => $serviceType ?: $arg3,
                'account_name' => $arg4,
            ];
        }

        // Pattern 4: addpw vsite vnode domain (interactive for service/account)
        if ($arg1 && $arg2 && $arg3 && str_contains($arg3, '.')) {
            return [
                'domain' => $arg3,
                'vnode' => $arg2,
                'vsite' => $arg1,
                'service_type' => $serviceType,
                'account_name' => $arg4,
            ];
        }

        // Fallback: try to detect domain
        if ($arg1 && str_contains($arg1, '.')) {
            return [
                'domain' => $arg1,
                'vnode' => null,
                'vsite' => null,
                'service_type' => $serviceType ?: $arg2,
                'account_name' => $arg3,
            ];
        }

        return [
            'domain' => null,
            'vnode' => null,
            'vsite' => null,
            'service_type' => $serviceType,
            'account_name' => null,
        ];
    }

    /**
     * Interactive mode with Laravel Prompts
     */
    protected function handleInteractive(): int
    {
        info('🔑 NetServa Credential Management - Add New Credential');

        // Select domain
        $domains = FleetVHost::with('vnode.vsite')
            ->get()
            ->mapWithKeys(fn ($v) => [
                $v->domain => "{$v->domain} ({$v->vnode->vsite->name}/{$v->vnode->name})",
            ])
            ->toArray();

        if (empty($domains)) {
            error('❌ No VHosts found in database');

            return 1;
        }

        $selectedDomain = select(
            label: 'Select domain',
            options: $domains,
            hint: 'Choose the domain to add credentials for'
        );

        $vhost = FleetVHost::where('domain', $selectedDomain)->first();

        // Select service type
        $serviceType = select(
            label: 'Select service type',
            options: FleetVHostCredential::SERVICE_TYPES,
            hint: 'Choose the type of credential to add'
        );

        // Get account name
        $accountName = text(
            label: 'Account name',
            placeholder: $serviceType === 'mail' ? 'user@'.$selectedDomain : 'admin',
            hint: 'Username, email address, or account identifier'
        );

        $args = [
            'domain' => $selectedDomain,
            'service_type' => $serviceType,
            'account_name' => $accountName,
        ];

        return $this->createCredential($vhost, $args);
    }

    /**
     * Create the credential
     */
    protected function createCredential(FleetVHost $vhost, array $args): int
    {
        $serviceType = $args['service_type'];
        $accountName = $args['account_name'];

        if (! $serviceType) {
            $this->error('❌ Service type required. Use --service or provide as argument');
            $this->line('   Available: '.implode(', ', array_keys(FleetVHostCredential::SERVICE_TYPES)));

            return 1;
        }

        if (! $accountName) {
            $this->error('❌ Account name required');

            return 1;
        }

        // Check if credential already exists
        $existing = FleetVHostCredential::where('vhost_id', $vhost->id)
            ->where('service_type', $serviceType)
            ->where('account_name', $accountName)
            ->first();

        if ($existing) {
            $this->error("❌ Credential already exists: {$serviceType}/{$accountName}");
            $this->line("   Use 'chpw' to modify or 'delpw' to remove");

            return 1;
        }

        // Gather credential data
        $credentialData = [
            'username' => $this->option('username') ?: $accountName,
            'password' => $this->option('password') ?: $this->generateSecurePassword(),
            'url' => $this->option('url'),
            'port' => $this->option('port'),
            'path' => $this->option('path'),
            'notes' => $this->option('notes'),
        ];

        // Interactive prompts for missing data
        if ($this->option('interactive')) {
            $credentialData = $this->gatherInteractiveData($serviceType, $credentialData);
        }

        if ($this->option('dry-run')) {
            $this->line('🔍 DRY RUN: Would create credential');
            $this->displayCredentialPreview($vhost, $serviceType, $accountName, $credentialData);

            return 0;
        }

        // Create the credential
        try {
            $credential = FleetVHostCredential::createOrUpdateCredential(
                $vhost->id,
                $serviceType,
                $accountName,
                $credentialData
            );

            $this->info("✅ Credential created: {$serviceType}/{$accountName} for {$vhost->domain}");
            $this->displayCredential($credential);

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Failed to create credential: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Gather additional data interactively
     */
    protected function gatherInteractiveData(string $serviceType, array $data): array
    {
        // Service-specific interactive prompts
        switch ($serviceType) {
            case 'ssh':
                $data['port'] = $data['port'] ?: text('SSH Port', default: '22');
                $data['path'] = $data['path'] ?: text('SSH Path', default: '/var/www');
                break;

            case 'wordpress':
                $data['url'] = $data['url'] ?: text('WordPress Admin URL', placeholder: 'https://domain.com/wp-admin/');
                break;

            case 'private':
                $data['url'] = $data['url'] ?: text('Private Area URL', placeholder: 'https://domain.com/private/');
                break;
        }

        // Ask for notes
        $notes = text('Notes (optional)', hint: 'Additional information about this credential');
        if ($notes) {
            $data['notes'] = $notes;
        }

        return $data;
    }

    /**
     * Generate secure password
     */
    protected function generateSecurePassword(int $length = 12): string
    {
        $charset = 'ABCDEFGHIJKLMNPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $upperChars = 'ABCDEFGHIJKLMNPQRSTUVWXYZ';
        $lowerChars = 'abcdefghijklmnopqrstuvwxyz';
        $digitChars = '0123456789';

        // Guarantee one of each required type
        $upperChar = $upperChars[random_int(0, strlen($upperChars) - 1)];
        $lowerChar = $lowerChars[random_int(0, strlen($lowerChars) - 1)];
        $digitChar = $digitChars[random_int(0, strlen($digitChars) - 1)];

        // Fill the rest with random characters
        $remainingLength = $length - 3;
        $rest = '';

        for ($i = 0; $i < $remainingLength; $i++) {
            $rest .= $charset[random_int(0, strlen($charset) - 1)];
        }

        // Combine and shuffle
        $combined = $upperChar.$lowerChar.$digitChar.$rest;

        return str_shuffle($combined);
    }

    /**
     * Display credential preview for dry run
     */
    protected function displayCredentialPreview(FleetVHost $vhost, string $serviceType, string $accountName, array $data): void
    {
        $this->line('');
        $this->line('<fg=blue>📋 Credential Preview:</fg=blue>');
        $this->line("   Domain: <fg=green>{$vhost->domain}</fg=green>");
        $this->line("   Service: <fg=yellow>{$serviceType}</fg=yellow>");
        $this->line("   Account: <fg=cyan>{$accountName}</fg=cyan>");
        $this->line("   Username: {$data['username']}");
        $this->line("   Password: {$data['password']}");

        if ($data['url']) {
            $this->line("   URL: {$data['url']}");
        }
        if ($data['port']) {
            $this->line("   Port: {$data['port']}");
        }
        if ($data['path']) {
            $this->line("   Path: {$data['path']}");
        }
        if ($data['notes']) {
            $this->line("   Notes: {$data['notes']}");
        }
    }

    /**
     * Display created credential
     */
    protected function displayCredential(FleetVHostCredential $credential): void
    {
        $this->line('');
        $this->line('<fg=blue>🔑 Credential Details:</fg=blue>');
        $this->line("   Account: <fg=cyan>{$credential->account_name}</fg=cyan>");
        $this->line("   Username: {$credential->username}");
        $this->line("   Password: <fg=yellow>{$credential->password}</fg=yellow>");

        if ($credential->url) {
            $this->line("   URL: {$credential->url}");
        }
        if ($credential->port) {
            $this->line("   Port: {$credential->port}");
        }
        if ($credential->path) {
            $this->line("   Path: {$credential->path}");
        }
        if ($credential->display_url) {
            $this->line("   Access: {$credential->display_url}");
        }

        $this->line('');
        $this->line('<fg=red>⚠️  Security Warning:</fg=red> Store this password securely!');
    }
}
