<?php

namespace NetServa\Core\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use NetServa\Core\Models\VPass;
use NetServa\Core\Services\RemoteExecutionService;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

/**
 * Import VMail Credentials Command - NetServa 3.0
 *
 * Discovers email accounts from remote vnode databases and imports
 * them into the VPass credential vault (workstation only).
 *
 * NOTE: This only imports email addresses. Passwords remain hashed
 * on the remote server - we create placeholder entries in VPass.
 *
 * Usage:
 *   import:vmail-credentials mgo        # Import from mgo vnode
 *   import:vmail-credentials --all      # Import from all vnodes
 */
class ImportVmailCredentialsCommand extends Command
{
    protected $signature = 'import:vmail-credentials
                            {vnode? : VNode name to import from}
                            {--all : Import from all vnodes}
                            {--dry-run : Preview what would be imported}
                            {--force : Skip confirmation prompts}';

    protected $description = 'Import email credentials from remote vnode databases to VPass vault';

    public function __construct(
        protected RemoteExecutionService $remoteExecution
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            // Get vnodes to process
            if ($this->option('all')) {
                $vnodes = FleetVnode::all();
            } elseif ($vnodeName = $this->argument('vnode')) {
                $vnode = FleetVnode::where('name', $vnodeName)->first();
                if (! $vnode) {
                    error("VNode not found: {$vnodeName}");

                    return Command::FAILURE;
                }
                $vnodes = collect([$vnode]);
            } else {
                error('Specify a vnode name or use --all');

                return Command::FAILURE;
            }

            if ($vnodes->isEmpty()) {
                warning('No vnodes found');

                return Command::SUCCESS;
            }

            info("Processing {$vnodes->count()} vnode(s)");
            $this->line('');

            $totalImported = 0;
            $totalSkipped = 0;
            $totalErrors = 0;

            foreach ($vnodes as $vnode) {
                $this->line("Processing vnode: {$vnode->name}");

                $result = $this->processVNode($vnode);

                $totalImported += $result['imported'];
                $totalSkipped += $result['skipped'];
                $totalErrors += $result['errors'];

                $this->line('');
            }

            // Summary
            info('Import complete');
            $this->line("Imported: {$totalImported}");
            $this->line("Skipped: {$totalSkipped}");
            $this->line("Errors: {$totalErrors}");

            return Command::SUCCESS;

        } catch (Exception $e) {
            error('Import failed: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    /**
     * Process single vnode
     */
    private function processVNode(FleetVnode $vnode): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = 0;

        try {
            // Get vmails from remote database
            $vmails = $this->getRemoteVmails($vnode);

            if (empty($vmails)) {
                warning("  No vmails found on {$vnode->name}");

                return ['imported' => 0, 'skipped' => 0, 'errors' => 0];
            }

            $this->line("  Found {$vmails->count()} email account(s)");

            if ($this->option('dry-run')) {
                $this->displayDryRun($vmails);

                return ['imported' => 0, 'skipped' => $vmails->count(), 'errors' => 0];
            }

            // Confirm import
            if (! $this->option('force')) {
                if (! confirm("Import {$vmails->count()} email accounts from {$vnode->name}?", true)) {
                    warning('  Skipped');

                    return ['imported' => 0, 'skipped' => $vmails->count(), 'errors' => 0];
                }
            }

            // Import each vmail
            foreach ($vmails as $vmail) {
                try {
                    if ($this->importVmail($vnode, $vmail)) {
                        $imported++;
                        $this->line("  ✓ {$vmail['user']}");
                    } else {
                        $skipped++;
                        $this->line("  ⊘ {$vmail['user']} (already exists)");
                    }
                } catch (Exception $e) {
                    $errors++;
                    $this->line("  ✗ {$vmail['user']}: {$e->getMessage()}");
                }
            }

        } catch (Exception $e) {
            error("  Error processing {$vnode->name}: {$e->getMessage()}");
            $errors++;
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Get vmails from remote vnode database
     */
    private function getRemoteVmails(FleetVnode $vnode): \Illuminate\Support\Collection
    {
        $dbType = $vnode->database_type ?? 'sqlite';

        if ($dbType === 'mysql') {
            $sql = "mysql --skip-ssl sysadm -e 'SELECT user, home, created_at FROM vmails WHERE active = 1;' 2>&1";
        } else {
            $sql = "sqlite3 /var/lib/sqlite/sysadm/sysadm.db 'SELECT user, home, created_at FROM vmails WHERE active = 1;' 2>&1";
        }

        $result = $this->remoteExecution->executeAsRoot($vnode->name, $sql);

        if (! $result['success']) {
            throw new Exception('Failed to query remote database: '.($result['error'] ?? 'Unknown error'));
        }

        // Parse output
        $lines = explode("\n", trim($result['output']));
        $vmails = collect();

        foreach ($lines as $line) {
            if (empty($line) || str_contains($line, 'user')) {
                continue; // Skip header or empty lines
            }

            $parts = preg_split('/\t+/', $line);
            if (count($parts) >= 2) {
                $vmails->push([
                    'user' => $parts[0],
                    'home' => $parts[1] ?? '',
                    'created_at' => $parts[2] ?? null,
                ]);
            }
        }

        return $vmails;
    }

    /**
     * Import single vmail into VPass
     */
    private function importVmail(FleetVnode $vnode, array $vmail): bool
    {
        $email = $vmail['user'];
        $domain = substr(strstr($email, '@'), 1);

        // Find vhost
        $vhost = FleetVhost::where(function ($q) use ($domain) {
            $q->where('domain', $domain)
                ->orWhere('fqdn', $domain);
        })
            ->where('vnode_id', $vnode->id)
            ->first();

        if (! $vhost) {
            // Try to find any vhost for this vnode as fallback
            $vhost = FleetVhost::where('vnode_id', $vnode->id)->first();

            if (! $vhost) {
                throw new Exception("No vhost found for domain {$domain} on vnode {$vnode->name}");
            }
        }

        // Check if already exists
        $existing = VPass::byOwner($vhost)
            ->where('pserv', 'dovecot')
            ->where('pname', $email)
            ->first();

        if ($existing) {
            return false; // Already exists
        }

        // Create VPass entry (without password - hashed on server)
        VPass::create([
            'owner_type' => FleetVhost::class,
            'owner_id' => $vhost->id,
            'ptype' => 'VMAIL',
            'pserv' => 'dovecot',
            'pname' => $email,
            'pdata' => 'unknown', // Placeholder - cleartext not available
            'pmeta' => [
                'imported_from' => $vnode->name,
                'home' => $vmail['home'],
                'remote_created_at' => $vmail['created_at'],
                'note' => 'Imported from remote database - cleartext password not available',
            ],
            'pstat' => true,
            'pnote' => "Imported from {$vnode->name} on ".now()->format('Y-m-d H:i:s'),
            'created_at' => $vmail['created_at'] ?? now(),
        ]);

        return true;
    }

    /**
     * Display dry-run preview
     */
    private function displayDryRun($vmails): void
    {
        $this->table(
            ['Email', 'Maildir', 'Created'],
            $vmails->map(fn ($v) => [
                $v['user'],
                $v['maildir'],
                $v['created_at'] ?? 'N/A',
            ])->toArray()
        );
    }
}
