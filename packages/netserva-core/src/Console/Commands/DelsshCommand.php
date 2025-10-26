<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Models\SshHost;

use function Laravel\Prompts\confirm;

/**
 * Delete SSH Host Command (NetServa 3.0 CRUD: DELETE)
 *
 * Deletes an SSH host (with safety checks)
 */
class DelsshCommand extends Command
{
    protected $signature = 'delssh
                            {host : SSH host identifier to delete}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Delete SSH host (NetServa 3.0 CRUD: Delete)';

    public function handle(): int
    {
        $host = $this->argument('host');
        $force = $this->option('force');

        $sshHost = SshHost::where('host', $host)->first();

        if (! $sshHost) {
            $this->error("SSH host not found: {$host}");

            return Command::FAILURE;
        }

        // Safety check: warn if SSH host is used by vnodes
        $vnodeCount = $sshHost->vnodes()->count();

        if ($vnodeCount > 0) {
            $this->warn("⚠️  This SSH host is used by {$vnodeCount} vnode(s)!");
            $this->newLine();
            $this->line('VNodes that will be affected:');
            foreach ($sshHost->vnodes()->take(10)->get() as $vnode) {
                $this->line("  • {$vnode->name} (vsite: {$vnode->vsite->name})");
            }
            if ($vnodeCount > 10) {
                $this->line('  ... and '.($vnodeCount - 10).' more');
            }
            $this->newLine();
            $this->warn('Deleting this SSH host will prevent remote connections to these vnodes!');
            $this->newLine();
        }

        // Confirmation
        if (! $force) {
            $confirmed = confirm(
                label: "Delete SSH host '{$host}'?",
                default: false,
                yes: 'Yes, delete it',
                no: 'No, cancel'
            );

            if (! $confirmed) {
                $this->info('Deletion cancelled.');

                return Command::SUCCESS;
            }
        }

        // Delete SSH host
        $sshHost->delete();

        $this->info("✓ SSH host deleted: {$host}");

        if ($vnodeCount > 0) {
            $this->warn("Warning: {$vnodeCount} vnode(s) can no longer be accessed remotely.");
            $this->line('Update vnodes with: chvnode <name> --ssh-host=<new-host>');
        }

        return Command::SUCCESS;
    }
}
