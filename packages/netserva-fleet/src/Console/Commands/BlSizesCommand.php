<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVsite;
use NetServa\Fleet\Services\BinaryLaneService;

/**
 * List BinaryLane Sizes Command
 *
 * Lists available VPS sizes from BinaryLane
 */
class BlSizesCommand extends Command
{
    protected $signature = 'bl:sizes
                            {--region= : Filter by region (e.g., syd, mel, bne)}
                            {--vsite= : VSite name (must be binarylane provider)}
                            {--format=table : Output format (table, json)}';

    protected $description = 'List available BinaryLane VPS sizes';

    public function handle(BinaryLaneService $service): int
    {
        $token = $this->resolveToken();
        if (! $token) {
            return Command::FAILURE;
        }

        $service->setToken($token);
        $region = $this->option('region');
        $format = $this->option('format');

        $this->info('Fetching available sizes...');

        try {
            $sizes = $service->sizes($region);

            if ($sizes->isEmpty()) {
                $this->warn('No sizes found.');

                return Command::SUCCESS;
            }

            if ($format === 'json') {
                $this->line(json_encode($sizes->toArray(), JSON_PRETTY_PRINT));

                return Command::SUCCESS;
            }

            $data = [];
            foreach ($sizes as $size) {
                if (! ($size['available'] ?? true)) {
                    continue;
                }

                $data[] = [
                    'Slug' => $size['slug'],
                    'vCPUs' => $size['vcpus'],
                    'Memory' => $this->formatMemory($size['memory_mb']),
                    'Disk' => $size['disk_gb'].'GB',
                    'Transfer' => $size['transfer_tb'] ? $size['transfer_tb'].'TB' : 'N/A',
                    'Price' => '$'.$size['price_monthly'].'/mo',
                ];
            }

            $this->newLine();
            $this->table(['Slug', 'vCPUs', 'Memory', 'Disk', 'Transfer', 'Price'], $data);
            $this->newLine();
            $this->info("Total: {$sizes->count()} sizes");

            if ($region) {
                $this->line("Filtered by region: {$region}");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to fetch sizes: {$e->getMessage()}");

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

    protected function formatMemory(?int $mb): string
    {
        if (! $mb) {
            return 'N/A';
        }

        if ($mb >= 1024) {
            return round($mb / 1024, 1).'GB';
        }

        return $mb.'MB';
    }
}
