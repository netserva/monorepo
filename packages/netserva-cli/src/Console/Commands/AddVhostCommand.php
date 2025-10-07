<?php

namespace NetServa\Cli\Console\Commands;

use NetServa\Cli\Services\VhostManagementService;

/**
 * Add VHost Command
 *
 * Follows NetServa CRUD pattern: addvhost (not "ns vhost add")
 * Usage: addvhost <vnode> <vhost>
 * Example: addvhost markc markc.goldcoast.org
 */
class AddVhostCommand extends BaseNetServaCommand
{
    protected $signature = 'addvhost {vnode : SSH host/VNode identifier} {vhost : Domain name to add} {--dry-run : Show what would be done}';

    protected $description = 'Add a new virtual host (NetServa CRUD pattern)';

    protected VhostManagementService $vhostService;

    public function __construct(VhostManagementService $vhostService)
    {
        parent::__construct();
        $this->vhostService = $vhostService;
    }

    public function handle(): int
    {
        return $this->executeWithContext(function () {
            // Get required parameters from positional arguments
            $VNODE = $this->argument('vnode');
            $VHOST = $this->argument('vhost');

            // Show what we're about to do
            $this->line("🚀 Adding VHost: <fg=yellow>{$VHOST}</> on node <fg=cyan>{$VNODE}</>");

            if ($this->option('dry-run')) {
                $this->dryRun("Add VHost {$VHOST} on {$VNODE}", [
                    "Generate VHost configuration for {$VHOST}",
                    "Save config to ~/.ns/var/{$VNODE}/{$VHOST}",
                    "Save credentials to ~/.ns/var/{$VNODE}/{$VHOST}.conf",
                    "SSH to {$VNODE} and execute vhost creation",
                    'Create user u1001+, directories, permissions',
                    'Generate SSL certificate',
                    'Configure nginx, PHP-FPM, database',
                ]);

                return 0;
            }

            // Create the VHost using NetServa service (use VNODE but pass as VNODE internally)
            $result = $this->vhostService->createVhost($VNODE, $VHOST);

            if ($result['success']) {
                $this->info("✅ VHost {$VHOST} created successfully on {$VNODE}");

                // Show key NetServa information
                if (isset($result['config'])) {
                    $config = $result['config'];
                    $this->line('');
                    $this->line('<fg=blue>📋 VHost Details:</>');
                    $this->line("   User: <fg=yellow>{$config->UUSER}</> (UID: {$config->U_UID})");
                    $this->line("   Path: <fg=yellow>{$config->paths->wpath}</>");
                    $this->line("   Config: <fg=yellow>~/.ns/var/{$VNODE}/{$VHOST}</>");
                    $this->line("   Credentials: <fg=yellow>~/.ns/var/{$VNODE}/{$VHOST}.conf</>");
                }

                // Add to command history
                $this->context->addToHistory("addvhost {$VHOST}", [
                    'VNODE' => $VNODE,
                    'VHOST' => $VHOST,
                    'success' => true,
                ]);

                return 0;
            } else {
                $this->error("❌ Failed to create VHost {$VHOST} on {$VNODE}");
                if (isset($result['error'])) {
                    $this->line("   Error: {$result['error']}");
                }

                return 1;
            }
        });
    }
}
