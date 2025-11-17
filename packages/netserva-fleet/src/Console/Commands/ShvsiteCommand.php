<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVenue;
use NetServa\Fleet\Models\FleetVsite;

/**
 * Show VSite Command (NetServa 3.0 CRUD: READ)
 *
 * Displays vsite information
 */
class ShvsiteCommand extends Command
{
    protected $signature = 'shvsite
                            {venue? : Filter by venue name}
                            {name? : Specific vsite name to display}
                            {--format=table : Output format (table, json, csv)}';

    protected $description = 'Show vsite information (NetServa 3.0 CRUD: Read)';

    public function handle(): int
    {
        $venueName = $this->argument('venue');
        $name = $this->argument('name');
        $format = $this->option('format');

        if ($venueName && $name) {
            return $this->showSingleVSite($venueName, $name, $format);
        }

        if ($venueName) {
            return $this->showVSitesByVenue($venueName, $format);
        }

        return $this->showAllVSites($format);
    }

    protected function showSingleVSite(string $venueName, string $name, string $format): int
    {
        $vsite = FleetVsite::where('name', $name)
            ->with(['venue', 'vnodes'])
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
                ['Venue', $vsite->venue->name],
                ['Technology', $vsite->technology],
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
                    'Role' => $vnode->role,
                    'IP' => $vnode->ip_address ?? 'N/A',
                    'VHosts' => $vnode->vhosts->count(),
                ];
            }
            $this->table(['Name', 'Role', 'IP', 'VHosts'], $vnodeData);
        }

        return Command::SUCCESS;
    }

    protected function showVSitesByVenue(string $venueName, string $format): int
    {
        $venue = FleetVenue::where('name', $venueName)->first();

        if (! $venue) {
            $this->error("Venue not found: {$venueName}");

            return Command::FAILURE;
        }

        $vsites = FleetVsite::where('venue_id', $venue->id)
            ->with('vnodes')
            ->orderBy('name')
            ->get();

        if ($vsites->isEmpty()) {
            $this->warn("No vsites found in venue: {$venueName}");
            $this->info("Create a vsite: addvsite {$venueName} <name> <technology>");

            return Command::SUCCESS;
        }

        if ($format === 'json') {
            $this->line(json_encode($vsites->toArray(), JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $this->info("VSites in venue: {$venueName}");
        $this->newLine();

        return $this->displayVSitesTable($vsites, $format);
    }

    protected function showAllVSites(string $format): int
    {
        $vsites = FleetVsite::with(['venue', 'vnodes'])->orderBy('name')->get();

        if ($vsites->isEmpty()) {
            $this->warn('No vsites found.');
            $this->info('Create a vsite: addvsite <venue> <name> <technology>');

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
            $this->line('Name,Venue,Technology,VNodes');
            foreach ($vsites as $vsite) {
                $this->line(sprintf(
                    '%s,%s,%s,%d',
                    $vsite->name,
                    $vsite->venue->name,
                    $vsite->technology,
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
                'Venue' => $vsite->venue->name,
                'Technology' => $vsite->technology,
                'VNodes' => $vsite->vnodes->count(),
            ];
        }

        $this->table(['Name', 'Venue', 'Technology', 'VNodes'], $data);
        $this->newLine();
        $this->info('Total: '.$vsites->count().' vsites');

        return Command::SUCCESS;
    }
}
