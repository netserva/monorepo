<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVsite;
use NetServa\Fleet\Services\BinaryLaneService;

/**
 * List BinaryLane SSH Keys Command
 *
 * Lists SSH keys registered with BinaryLane account
 */
class BlKeysCommand extends Command
{
    protected $signature = 'bl:keys
                            {--vsite= : VSite name (must be binarylane provider)}
                            {--format=table : Output format (table, json)}';

    protected $description = 'List BinaryLane SSH keys';

    public function handle(BinaryLaneService $service): int
    {
        $token = $this->resolveToken();
        if (! $token) {
            return Command::FAILURE;
        }

        $service->setToken($token);
        $format = $this->option('format');

        $this->info('Fetching SSH keys...');

        try {
            $keys = $service->sshKeys();

            if ($keys->isEmpty()) {
                $this->warn('No SSH keys found.');
                $this->info('Add SSH keys in the BinaryLane web panel or via API.');

                return Command::SUCCESS;
            }

            if ($format === 'json') {
                $this->line(json_encode($keys->toArray(), JSON_PRETTY_PRINT));

                return Command::SUCCESS;
            }

            $data = [];
            foreach ($keys as $key) {
                $data[] = [
                    'ID' => $key['id'],
                    'Name' => $key['name'],
                    'Fingerprint' => $key['fingerprint'],
                ];
            }

            $this->newLine();
            $this->table(['ID', 'Name', 'Fingerprint'], $data);
            $this->newLine();
            $this->info("Total: {$keys->count()} SSH keys");
            $this->newLine();
            $this->line('Use --ssh-key=<id> with addbl to specify keys for new servers');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to fetch SSH keys: {$e->getMessage()}");

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
