<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Console\Traits\ResolvesVPassOwner;
use NetServa\Core\Models\VPass;

use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

/**
 * Show Password Command - NetServa 3.0
 *
 * CRUD: Read - List credentials from unified vault
 *
 * Usage:
 *   shpw                          # Interactive mode
 *   shpw vnode mgo               # List all credentials for mgo vnode
 *   shpw vhost example.com        # List all credentials for vhost
 *   shpw --service=cloudflare     # List all Cloudflare credentials
 *   shpw --type=APKEY             # List all API keys
 *
 * NetServa 3.0 Security Architecture:
 * - Shows encrypted credentials from workstation vault
 * - Supports filtering by owner, service, type
 * - Optional masked display (default shows cleartext)
 */
class ShpwCommand extends Command
{
    use ResolvesVPassOwner;

    protected $signature = 'shpw
                            {name? : VNode name, domain, or vnode+domain}
                            {domain? : Domain (if first arg is vnode)}
                            {--service= : Filter by service (cloudflare, binarylane, etc.)}
                            {--type= : Filter by type (VMAIL, APKEY, DBPWD, SSLKY, OAUTH)}
                            {--active : Show only active credentials}
                            {--expired : Show only expired credentials}
                            {--needs-rotation : Show credentials needing rotation}
                            {--show-secrets : Show actual passwords (default hidden)}';

    protected $description = 'Show credentials from unified vault (READ)';

    public function handle(): int
    {
        try {
            // Build query
            $query = VPass::query();

            // Smart owner resolution
            $name = $this->argument('name');
            $domain = $this->argument('domain');

            if ($name) {
                $owner = $this->resolveOwner($name, $domain);
                if (! $owner) {
                    return Command::FAILURE;
                }

                $query->byOwner($owner);
                $context = $this->getOwnerContext($owner, $name, $domain);
            } else {
                $context = 'All owners';
            }

            // Apply filters
            if ($service = $this->option('service')) {
                $query->byService($service);
            }

            if ($type = $this->option('type')) {
                $query->byType($type);
            }

            if ($this->option('active')) {
                $query->active();
            }

            if ($this->option('expired')) {
                $query->expired();
            }

            if ($this->option('needs-rotation')) {
                $query->needsRotation(90);
            }

            // Execute query
            $credentials = $query->with('owner')->orderBy('owner_type')->orderBy('pserv')->get();

            if ($credentials->isEmpty()) {
                warning('No credentials found matching criteria');

                return Command::SUCCESS;
            }

            // Display header
            info("Credentials Vault - {$context}");
            $this->line('');

            // Prepare table data
            $showSecrets = $this->option('show-secrets');
            $rows = [];

            foreach ($credentials as $cred) {
                $secret = $showSecrets ? $this->truncateSecret($cred->getSecret()) : '***HIDDEN***';

                $status = [];
                if (! $cred->pstat) {
                    $status[] = '✗ Disabled';
                }
                if ($cred->isExpired()) {
                    $status[] = '⏰ Expired';
                }
                if ($cred->needsRotation()) {
                    $status[] = '⚠ Needs Rotation';
                }
                $statusStr = empty($status) ? '✓ Active' : implode(', ', $status);

                $rows[] = [
                    $cred->owner_type_display,
                    $cred->owner->domain ?? $cred->owner->name ?? $cred->owner->fqdn ?? 'N/A',
                    $cred->type_display,
                    $cred->pserv,
                    $cred->pname,
                    $secret,
                    $statusStr,
                    $cred->pdate?->format('Y-m-d') ?? 'Never',
                ];
            }

            // Display table
            $this->table(
                ['Owner Type', 'Owner', 'Type', 'Service', 'Name', 'Secret', 'Status', 'Rotated'],
                $rows
            );

            // Summary
            $this->line('');
            $this->line("Total credentials: {$credentials->count()}");

            if (! $showSecrets) {
                $this->line('');
                warning('Use --show-secrets to display actual passwords');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            error('Failed to retrieve credentials: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    /**
     * Truncate secret for display (first/last chars only)
     */
    private function truncateSecret(string $secret): string
    {
        $len = strlen($secret);

        if ($len <= 12) {
            return $secret;
        }

        if ($len <= 24) {
            return substr($secret, 0, 4).'...'.substr($secret, -4);
        }

        return substr($secret, 0, 8).'...'.substr($secret, -8);
    }
}
