<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVsite;
use NetServa\Fleet\Services\BinaryLaneService;

/**
 * List BinaryLane VPCs Command
 *
 * Lists Virtual Private Clouds from BinaryLane
 */
class BlVpcsCommand extends Command
{
    protected $signature = 'bl:vpcs
                            {--vsite= : VSite name (must be binarylane provider)}
                            {--format=table : Output format (table, json)}';

    protected $description = 'List BinaryLane VPCs (Virtual Private Clouds)';

    public function handle(BinaryLaneService $service): int
    {
        $token = $this->resolveToken();
        if (! $token) {
            return Command::FAILURE;
        }

        $service->setToken($token);
        $format = $this->option('format');

        $this->info('Fetching VPCs...');

        try {
            $vpcs = $service->vpcs();

            if ($vpcs->isEmpty()) {
                $this->warn('No VPCs found.');
                $this->info('VPCs can be created in the BinaryLane web panel.');

                return Command::SUCCESS;
            }

            if ($format === 'json') {
                $this->line(json_encode($vpcs->toArray(), JSON_PRETTY_PRINT));

                return Command::SUCCESS;
            }

            $data = [];
            foreach ($vpcs as $vpc) {
                $data[] = [
                    'ID' => $vpc['id'],
                    'Name' => $vpc['name'],
                    'Region' => $vpc['region'],
                    'IP Range' => $vpc['ip_range'],
                ];
            }

            $this->newLine();
            $this->table(['ID', 'Name', 'Region', 'IP Range'], $data);
            $this->newLine();
            $this->info("Total: {$vpcs->count()} VPCs");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to fetch VPCs: {$e->getMessage()}");

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
