<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Services\Infrastructure\DnsmasqService;

class ChDnsmasqCommand extends Command
{
    protected $signature = 'chdnsmasq {vnode : VNode name (e.g., gw)}
                            {hostname : Hostname to modify}
                            {--ip= : New IP address}
                            {--mac= : New MAC address}
                            {--new-hostname= : Rename hostname}';

    protected $description = 'Modify DNS host record in dnsmasq (UCI) on router/gateway vnode';

    public function handle(DnsmasqService $dnsmasqService): int
    {
        $vnodeName = $this->argument('vnode');
        $hostname = $this->argument('hostname');
        $newIp = $this->option('ip');
        $newMac = $this->option('mac');
        $newHostname = $this->option('new-hostname');

        $vnode = FleetVnode::where('name', $vnodeName)->first();

        if (! $vnode) {
            $this->error("VNode '{$vnodeName}' not found");

            return 1;
        }

        if (! $vnode->sshHost) {
            $this->error("VNode '{$vnodeName}' has no SSH host configured");

            return 1;
        }

        // Validate that at least one change is specified
        if (! $newIp && ! $newMac && ! $newHostname) {
            $this->error('You must specify at least one change: --ip, --mac, or --new-hostname');

            return 1;
        }

        // Validate IP if provided
        if ($newIp && ! filter_var($newIp, FILTER_VALIDATE_IP)) {
            $this->error("Invalid IP address: {$newIp}");

            return 1;
        }

        $this->info('Modifying DNS record...');
        $this->line("VNode: {$vnode->name}");
        $this->line("Current Hostname: {$hostname}");
        if ($newHostname) {
            $this->line("New Hostname: {$newHostname}");
        }
        if ($newIp) {
            $this->line("New IP: {$newIp}");
        }
        if ($newMac) {
            $this->line("New MAC: {$newMac}");
        }
        $this->newLine();

        try {
            $changes = array_filter([
                'ip' => $newIp,
                'mac' => $newMac,
                'hostname' => $newHostname,
            ]);

            $result = $dnsmasqService->updateUciHost($vnode, $hostname, $changes);

            if ($result['success']) {
                // Update local cache
                $dnsmasqService->updateInCache($vnode, $hostname, $changes);

                $this->info('✓ DNS record modified successfully!');
                $this->newLine();
                $this->line($result['output']);

                return 0;
            } else {
                $this->error('✗ Failed to modify DNS record');
                $this->line($result['error'] ?? 'Unknown error');

                return 1;
            }
        } catch (\Exception $e) {
            $this->error('✗ Failed to modify DNS record: '.$e->getMessage());
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return 1;
        }
    }
}
