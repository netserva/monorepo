<?php

namespace NetServa\Cli\Console\Commands;

use NetServa\Cli\Services\VHostResolverService;
use NetServa\Fleet\Models\FleetVHost;
use NetServa\Fleet\Models\FleetVHostCredential;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

/**
 * Delete Password/Credential Command - NetServa 3.0 Database-First
 *
 * Remove credentials from VHosts
 * Smart VHost resolution: delpw cloud.goldcoast.org mail admin@cloud.goldcoast.org
 */
class DelpwCommand extends BaseNetServaCommand
{
    protected $signature = 'delpw {domain_or_vnode? : Domain name or VNode (smart detection)}
                           {service_or_domain? : Service type or Domain (if first arg is VNode)}
                           {account_or_domain? : Account name or Domain (if first two args are VSite/VNode)}
                           {account_name? : Account name (if full VSite/VNode/Domain provided)}
                           {--service= : Service type (mail|ssh|wordpress|private|ftp|phpmyadmin|hcp)}
                           {--account= : Account name filter}
                           {--all-service= : Delete all credentials of specific service type}
                           {--force : Skip confirmation prompts}
                           {--interactive : Interactive mode with prompts}
                           {--dry-run : Show what would be deleted}';

    protected $description = 'Delete credentials for virtual hosts (NetServa 3.0 Database-First)';

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
                $this->line('  • delpw cloud.goldcoast.org mail admin@cloud.goldcoast.org');
                $this->line('  • delpw mgo cloud.goldcoast.org --all-service=mail');
                $this->line('  • delpw --interactive');

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

                return $this->deleteCredentials($vhost, $args);

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

        $serviceType = $this->option('service');
        $accountFilter = $this->option('account');

        // Pattern 1: Email-first syntax - delpw admin@cloud.goldcoast.org
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

        // Pattern 2: delpw domain service account
        if ($arg1 && str_contains($arg1, '.') && $arg2 && $arg3) {
            return [
                'domain' => $arg1,
                'vnode' => null,
                'vsite' => null,
                'service_type' => $serviceType ?: $arg2,
                'account_name' => $accountFilter ?: $arg3,
            ];
        }

        // Pattern 3: delpw vnode domain service account
        if ($arg1 && $arg2 && str_contains($arg2, '.') && $arg3 && $arg4) {
            return [
                'domain' => $arg2,
                'vnode' => $arg1,
                'vsite' => null,
                'service_type' => $serviceType ?: $arg3,
                'account_name' => $accountFilter ?: $arg4,
            ];
        }

        // Fallback pattern
        if ($arg1 && str_contains($arg1, '.')) {
            return [
                'domain' => $arg1,
                'vnode' => null,
                'vsite' => null,
                'service_type' => $serviceType ?: $arg2,
                'account_name' => $accountFilter ?: $arg3,
            ];
        }

        return [
            'domain' => null,
            'vnode' => null,
            'vsite' => null,
            'service_type' => $serviceType,
            'account_name' => $accountFilter,
        ];
    }

    /**
     * Interactive mode
     */
    protected function handleInteractive(): int
    {
        info('🗑️  NetServa Credential Management - Delete Credentials');

        // Select domain
        $domains = FleetVHost::with('vnode.vsite')
            ->whereHas('credentials')
            ->get()
            ->mapWithKeys(fn ($v) => [
                $v->domain => "{$v->domain} ({$v->vnode->vsite->name}/{$v->vnode->name})",
            ])
            ->toArray();

        if (empty($domains)) {
            error('❌ No VHosts with credentials found');

            return 1;
        }

        $selectedDomain = select(
            label: 'Select domain',
            options: $domains,
            hint: 'Choose the domain to delete credentials from'
        );

        $vhost = FleetVHost::where('domain', $selectedDomain)->first();

        // Select credentials to delete
        $credentials = FleetVHostCredential::where('vhost_id', $vhost->id)
            ->active()
            ->get()
            ->mapWithKeys(function ($cred) {
                $serviceType = FleetVHostCredential::SERVICE_TYPES[$cred->service_type] ?? $cred->service_type;

                return [
                    $cred->id => "{$serviceType}: {$cred->account_name}",
                ];
            })
            ->toArray();

        if (empty($credentials)) {
            error("❌ No credentials found for {$selectedDomain}");

            return 1;
        }

        $selectedCredentialIds = multiselect(
            label: 'Select credentials to delete',
            options: $credentials,
            hint: 'Choose which credentials to remove (use space to select multiple)'
        );

        if (empty($selectedCredentialIds)) {
            warning('No credentials selected for deletion');

            return 0;
        }

        // Confirm deletion
        $count = count($selectedCredentialIds);
        $confirmed = confirm("Delete {$count} credential(s)?", false);

        if (! $confirmed) {
            info('Deletion cancelled');

            return 0;
        }

        // Perform deletion
        $credentialsToDelete = FleetVHostCredential::whereIn('id', $selectedCredentialIds)->get();

        foreach ($credentialsToDelete as $credential) {
            $credential->delete();
            $this->line("✅ Deleted: {$credential->service_type}/{$credential->account_name}");
        }

        $this->info("✅ Successfully deleted {$count} credential(s)");

        return 0;
    }

    /**
     * Delete credentials based on filters
     */
    protected function deleteCredentials(FleetVHost $vhost, array $args): int
    {
        // Build query for credentials to delete
        $query = FleetVHostCredential::where('vhost_id', $vhost->id);

        // Apply filters
        if ($this->option('all-service')) {
            $query->where('service_type', $this->option('all-service'));
        } elseif ($args['service_type']) {
            $query->where('service_type', $args['service_type']);
        }

        if ($args['account_name']) {
            $query->where('account_name', $args['account_name']);
        } elseif ($this->option('account')) {
            $query->where('account_name', 'like', "%{$this->option('account')}%");
        }

        $credentials = $query->get();

        if ($credentials->isEmpty()) {
            $filters = [];
            if ($args['service_type']) {
                $filters[] = "service: {$args['service_type']}";
            }
            if ($args['account_name']) {
                $filters[] = "account: {$args['account_name']}";
            }

            $filterText = empty($filters) ? '' : ' ('.implode(', ', $filters).')';
            warning("No credentials found to delete for {$vhost->domain}{$filterText}");

            return 0;
        }

        if ($this->option('dry-run')) {
            $this->line('🔍 DRY RUN: Would delete credentials');
            $this->displayDeletionPreview($credentials);

            return 0;
        }

        // Confirm deletion unless forced
        if (! $this->option('force')) {
            $count = $credentials->count();
            $this->line('');
            $this->line("<fg=red>⚠️  About to delete {$count} credential(s):</fg=red>");

            foreach ($credentials as $cred) {
                $serviceType = FleetVHostCredential::SERVICE_TYPES[$cred->service_type] ?? $cred->service_type;
                $this->line("   • {$serviceType}: {$cred->account_name}");
            }

            $this->line('');
            $confirmed = confirm("Permanently delete these {$count} credential(s)?", false);

            if (! $confirmed) {
                info('Deletion cancelled');

                return 0;
            }
        }

        // Perform deletion
        $deletedCount = 0;
        foreach ($credentials as $credential) {
            try {
                $serviceType = FleetVHostCredential::SERVICE_TYPES[$credential->service_type] ?? $credential->service_type;
                $credential->delete();
                $this->line("✅ Deleted: {$serviceType}/{$credential->account_name}");
                $deletedCount++;
            } catch (\Exception $e) {
                $this->error("❌ Failed to delete {$credential->service_type}/{$credential->account_name}: {$e->getMessage()}");
            }
        }

        if ($deletedCount > 0) {
            $this->info("✅ Successfully deleted {$deletedCount} credential(s) from {$vhost->domain}");
        }

        return 0;
    }

    /**
     * Display deletion preview for dry run
     */
    protected function displayDeletionPreview($credentials): void
    {
        $this->line('');
        $this->line('<fg=blue>📋 Deletion Preview:</fg=blue>');
        $this->line('');

        foreach ($credentials as $cred) {
            $serviceType = FleetVHostCredential::SERVICE_TYPES[$cred->service_type] ?? $cred->service_type;
            $this->line("   <fg=red>✗</fg=red> {$serviceType}: {$cred->account_name}");
        }

        $this->line('');
        $this->line("Total credentials to delete: <fg=yellow>{$credentials->count()}</fg=yellow>");
    }
}
