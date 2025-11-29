<?php

namespace NetServa\Core\Console\Commands;

use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;
use Symfony\Component\Console\Helper\Table;

/**
 * Show VHost Command
 *
 * Follows NetServa CRUD pattern: shvhost (not "ns vhost show")
 * Usage: shvhost [vnode] [vhost]
 * Examples:
 *   shvhost                              # Show all vhosts on all servers
 *   shvhost markc --list                 # List all vhosts on markc server
 *   shvhost markc markc.goldcoast.org    # Show specific vhost details
 *
 * DATABASE-FIRST: Uses FleetVhost model (environment_vars JSON column)
 */
class ShvhostCommand extends BaseNetServaCommand
{
    protected $signature = 'shvhost {vnode? : SSH host/VNode identifier (optional)} {vhost? : Domain name to show (optional)} {--list : List all vhosts on server} {--config : Show configuration details} {--all : Show all environment variables (like shvconf)}';

    protected $description = 'Show virtual host information (NetServa CRUD pattern)';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        return $this->executeWithContext(function () {
            $VNODE = $this->argument('vnode');
            $VHOST = $this->argument('vhost');

            // List mode: show all vhosts for server
            if ($this->option('list') || (! $VHOST && $VNODE)) {
                return $this->showVhostList($VNODE);
            }

            // Show specific vhost
            if ($VHOST && $VNODE) {
                return $this->showVhostDetails($VNODE, $VHOST);
            }

            // Show all servers and their vhosts
            return $this->showAllVhosts();
        });
    }

    protected function showVhostList(?string $VNODE): int
    {
        if (! $VNODE) {
            $this->error('❌ VNODE required for listing vhosts');

            return 1;
        }

        // Query database for vhosts on this vnode
        $vhosts = FleetVhost::whereHas('vnode', fn ($q) => $q->where('name', $VNODE))
            ->orderBy('domain')
            ->get();

        $this->line("<fg=blue>📋 VHosts on server:</> <fg=yellow>{$VNODE}</>");

        if ($vhosts->isEmpty()) {
            $this->line('   <fg=gray>No vhosts found in database</>');
            $this->line("   💡 Run: php artisan addfleet {$VNODE}");

            return 0;
        }

        $table = new Table($this->output);
        $table->setHeaders(['VHost', 'Status', 'Services', 'Last Discovered']);

        foreach ($vhosts as $vhost) {
            $status = $vhost->is_active ? '✅ Active' : '❌ Inactive';
            $services = $vhost->services ? implode(', ', array_slice($vhost->services, 0, 3)) : '-';
            $discovered = $vhost->last_discovered_at?->diffForHumans() ?? 'never';

            $table->addRow([
                $vhost->domain,
                $status,
                $services,
                $discovered,
            ]);
        }

        $table->render();

        return 0;
    }

    protected function showVhostDetails(string $VNODE, string $VHOST): int
    {
        try {
            // Find VHost in database
            $vhost = FleetVhost::where('domain', $VHOST)
                ->whereHas('vnode', fn ($q) => $q->where('name', $VNODE))
                ->first();

            if (! $vhost) {
                $this->error("❌ VHost {$VHOST} not found on {$VNODE}");
                $this->line("   💡 Run: php artisan addfleet {$VNODE}");

                return 1;
            }

            $this->line("<fg=blue>📋 VHost Details:</> <fg=yellow>{$VHOST}</> <fg=gray>on</> <fg=cyan>{$VNODE}</>");
            $this->line('');

            // Basic info
            $this->line('<fg=blue>🖥️  Basic Information:</>');
            $this->line('   Domain: <fg=yellow>'.$vhost->domain.'</>');
            $this->line('   User: <fg=yellow>'.($vhost->getEnvVar('UUSER') ?? 'N/A').'</> (UID: '.($vhost->getEnvVar('U_UID') ?? 'N/A').')');
            $this->line('   Group: <fg=yellow>'.($vhost->getEnvVar('WUGID') ?? 'N/A').'</> (GID: '.($vhost->getEnvVar('U_GID') ?? 'N/A').')');
            $this->line('   Status: <fg=yellow>'.($vhost->is_active ? 'Active' : 'Inactive').'</>');
            $this->line('');

            // Paths
            $this->line('<fg=blue>📁 Paths:</>');
            $this->line('   User path: <fg=yellow>'.($vhost->getEnvVar('UPATH') ?? 'N/A').'</>');
            $this->line('   Web path: <fg=yellow>'.($vhost->getEnvVar('WPATH') ?? 'N/A').'</>');
            $this->line('   Mail path: <fg=yellow>'.($vhost->getEnvVar('MPATH') ?? 'N/A').'</>');
            $this->line('');

            // Database
            $this->line('<fg=blue>🗄️  Database:</>');
            $this->line('   Name: <fg=yellow>'.($vhost->getEnvVar('DNAME') ?? 'N/A').'</>');
            $this->line('   User: <fg=yellow>'.($vhost->getEnvVar('DUSER') ?? 'N/A').'</>');
            $this->line('   Type: <fg=yellow>'.($vhost->getEnvVar('DTYPE') ?? 'N/A').'</>');

            // Show DPATH for SQLite, DHOST:DPORT for MySQL/MariaDB/PostgreSQL
            $dbType = strtolower($vhost->getEnvVar('DTYPE') ?? '');
            if ($dbType === 'sqlite') {
                $this->line('   Path: <fg=yellow>'.($vhost->getEnvVar('DPATH') ?? 'N/A').'</>');
            } else {
                $this->line('   Host: <fg=yellow>'.($vhost->getEnvVar('DHOST') ?? 'N/A').':'.($vhost->getEnvVar('DPORT') ?? 'N/A').'</>');
            }
            $this->line('');

            // Services
            if ($vhost->services) {
                $this->line('<fg=blue>⚙️  Services:</>');
                $this->line('   '.implode(', ', $vhost->services));
                $this->line('');
            }

            // Show full environment variables if requested
            if ($this->option('config') && $vhost->environment_vars) {
                $this->line('<fg=blue>⚙️  Environment Variables:</>');

                $table = new Table($this->output);
                $table->setHeaders(['Variable', 'Value']);

                foreach ($vhost->environment_vars as $key => $value) {
                    // Mask sensitive values
                    if (str_contains(strtolower($key), 'pass')) {
                        $value = str_repeat('*', min(strlen($value), 16));
                    }
                    $table->addRow([$key, $value]);
                }

                $table->render();
            }

            // Show all environment variables in shell format (like shvconf) if --all is used
            if ($this->option('all') && $vhost->environment_vars) {
                $this->line('<fg=blue>⚙️  All Environment Variables (shvconf format):</>');
                $this->line('');

                // Sort variables alphabetically for consistency
                $vars = $vhost->environment_vars;
                ksort($vars);

                foreach ($vars as $key => $value) {
                    // Output in shell variable format: KEY='value'
                    // Escape single quotes in values by replacing ' with '\''
                    $escapedValue = str_replace("'", "'\\''", $value);
                    $this->line("{$key}='{$escapedValue}'");
                }
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Failed to load VHost {$VHOST}: ".$e->getMessage());

            return 1;
        }
    }

    protected function showAllVhosts(): int
    {
        // Get all vnodes with vhosts from database
        $vnodes = FleetVnode::has('vhosts')->with('vhosts')->get();

        if ($vnodes->isEmpty()) {
            $this->line('<fg=gray>No VHosts found in database</>');
            $this->line('   💡 Run: php artisan addfleet');

            return 0;
        }

        $this->line('<fg=blue>📋 All VHosts:</>');
        $this->line('');

        foreach ($vnodes as $vnode) {
            $vhostCount = $vnode->vhosts->count();
            $this->line("   <fg=cyan>{$vnode->name}</> <fg=gray>({$vhostCount} vhosts)</>");

            foreach ($vnode->vhosts as $vhost) {
                $status = $vhost->is_active ? '✅' : '❌';
                $this->line("      {$status} <fg=yellow>{$vhost->domain}</>");
            }

            $this->line('');
        }

        $this->line('<fg=gray>💡 Use "shvhost <vnode> <vhost>" for details</>');

        return 0;
    }
}
