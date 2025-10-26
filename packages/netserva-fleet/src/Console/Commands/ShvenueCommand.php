<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVenue;

/**
 * Show Venue Command (NetServa 3.0 CRUD: READ)
 *
 * Displays venue information
 */
class ShvenueCommand extends Command
{
    protected $signature = 'shvenue
                            {name? : Specific venue name to display}
                            {--format=table : Output format (table, json, csv)}';

    protected $description = 'Show venue information (NetServa 3.0 CRUD: Read)';

    public function handle(): int
    {
        $name = $this->argument('name');
        $format = $this->option('format');

        if ($name) {
            return $this->showSingleVenue($name, $format);
        }

        return $this->showAllVenues($format);
    }

    protected function showSingleVenue(string $name, string $format): int
    {
        $venue = FleetVenue::where('name', $name)->with('vsites')->first();

        if (! $venue) {
            $this->error("Venue not found: {$name}");

            return Command::FAILURE;
        }

        if ($format === 'json') {
            $this->line(json_encode($venue->toArray(), JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $this->info("Venue: {$venue->name}");
        $this->newLine();

        $this->table(
            ['Property', 'Value'],
            [
                ['Name', $venue->name],
                ['Provider', $venue->provider],
                ['Location', $venue->location ?? 'N/A'],
                ['Description', $venue->description ?? 'N/A'],
                ['Status', $venue->is_active ? 'Active' : 'Inactive'],
                ['VSites', $venue->vsites->count()],
                ['Created', $venue->created_at?->format('Y-m-d H:i:s')],
            ]
        );

        if ($venue->vsites->count() > 0) {
            $this->newLine();
            $this->info('VSites:');
            $vsiteData = [];
            foreach ($venue->vsites as $vsite) {
                $vsiteData[] = [
                    'Name' => $vsite->name,
                    'Technology' => $vsite->technology,
                    'VNodes' => $vsite->vnodes->count(),
                ];
            }
            $this->table(['Name', 'Technology', 'VNodes'], $vsiteData);
        }

        return Command::SUCCESS;
    }

    protected function showAllVenues(string $format): int
    {
        $venues = FleetVenue::with('vsites')->orderBy('name')->get();

        if ($venues->isEmpty()) {
            $this->warn('No venues found.');
            $this->info('Create a venue: addvenue <name> <provider> --location=<location>');

            return Command::SUCCESS;
        }

        if ($format === 'json') {
            $this->line(json_encode($venues->toArray(), JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        if ($format === 'csv') {
            $this->line('Name,Provider,Location,VSites,Status');
            foreach ($venues as $venue) {
                $this->line(sprintf(
                    '%s,%s,%s,%d,%s',
                    $venue->name,
                    $venue->provider,
                    $venue->location ?? '',
                    $venue->vsites->count(),
                    $venue->is_active ? 'active' : 'inactive'
                ));
            }

            return Command::SUCCESS;
        }

        // Table format
        $data = [];
        foreach ($venues as $venue) {
            $data[] = [
                'Name' => $venue->name,
                'Provider' => $venue->provider,
                'Location' => $venue->location ?? 'N/A',
                'VSites' => $venue->vsites->count(),
                'Status' => $venue->is_active ? '✓ Active' : '✗ Inactive',
            ];
        }

        $this->table(['Name', 'Provider', 'Location', 'VSites', 'Status'], $data);
        $this->newLine();
        $this->info('Total: '.$venues->count().' venues');

        return Command::SUCCESS;
    }
}
