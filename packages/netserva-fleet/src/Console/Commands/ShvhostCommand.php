<?php

namespace NetServa\Fleet\Console\Commands;

use NetServa\Core\Console\Commands\BaseNetServaCommand;
use NetServa\Core\Models\VPass;
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
 * NetServa 3.0: Uses FleetVhost model (columns on table, derived paths)
 */
class ShvhostCommand extends BaseNetServaCommand
{
    protected $signature = 'shvhost {vnode? : SSH host/VNode identifier (optional)} {vhost? : Domain name to show (optional)} {--list : List all vhosts on server} {--config : Show configuration details}';

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
            $this->error('VNODE required for listing vhosts');

            return 1;
        }

        // Query database for vhosts on this vnode
        $vhosts = FleetVhost::whereHas('vnode', fn ($q) => $q->where('name', $VNODE))
            ->orderBy('domain')
            ->get();

        $this->line("<fg=blue>VHosts on server:</> <fg=yellow>{$VNODE}</>");

        if ($vhosts->isEmpty()) {
            $this->line('   <fg=gray>No vhosts found</>');
            $this->line("   Run: php artisan addfleet {$VNODE}");

            return 0;
        }

        $table = new Table($this->output);
        $table->setHeaders(['VHost', 'Status', 'App Type', 'PHP']);

        foreach ($vhosts as $vhost) {
            $status = $vhost->status === 'active' ? 'Active' : 'Inactive';
            $appType = $vhost->app_type ?? '-';
            $php = $vhost->php_version ?? '8.4';

            $table->addRow([
                $vhost->domain,
                $status,
                $appType,
                $php,
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
                $this->error("VHost {$VHOST} not found on {$VNODE}");
                $this->line("   Run: php artisan addfleet {$VNODE}");

                return 1;
            }

            $this->line("<fg=blue>VHost Details:</> <fg=yellow>{$VHOST}</> <fg=gray>on</> <fg=cyan>{$VNODE}</>");
            $this->line('');

            // Basic info
            $this->line('<fg=blue>Basic Information:</>');
            $this->line('   Domain: <fg=yellow>'.$vhost->domain.'</>');
            $this->line('   Status: <fg=yellow>'.$vhost->status.'</>');
            $this->line('   User: <fg=yellow>'.$vhost->unix_username.'</> (UID: '.$vhost->uid.')');
            $this->line('   GID: <fg=yellow>'.$vhost->gid.'</>');
            $this->line('   PHP: <fg=yellow>'.$vhost->php_version.'</>');
            $this->line('   SSL: <fg=yellow>'.($vhost->ssl_enabled ? 'enabled' : 'disabled').'</> ('.$vhost->ssl_type.')');
            $this->line('');

            // Paths (derived from domain - NS 3.0 convention)
            $this->line('<fg=blue>Paths:</>');
            $this->line('   VPATH (home): <fg=yellow>'.$vhost->user_path.'</>');
            $this->line('   WPATH (web):  <fg=yellow>'.$vhost->web_path.'</>');
            $this->line('   MPATH (mail): <fg=yellow>'.$vhost->mail_path.'</>');
            $this->line('   Doc root:     <fg=yellow>'.$vhost->document_root.'</>');
            $this->line('');

            // Database
            if ($vhost->db_name) {
                $this->line('<fg=blue>Database:</>');
                $this->line('   Name: <fg=yellow>'.$vhost->db_name.'</>');
                $this->line('   User: <fg=yellow>'.($vhost->db_user ?? 'N/A').'</>');
                $this->line('');
            }

            // Application info
            if ($vhost->app_type) {
                $this->line('<fg=blue>Application:</>');
                $this->line('   Type: <fg=yellow>'.$vhost->app_type.'</>');
                if ($vhost->app_version) {
                    $this->line('   Version: <fg=yellow>'.$vhost->app_version.'</>');
                }
                if ($vhost->cms_admin_user) {
                    $this->line('   Admin user: <fg=yellow>'.$vhost->cms_admin_user.'</>');
                }
                $this->line('');
            }

            // Show credentials if --config is used
            if ($this->option('config')) {
                $this->line('<fg=blue>Credentials (from VPass):</>');

                $credentials = VPass::where('fleet_vhost_id', $vhost->id)->get();

                if ($credentials->isEmpty()) {
                    $this->line('   <fg=gray>No credentials stored</>');
                } else {
                    $table = new Table($this->output);
                    $table->setHeaders(['Service', 'Name', 'Username']);

                    foreach ($credentials as $cred) {
                        $table->addRow([
                            $cred->service,
                            $cred->name,
                            $cred->username ?? '-',
                        ]);
                    }

                    $table->render();
                }
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("Failed to load VHost {$VHOST}: ".$e->getMessage());

            return 1;
        }
    }

    protected function showAllVhosts(): int
    {
        // Get all vnodes with vhosts from database
        $vnodes = FleetVnode::has('vhosts')->with('vhosts')->get();

        if ($vnodes->isEmpty()) {
            $this->line('<fg=gray>No VHosts found in database</>');
            $this->line('   Run: php artisan addfleet');

            return 0;
        }

        $this->line('<fg=blue>All VHosts:</>');
        $this->line('');

        foreach ($vnodes as $vnode) {
            $vhostCount = $vnode->vhosts->count();
            $this->line("   <fg=cyan>{$vnode->name}</> <fg=gray>({$vhostCount} vhosts)</>");

            foreach ($vnode->vhosts as $vhost) {
                $status = $vhost->status === 'active' ? '+' : '-';
                $this->line("      {$status} <fg=yellow>{$vhost->domain}</>");
            }

            $this->line('');
        }

        $this->line('<fg=gray>Use "shvhost <vnode> <vhost>" for details</>');

        return 0;
    }
}
