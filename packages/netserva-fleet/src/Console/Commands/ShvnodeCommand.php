<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;

/**
 * Show VNode Command (NetServa 3.0 CRUD: READ)
 *
 * Displays vnode information
 */
class ShvnodeCommand extends Command
{
    protected $signature = 'shvnode
                            {vsite? : Filter by vsite name}
                            {name? : Specific vnode name to display}
                            {--format=table : Output format (table, json, csv)}';

    protected $description = 'Show vnode information (NetServa 3.0 CRUD: Read)';

    public function handle(): int
    {
        $vsiteName = $this->argument('vsite');
        $name = $this->argument('name');
        $format = $this->option('format');

        if ($vsiteName && $name) {
            return $this->showSingleVNode($name, $format);
        }

        if ($vsiteName) {
            return $this->showVNodesByVSite($vsiteName, $format);
        }

        return $this->showAllVNodes($format);
    }

    protected function showSingleVNode(string $name, string $format): int
    {
        $vnode = FleetVnode::where('name', $name)
            ->with(['vsite.venue', 'sshHost', 'vhosts'])
            ->first();

        if (! $vnode) {
            $this->error("VNode not found: {$name}");

            return Command::FAILURE;
        }

        if ($format === 'json') {
            $this->line(json_encode($vnode->toArray(), JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $this->info("VNode: {$vnode->name}");
        $this->newLine();

        $this->table(
            ['Property', 'Value'],
            [
                ['Name', $vnode->name],
                ['VSite', $vnode->vsite->name],
                ['Venue', $vnode->vsite->venue->name],
                ['Role', $vnode->role],
                ['Environment', $vnode->environment],
                ['IP Address', $vnode->ip_address ?? 'N/A'],
                ['FQDN', $vnode->fqdn ?? 'N/A'],
                ['OS', $vnode->operating_system ?? 'N/A'],
                ['CPU Cores', $vnode->cpu_cores ?? 'N/A'],
                ['Memory', $vnode->memory_mb ? $vnode->memory_mb.' MB' : 'N/A'],
                ['Disk', $vnode->disk_gb ? $vnode->disk_gb.' GB' : 'N/A'],
                ['SSH Host', $vnode->sshHost ? "{$vnode->sshHost->host} ({$vnode->sshHost->hostname})" : 'N/A'],
                ['Email Capable', $vnode->email_capable ? 'Yes' : 'No'],
                ['Status', ucfirst($vnode->status)],
                ['VHosts', $vnode->vhosts->count()],
                ['Last Discovered', $vnode->last_discovered_at?->format('Y-m-d H:i:s') ?? 'Never'],
                ['Created', $vnode->created_at?->format('Y-m-d H:i:s')],
            ]
        );

        if ($vnode->vhosts->count() > 0) {
            $this->newLine();
            $this->info('VHosts:');
            $vhostData = [];
            foreach ($vnode->vhosts->take(10) as $vhost) {
                $vhostData[] = [
                    'Domain' => $vhost->domain,
                    'Type' => $vhost->instance_type ?? 'N/A',
                    'Status' => ucfirst($vhost->status),
                ];
            }
            $this->table(['Domain', 'Type', 'Status'], $vhostData);

            if ($vnode->vhosts->count() > 10) {
                $this->line('  ... and '.($vnode->vhosts->count() - 10).' more vhosts');
            }
        }

        return Command::SUCCESS;
    }

    protected function showVNodesByVSite(string $vsiteName, string $format): int
    {
        $vsite = FleetVsite::where('name', $vsiteName)->first();

        if (! $vsite) {
            $this->error("VSite not found: {$vsiteName}");

            return Command::FAILURE;
        }

        $vnodes = FleetVnode::where('vsite_id', $vsite->id)
            ->with(['sshHost', 'vhosts'])
            ->orderBy('name')
            ->get();

        if ($vnodes->isEmpty()) {
            $this->warn("No vnodes found in vsite: {$vsiteName}");
            $this->info("Create a vnode: addvnode {$vsiteName} <name> <ssh-host>");

            return Command::SUCCESS;
        }

        if ($format === 'json') {
            $this->line(json_encode($vnodes->toArray(), JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $this->info("VNodes in vsite: {$vsiteName}");
        $this->newLine();

        return $this->displayVNodesTable($vnodes, $format);
    }

    protected function showAllVNodes(string $format): int
    {
        $vnodes = FleetVnode::with(['vsite', 'sshHost', 'vhosts'])->orderBy('name')->get();

        if ($vnodes->isEmpty()) {
            $this->warn('No vnodes found.');
            $this->info('Create a vnode: addvnode <vsite> <name> <ssh-host>');

            return Command::SUCCESS;
        }

        if ($format === 'json') {
            $this->line(json_encode($vnodes->toArray(), JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        return $this->displayVNodesTable($vnodes, $format);
    }

    protected function displayVNodesTable($vnodes, string $format): int
    {
        if ($format === 'csv') {
            $this->line('Name,VSite,Role,IP,VHosts,Status');
            foreach ($vnodes as $vnode) {
                $this->line(sprintf(
                    '%s,%s,%s,%s,%d,%s',
                    $vnode->name,
                    $vnode->vsite->name,
                    $vnode->role,
                    $vnode->ip_address ?? '',
                    $vnode->vhosts->count(),
                    $vnode->status
                ));
            }

            return Command::SUCCESS;
        }

        // Table format
        $data = [];
        foreach ($vnodes as $vnode) {
            $data[] = [
                'Name' => $vnode->name,
                'VSite' => $vnode->vsite->name,
                'Role' => $vnode->role,
                'IP' => $vnode->ip_address ?? 'N/A',
                'VHosts' => $vnode->vhosts->count(),
                'Status' => ucfirst($vnode->status),
            ];
        }

        $this->table(['Name', 'VSite', 'Role', 'IP', 'VHosts', 'Status'], $data);
        $this->newLine();
        $this->info('Total: '.$vnodes->count().' vnodes');

        return Command::SUCCESS;
    }
}
