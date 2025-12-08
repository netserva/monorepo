<?php

namespace NetServa\Core\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use NetServa\Core\Models\VPass;
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
 *   delpw mrn cloudflare api_key             # Delete by vnode + service + name
 *   delpw example.com dovecot admin@...      # Delete by vhost + service + name
 *   delpw --force mrn cloudflare api_key     # Skip confirmation
 *
 * Smart Resolution:
 *   - No dots = VNode (mrn, gw, ns1gc)
 *   - Has dots = VHost (example.com)
 */
class DelpwCommand extends Command
{
    protected $signature = 'delpw
                            {name : VNode name (no dots) or domain (has dots)}
                            {service : Service type (cloudflare, mysql, dovecot, etc.)}
                            {credential : Credential name/identifier}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Delete credential from unified vault (DELETE)';

    public function handle(): int
    {
        try {
            // Get credential to delete
            $ownerName = $this->argument('name');
            $service = $this->argument('service');
            $credentialName = $this->argument('credential');

            // Smart owner resolution
            $owner = $this->resolveOwner($ownerName);
            if (! $owner) {
                return Command::FAILURE;
            }

            $ownerType = $this->getOwnerType($owner);
            $ownerKey = $this->getOwnerKey($owner);

            // Find credential
            $credential = VPass::where($ownerKey, $owner->id)
                ->where('service', $service)
                ->where('name', $credentialName)
                ->first();

            if (! $credential) {
                error("Credential not found: {$service}/{$credentialName} for {$ownerName}");
                warning("Use 'shpw {$ownerName}' to list available credentials");

                return Command::FAILURE;
            }

            // Display credential info
            info("Deleting credential: {$credential->service_display}");
            $this->line('');
            $this->line("Owner: {$ownerType} - {$ownerName}");
            $this->line("Service: {$service}");
            $this->line("Name: {$credentialName}");
            $this->line('Username: '.($credential->username ?: '-'));
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
            $this->line("Deleted: {$service}/{$credentialName}");

            return Command::SUCCESS;

        } catch (Exception $e) {
            error('Failed to delete credential: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    /**
     * Smart owner resolution: dots = domain = VHost, no dots = VNode
     */
    private function resolveOwner(string $name): ?object
    {
        if (str_contains($name, '.')) {
            // Has dots = domain = VHost
            $vhost = FleetVhost::where('domain', $name)->first();
            if (! $vhost) {
                error("VHost not found: {$name}");

                return null;
            }

            return $vhost;
        }

        // No dots = VNode
        $vnode = FleetVnode::where('name', $name)->first();
        if (! $vnode) {
            error("VNode not found: {$name}");

            return null;
        }

        return $vnode;
    }

    /**
     * Get owner type display name
     */
    private function getOwnerType(object $owner): string
    {
        return match (true) {
            $owner instanceof FleetVhost => 'VHost',
            $owner instanceof FleetVnode => 'VNode',
            $owner instanceof FleetVsite => 'VSite',
            default => 'Unknown',
        };
    }

    /**
     * Get FK column name for owner
     */
    private function getOwnerKey(object $owner): string
    {
        return match (true) {
            $owner instanceof FleetVhost => 'fleet_vhost_id',
            $owner instanceof FleetVnode => 'fleet_vnode_id',
            $owner instanceof FleetVsite => 'fleet_vsite_id',
            default => throw new Exception('Invalid owner type'),
        };
    }
}
