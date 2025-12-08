<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVsite;

/**
 * Show VSite Command (NetServa 3.0 CRUD: READ)
 *
 * Displays vsite information
 * VSite is now the top of the hierarchy: VSite → VNode → VHost
 */
class ShvsiteCommand extends Command
{
    protected $signature = 'shvsite
                            {name? : Specific vsite name to display}
                            {--provider= : Filter by provider}
                            {--technology= : Filter by technology}
                            {--format=table : Output format (table, json, csv)}';

    protected $description = 'Show vsite information (NetServa 3.0 CRUD: Read)';

    public function handle(): int
    {
        $name = $this->argument('name');
        $format = $this->option('format');

        if ($name) {
            return $this->showSingleVSite($name, $format);
        }

        return $this->showAllVSites($format);
    }

    protected function showSingleVSite(string $name, string $format): int
    {
        $vsite = FleetVsite::where('name', $name)
            ->with('vnodes')
            ->first();

        if (! $vsite) {
            $this->error("VSite not found: {$name}");

            return Command::FAILURE;
        }

        if ($format === 'json') {
            $this->line(json_encode($vsite->toArray(), JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $this->info("VSite: {$vsite->name}");
        $this->newLine();

        $this->table(
            ['Property', 'Value'],
            [
                ['Name', $vsite->name],
                ['Provider', $vsite->provider ?? 'N/A'],
                ['Technology', $vsite->technology ?? 'N/A'],
                ['Location', $vsite->location ?? 'N/A'],
                ['Description', $vsite->description ?? 'N/A'],
                ['VNodes', $vsite->vnodes->count()],
                ['Created', $vsite->created_at?->format('Y-m-d H:i:s')],
            ]
        );

        if ($vsite->vnodes->count() > 0) {
            $this->newLine();
            $this->info('VNodes:');
            $vnodeData = [];
            foreach ($vsite->vnodes as $vnode) {
                $vnodeData[] = [
                    'Name' => $vnode->name,
                    'Role' => $vnode->role ?? 'N/A',
                    'IP' => $vnode->ip_address ?? 'N/A',
                    'VHosts' => $vnode->vhosts->count(),
                ];
            }
            $this->table(['Name', 'Role', 'IP', 'VHosts'], $vnodeData);
        }

        return Command::SUCCESS;
    }

    protected function showAllVSites(string $format): int
    {
        $query = FleetVsite::with('vnodes')->orderBy('name');

        // Apply filters
        if ($provider = $this->option('provider')) {
            $query->where('provider', $provider);
        }

        if ($technology = $this->option('technology')) {
            $query->where('technology', $technology);
        }

        $vsites = $query->get();

        if ($vsites->isEmpty()) {
            $this->warn('No vsites found.');
            $this->info('Create a vsite: addvsite <name> <provider> <technology>');

            return Command::SUCCESS;
        }

        if ($format === 'json') {
            $this->line(json_encode($vsites->toArray(), JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        return $this->displayVSitesTable($vsites, $format);
    }

    protected function displayVSitesTable($vsites, string $format): int
    {
        if ($format === 'csv') {
            $this->line('Name,Provider,Technology,Location,VNodes');
            foreach ($vsites as $vsite) {
                $this->line(sprintf(
                    '%s,%s,%s,%s,%d',
                    $vsite->name,
                    $vsite->provider ?? '',
                    $vsite->technology ?? '',
                    $vsite->location ?? '',
                    $vsite->vnodes->count()
                ));
            }

            return Command::SUCCESS;
        }

        // Table format
        $data = [];
        foreach ($vsites as $vsite) {
            $data[] = [
                'Name' => $vsite->name,
                'Provider' => $vsite->provider ?? 'N/A',
                'Technology' => $vsite->technology ?? 'N/A',
                'Location' => $vsite->location ?? 'N/A',
                'VNodes' => $vsite->vnodes->count(),
            ];
        }

        $this->table(['Name', 'Provider', 'Technology', 'Location', 'VNodes'], $data);
        $this->newLine();
        $this->info('Total: '.$vsites->count().' vsites');

        return Command::SUCCESS;
    }
}
