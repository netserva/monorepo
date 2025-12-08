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
use function Laravel\Prompts\password;
use function Laravel\Prompts\warning;

/**
 * Change Password Command - NetServa 3.0
 *
 * CRUD: Update - Modify existing credential in unified vault
 *
 * Usage:
 *   chpw mrn cloudflare api_key          # Update by vnode + service + name
 *   chpw example.com dovecot admin@...   # Update by vhost + service + name
 *   chpw mrn mysql root --new-secret=xxx # Non-interactive update
 *
 * Smart Resolution:
 *   - No dots = VNode (mrn, gw, ns1gc)
 *   - Has dots = VHost (example.com)
 */
class ChpwCommand extends Command
{
    protected $signature = 'chpw
                            {name? : VNode name (no dots) or domain (has dots)}
                            {service? : Service type (cloudflare, mysql, dovecot, etc.)}
                            {credential? : Credential name/identifier}
                            {--new-secret= : New password/API key/token}
                            {--username= : Update username}
                            {--url= : Update URL}
                            {--port= : Update port}
                            {--notes= : Update notes}';

    protected $description = 'Update credentials in unified vault (UPDATE)';

    public function handle(): int
    {
        try {
            // Get credential to update
            $ownerName = $this->argument('name');
            $service = $this->argument('service');
            $credentialName = $this->argument('credential');

            if (! $ownerName || ! $service || ! $credentialName) {
                error('All arguments required: name service credential');
                $this->line('Example: chpw mrn cloudflare api_key');
                $this->line('Example: chpw example.com dovecot admin@example.com');

                return Command::FAILURE;
            }

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
                warning("Use 'addpw' to create it first, or 'shpw {$ownerName}' to list available");

                return Command::FAILURE;
            }

            // Display current credential info
            info("Updating credential: {$credential->service_display}");
            $this->line('');
            $this->line("Owner: {$ownerType} - {$ownerName}");
            $this->line("Service: {$service}");
            $this->line("Name: {$credentialName}");
            $this->line('Username: '.($credential->username ?: '-'));
            $this->line('URL: '.($credential->url ?: '-'));
            $this->line('');

            $updated = false;

            // Update secret if provided
            if ($newSecret = $this->option('new-secret')) {
                if (confirm(
                    label: 'Update secret data?',
                    default: true,
                    hint: 'This will replace the current password/API key'
                )) {
                    $credential->password = $newSecret;
                    $updated = true;
                    info('Secret updated');
                }
            } else {
                // Prompt for new secret interactively
                if (confirm(
                    label: 'Update the password/secret?',
                    default: true,
                    hint: 'The current secret will be replaced'
                )) {
                    $newSecret = password(
                        label: 'New password/secret',
                        required: true,
                        hint: 'Will be encrypted at rest'
                    );
                    $credential->password = $newSecret;
                    $updated = true;
                    info('Secret updated');
                }
            }

            // Update username if provided
            if ($username = $this->option('username')) {
                $credential->username = $username;
                $updated = true;
                info('Username updated');
            }

            // Update URL if provided
            if ($url = $this->option('url')) {
                $credential->url = $url;
                $updated = true;
                info('URL updated');
            }

            // Update port if provided
            if ($port = $this->option('port')) {
                $credential->port = (int) $port;
                $updated = true;
                info('Port updated');
            }

            // Update notes if provided
            if ($notes = $this->option('notes')) {
                $credential->notes = $notes;
                $updated = true;
                info('Notes updated');
            }

            if (! $updated) {
                warning('No changes made. Use options: --new-secret, --username, --url, --port, --notes');

                return Command::SUCCESS;
            }

            $credential->save();

            // Success summary
            $this->line('');
            info('Credential updated successfully');
            $this->table(
                ['Field', 'Value'],
                [
                    ['Owner', "{$ownerType}: {$ownerName}"],
                    ['Service', $credential->service_display],
                    ['Name', $credential->name],
                    ['Username', $credential->username ?: '-'],
                    ['URL', $credential->url ?: '-'],
                    ['Port', $credential->port ?: '-'],
                    ['Updated', $credential->updated_at->format('Y-m-d H:i:s')],
                ]
            );

            return Command::SUCCESS;

        } catch (Exception $e) {
            error('Failed to update credential: '.$e->getMessage());
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
