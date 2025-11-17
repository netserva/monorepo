<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Services\Infrastructure\DnsmasqService;

class AddDnsmasqCommand extends Command
{
    protected $signature = 'adddnsmasq {vnode : VNode name (e.g., gw)}
                            {hostname : Hostname to add}
                            {ip : IP address}
                            {--mac= : MAC address (for DHCP static lease)}
                            {--dns : Enable DNS for this host (default: true)}';

    protected $description = 'Add DNS host record to dnsmasq (UCI) on router/gateway vnode';

    public function handle(DnsmasqService $dnsmasqService): int
    {
        $vnodeName = $this->argument('vnode');
        $hostname = $this->argument('hostname');
        $ip = $this->argument('ip');
        $mac = $this->option('mac');
        $dns = $this->option('dns');

        $vnode = FleetVnode::where('name', $vnodeName)->first();

        if (! $vnode) {
            $this->error("VNode '{$vnodeName}' not found");

            return 1;
        }

        if (! $vnode->sshHost) {
            $this->error("VNode '{$vnodeName}' has no SSH host configured");

            return 1;
        }

        // Validate IP address
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->error("Invalid IP address: {$ip}");

            return 1;
        }

        // Determine record type
        $recordType = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 'AAAA' : 'A';

        $this->info('Adding DNS record...');
        $this->line("VNode: {$vnode->name}");
        $this->line("Hostname: {$hostname}");
        $this->line("IP: {$ip}");
        $this->line("Type: {$recordType}");
        $this->newLine();

        try {
            $result = $dnsmasqService->addUciHost($vnode, $hostname, $ip, $mac, $dns);

            if ($result['success']) {
                // Update local cache
                $dnsmasqService->addToCache($vnode, $hostname, $ip, $mac);

                $this->info('✓ DNS record added successfully!');
                $this->newLine();
                $this->line($result['output']);

                return 0;
            } else {
                $this->error('✗ Failed to add DNS record');
                $this->line($result['error'] ?? 'Unknown error');

                return 1;
            }
        } catch (\Exception $e) {
            $this->error('✗ Failed to add DNS record: '.$e->getMessage());
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return 1;
        }
    }
}
