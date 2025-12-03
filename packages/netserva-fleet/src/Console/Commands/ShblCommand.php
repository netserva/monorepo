<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVsite;
use NetServa\Fleet\Services\BinaryLaneService;

/**
 * Show BinaryLane Servers Command (NetServa 3.0 CRUD: READ)
 *
 * Lists and displays BinaryLane server information
 */
class ShblCommand extends Command
{
    protected $signature = 'shbl
                            {server? : Server ID or name to show details}
                            {--vsite= : VSite name (must be binarylane provider)}
                            {--format=table : Output format (table, json, csv)}';

    protected $description = 'Show BinaryLane servers (NetServa 3.0 CRUD: Read)';

    public function handle(BinaryLaneService $service): int
    {
        // Get token from VSite or config
        $token = $this->resolveToken($service);
        if (! $token) {
            return Command::FAILURE;
        }

        $service->setToken($token);
        $serverId = $this->argument('server');
        $format = $this->option('format');

        if ($serverId) {
            return $this->showSingleServer($service, $serverId, $format);
        }

        return $this->showAllServers($service, $format);
    }

    protected function showSingleServer(BinaryLaneService $service, string $serverId, string $format): int
    {
        $this->info("Fetching server: {$serverId}...");

        try {
            // Try by ID first
            if (is_numeric($serverId)) {
                $server = $service->getServer((int) $serverId);
            } else {
                // Search by name
                $servers = $service->listServers();
                $server = $servers->firstWhere('name', $serverId);

                if (! $server) {
                    $this->error("Server not found: {$serverId}");

                    return Command::FAILURE;
                }
            }

            if ($format === 'json') {
                $this->line(json_encode($server, JSON_PRETTY_PRINT));

                return Command::SUCCESS;
            }

            $this->newLine();
            $this->info("Server: {$server['name']}");
            $this->newLine();

            $this->table(
                ['Property', 'Value'],
                [
                    ['ID', $server['id']],
                    ['Name', $server['name']],
                    ['Status', $this->formatStatus($server['status'])],
                    ['IPv4', $server['ipv4'] ?? 'N/A'],
                    ['IPv6', $server['ipv6'] ?? 'N/A'],
                    ['Size', $server['size_slug']],
                    ['Region', $server['region_slug']],
                    ['Image', $server['image_name'] ?? $server['image_slug']],
                    ['vCPUs', $server['vcpus'] ?? 'N/A'],
                    ['Memory', $server['memory_mb'] ? $server['memory_mb'].' MB' : 'N/A'],
                    ['Disk', $server['disk_gb'] ? $server['disk_gb'].' GB' : 'N/A'],
                    ['VPC ID', $server['vpc_id'] ?? 'Public'],
                    ['Price', $server['price_monthly'] ? '$'.$server['price_monthly'].'/mo' : 'N/A'],
                    ['Created', $server['created_at'] ?? 'N/A'],
                ]
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to fetch server: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    protected function showAllServers(BinaryLaneService $service, string $format): int
    {
        $this->info('Fetching BinaryLane servers...');

        try {
            $servers = $service->listServers();

            if ($servers->isEmpty()) {
                $this->warn('No servers found.');
                $this->info('Create a server: addbl <name> <size> <image> <region>');

                return Command::SUCCESS;
            }

            if ($format === 'json') {
                $this->line(json_encode($servers->toArray(), JSON_PRETTY_PRINT));

                return Command::SUCCESS;
            }

            if ($format === 'csv') {
                $this->line('ID,Name,Status,IPv4,Size,Region,Price');
                foreach ($servers as $server) {
                    $this->line(sprintf(
                        '%d,%s,%s,%s,%s,%s,%s',
                        $server['id'],
                        $server['name'],
                        $server['status'],
                        $server['ipv4'] ?? '',
                        $server['size_slug'],
                        $server['region_slug'],
                        $server['price_monthly'] ?? ''
                    ));
                }

                return Command::SUCCESS;
            }

            // Table format
            $data = [];
            foreach ($servers as $server) {
                $data[] = [
                    'ID' => $server['id'],
                    'Name' => $server['name'],
                    'Status' => $this->formatStatus($server['status']),
                    'IPv4' => $server['ipv4'] ?? 'N/A',
                    'Size' => $server['size_slug'],
                    'Region' => $server['region_slug'],
                    'Price' => $server['price_monthly'] ? '$'.$server['price_monthly'].'/mo' : 'N/A',
                ];
            }

            $this->newLine();
            $this->table(['ID', 'Name', 'Status', 'IPv4', 'Size', 'Region', 'Price'], $data);
            $this->newLine();
            $this->info("Total: {$servers->count()} servers");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to fetch servers: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    protected function resolveToken(BinaryLaneService $service): ?string
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

        // Try config
        $token = config('fleet.binarylane.api_token');
        if ($token) {
            return $token;
        }

        $this->error('No BinaryLane API token configured.');
        $this->info('Set BINARYLANE_API_TOKEN in .env or use --vsite option');

        return null;
    }

    protected function formatStatus(string $status): string
    {
        return match ($status) {
            'active' => '<fg=green>active</>',
            'new' => '<fg=yellow>new</>',
            'off' => '<fg=red>off</>',
            'archive' => '<fg=gray>archive</>',
            default => $status,
        };
    }
}
