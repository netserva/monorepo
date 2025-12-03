<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVsite;
use NetServa\Fleet\Services\BinaryLaneService;

/**
 * List BinaryLane Images Command
 *
 * Lists available OS images from BinaryLane
 */
class BlImagesCommand extends Command
{
    protected $signature = 'bl:images
                            {--type=distribution : Image type (distribution, backup, application)}
                            {--vsite= : VSite name (must be binarylane provider)}
                            {--format=table : Output format (table, json)}';

    protected $description = 'List available BinaryLane OS images';

    public function handle(BinaryLaneService $service): int
    {
        $token = $this->resolveToken();
        if (! $token) {
            return Command::FAILURE;
        }

        $service->setToken($token);
        $type = $this->option('type');
        $format = $this->option('format');

        $this->info("Fetching {$type} images...");

        try {
            $images = $service->images($type);

            if ($images->isEmpty()) {
                $this->warn('No images found.');

                return Command::SUCCESS;
            }

            if ($format === 'json') {
                $this->line(json_encode($images->toArray(), JSON_PRETTY_PRINT));

                return Command::SUCCESS;
            }

            $data = [];
            foreach ($images as $image) {
                $data[] = [
                    'Slug' => $image['slug'] ?? 'N/A',
                    'Distribution' => $image['distribution'] ?? 'N/A',
                    'Name' => $image['name'],
                    'Min Disk' => $image['min_disk_size'] ? $image['min_disk_size'].'GB' : 'N/A',
                ];
            }

            $this->newLine();
            $this->table(['Slug', 'Distribution', 'Name', 'Min Disk'], $data);
            $this->newLine();
            $this->info("Total: {$images->count()} images");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to fetch images: {$e->getMessage()}");

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
