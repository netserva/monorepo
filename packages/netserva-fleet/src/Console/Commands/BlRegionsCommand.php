<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVsite;
use NetServa\Fleet\Services\BinaryLaneService;

/**
 * List BinaryLane Regions Command
 *
 * Lists available data center regions from BinaryLane
 */
class BlRegionsCommand extends Command
{
    protected $signature = 'bl:regions
                            {--vsite= : VSite name (must be binarylane provider)}
                            {--format=table : Output format (table, json)}';

    protected $description = 'List available BinaryLane regions';

    public function handle(BinaryLaneService $service): int
    {
        $token = $this->resolveToken();
        if (! $token) {
            return Command::FAILURE;
        }

        $service->setToken($token);
        $format = $this->option('format');

        $this->info('Fetching available regions...');

        try {
            $regions = $service->regions();

            if ($regions->isEmpty()) {
                $this->warn('No regions found.');

                return Command::SUCCESS;
            }

            if ($format === 'json') {
                $this->line(json_encode($regions->toArray(), JSON_PRETTY_PRINT));

                return Command::SUCCESS;
            }

            $data = [];
            foreach ($regions as $region) {
                $data[] = [
                    'Slug' => $region['slug'],
                    'Name' => $region['name'],
                    'Available' => $region['available'] ? 'Yes' : 'No',
                    'Features' => implode(', ', $region['features'] ?? []),
                ];
            }

            $this->newLine();
            $this->table(['Slug', 'Name', 'Available', 'Features'], $data);
            $this->newLine();
            $this->info("Total: {$regions->count()} regions");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to fetch regions: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    protected function resolveToken(): ?string
    {
        $vsiteName = $this->option('vsite');

        if ($vsiteName) {
            $vsite = FleetVsite::where('name', $vsiteName)
                ->where('provider', 'binarylane')
                ->first();

            if (! $vsite) {
                $this->error("VSite not found or not a BinaryLane provider: {$vsiteName}");

                return null;
            }

            $credentials = $vsite->api_credentials;

            return $credentials['token'] ?? null;
        }

        $token = config('fleet.binarylane.api_token');
        if ($token) {
            return $token;
        }

        $this->error('No BinaryLane API token configured.');
        $this->info('Set BINARYLANE_API_TOKEN in .env or use --vsite option');

        return null;
    }
}
