<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Services\Infrastructure\DnsmasqService;

class DelDnsmasqCommand extends Command
{
    protected $signature = 'deldnsmasq {vnode : VNode name (e.g., gw)}
                            {hostname : Hostname to delete}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Delete DNS host record from dnsmasq (UCI) on router/gateway vnode';

    public function handle(DnsmasqService $dnsmasqService): int
    {
        $vnodeName = $this->argument('vnode');
        $hostname = $this->argument('hostname');

        $vnode = FleetVnode::where('name', $vnodeName)->first();

        if (! $vnode) {
            $this->error("VNode '{$vnodeName}' not found");

            return 1;
        }

        if (! $vnode->sshHost) {
            $this->error("VNode '{$vnodeName}' has no SSH host configured");

            return 1;
        }

        $this->warn('Deleting DNS record...');
        $this->line("VNode: {$vnode->name}");
        $this->line("Hostname: {$hostname}");
        $this->newLine();

        // Confirm deletion unless --force
        if (! $this->option('force')) {
            if (! $this->confirm("Are you sure you want to delete DNS record for '{$hostname}'?")) {
                $this->info('Deletion cancelled');

                return 0;
            }
        }

        try {
            $result = $dnsmasqService->deleteUciHost($vnode, $hostname);

            if ($result['success']) {
                // Update local cache
                $dnsmasqService->deleteFromCache($vnode, $hostname);

                $this->info('✓ DNS record deleted successfully!');
                $this->newLine();
                $this->line($result['output']);

                return 0;
            } else {
                $this->error('✗ Failed to delete DNS record');
                $this->line($result['error'] ?? 'Unknown error');

                return 1;
            }
        } catch (\Exception $e) {
            $this->error('✗ Failed to delete DNS record: '.$e->getMessage());
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return 1;
        }
    }
}
