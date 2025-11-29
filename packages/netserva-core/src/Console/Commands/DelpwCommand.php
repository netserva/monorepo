<?php

namespace NetServa\Core\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use NetServa\Core\Models\VPass;
use NetServa\Fleet\Models\FleetVenue;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

/**
 * Delete Password Command - NetServa 3.0
 *
 * CRUD: Delete - Remove credential from unified vault
 *
 * Usage:
 *   delpw vnode mgo cloudflare default     # Delete Cloudflare API key for mgo
 *   delpw vhost example.com dovecot admin@example.com  # Delete email password
 *   delpw --force vnode mgo cloudflare default  # Skip confirmation
 *
 * NetServa 3.0 Security Architecture:
 * - Permanently removes encrypted credentials from workstation vault
 * - Requires confirmation unless --force flag used
 * - Supports soft-delete pattern (disable instead of delete)
 */
class DelpwCommand extends Command
{
    protected $signature = 'delpw
                            {owner_type : Owner type (venue/vsite/vnode/vhost)}
                            {owner_name : Owner name}
                            {pserv : Service provider}
                            {pname : Identifier name}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Delete credential from unified vault (DELETE)';

    public function handle(): int
    {
        try {
            // Get credential to delete
            $ownerType = $this->argument('owner_type');
            $ownerName = $this->argument('owner_name');
            $pserv = $this->argument('pserv');
            $pname = $this->argument('pname');

            // Find owner
            $owner = $this->findOwner($ownerType, $ownerName);
            if (! $owner) {
                error("{$ownerType} not found: {$ownerName}");

                return Command::FAILURE;
            }

            // Find credential
            $credential = VPass::byOwner($owner)
                ->where('pserv', $pserv)
                ->where('pname', $pname)
                ->first();

            if (! $credential) {
                error("Credential not found: {$pserv}/{$pname} for {$ownerType} {$ownerName}");
                warning("Use 'shpw' to list available credentials");

                return Command::FAILURE;
            }

            // Display credential info
            info("Deleting credential: {$credential->type_display} for {$pserv}");
            $this->line('');
            $this->line("Owner: {$credential->owner_type_display} - {$ownerName}");
            $this->line("Service: {$pserv}");
            $this->line("Name: {$pname}");
            $this->line("Type: {$credential->type_display}");
            $this->line('Status: '.($credential->pstat ? '✓ Active' : '✗ Disabled'));
            $this->line('');

            // Confirm deletion unless --force
            if (! $this->option('force')) {
                if (! confirm(
                    label: 'Permanently delete this credential?',
                    default: false,
                    hint: 'This action cannot be undone'
                )) {
                    warning('Deletion cancelled');

                    return Command::SUCCESS;
                }
            }

            // Delete credential
            $credential->delete();

            info('Credential deleted successfully');
            $this->line('');
            $this->line("Deleted: {$pserv}/{$pname}");

            return Command::SUCCESS;

        } catch (Exception $e) {
            error('Failed to delete credential: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    /**
     * Find owner model by type and name
     */
    private function findOwner(string $type, string $name): ?object
    {
        return match ($type) {
            'venue' => FleetVenue::where('name', $name)->first(),
            'vsite' => FleetVsite::where('name', $name)->first(),
            'vnode' => FleetVnode::where('name', $name)->first(),
            'vhost' => FleetVhost::where('fqdn', $name)->first(),
            default => null,
        };
    }
}
