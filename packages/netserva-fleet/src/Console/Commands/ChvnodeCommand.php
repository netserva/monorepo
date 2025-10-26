<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVNode;

/**
 * Change VNode Command (NetServa 3.0 CRUD: UPDATE)
 *
 * Updates vnode configuration
 */
class ChvnodeCommand extends Command
{
    protected $signature = 'chvnode
                            {name : VNode name to update}
                            {--role= : Update role (webserver, mailserver, database, etc.)}
                            {--environment= : Update environment (production, staging, development)}
                            {--fqdn= : Update FQDN}
                            {--status= : Update status (active, inactive, maintenance)}';

    protected $description = 'Change vnode configuration (NetServa 3.0 CRUD: Update)';

    public function handle(): int
    {
        $name = $this->argument('name');

        $vnode = FleetVNode::where('name', $name)->first();

        if (! $vnode) {
            $this->error("VNode not found: {$name}");
            $this->info('Available vnodes: '.FleetVNode::pluck('name')->implode(', '));

            return Command::FAILURE;
        }

        $updated = false;
        $changes = [];

        if ($role = $this->option('role')) {
            $oldRole = $vnode->role;
            $vnode->role = $role;
            $changes[] = "role: {$oldRole} → {$role}";
            $updated = true;
        }

        if ($environment = $this->option('environment')) {
            $oldEnv = $vnode->environment;
            $vnode->environment = $environment;
            $changes[] = "environment: {$oldEnv} → {$environment}";
            $updated = true;
        }

        if ($fqdn = $this->option('fqdn')) {
            $oldFqdn = $vnode->fqdn ?? 'none';
            $vnode->fqdn = $fqdn;
            $changes[] = "fqdn: {$oldFqdn} → {$fqdn}";
            $updated = true;
        }

        if ($status = $this->option('status')) {
            $oldStatus = $vnode->status;
            $vnode->status = $status;
            $changes[] = "status: {$oldStatus} → {$status}";
            $updated = true;
        }

        if (! $updated) {
            $this->warn('No changes specified. Use --role, --environment, --fqdn, or --status options.');

            return Command::FAILURE;
        }

        $vnode->save();

        $this->info('✓ VNode updated successfully');
        $this->newLine();
        $this->line('Changes applied:');
        foreach ($changes as $change) {
            $this->line("  • {$change}");
        }

        return Command::SUCCESS;
    }
}
