<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVsite;
use NetServa\Fleet\Services\BinaryLaneService;

/**
 * Change BinaryLane Server Command (NetServa 3.0 CRUD: UPDATE)
 *
 * Performs actions on BinaryLane servers (power, resize, rename)
 */
class ChblCommand extends Command
{
    protected $signature = 'chbl
                            {server : Server ID or name}
                            {action : Action to perform (power-on, power-off, shutdown, reboot, power-cycle, resize, rename)}
                            {value? : Value for action (size slug for resize, new name for rename)}
                            {--vsite= : VSite name (must be binarylane provider)}
                            {--force : Skip confirmation for destructive actions}
                            {--wait : Wait for action to complete}';

    protected $description = 'Change BinaryLane server state (NetServa 3.0 CRUD: Update)';

    protected array $validActions = [
        'power-on',
        'power-off',
        'shutdown',
        'reboot',
        'power-cycle',
        'resize',
        'rename',
    ];

    public function handle(BinaryLaneService $service): int
    {
        $serverArg = $this->argument('server');
        $action = $this->argument('action');
        $value = $this->argument('value');
        $force = $this->option('force');
        $wait = $this->option('wait');

        // Validate action
        if (! in_array($action, $this->validActions)) {
            $this->error("Invalid action: {$action}");
            $this->info('Valid actions: '.implode(', ', $this->validActions));

            return Command::FAILURE;
        }

        // Validate value for actions that require it
        if (in_array($action, ['resize', 'rename']) && ! $value) {
            $this->error("Action '{$action}' requires a value.");
            if ($action === 'resize') {
                $this->info('Usage: chbl <server> resize <size-slug>');
                $this->info('Run bl:sizes to see available sizes.');
            } else {
                $this->info('Usage: chbl <server> rename <new-name>');
            }

            return Command::FAILURE;
        }

        // Get token
        $token = $this->resolveToken();
        if (! $token) {
            return Command::FAILURE;
        }

        $service->setToken($token);

        // Resolve server
        try {
            $server = $this->resolveServer($service, $serverArg);
            if (! $server) {
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("Failed to find server: {$e->getMessage()}");

            return Command::FAILURE;
        }

        // Confirm destructive actions
        $destructiveActions = ['power-off', 'power-cycle', 'reboot', 'resize'];
        if (in_array($action, $destructiveActions) && ! $force) {
            $actionDesc = $this->getActionDescription($action, $value);
            if (! $this->confirm("Are you sure you want to {$actionDesc} server '{$server['name']}' ({$server['id']})?")) {
                $this->info('Operation cancelled.');

                return Command::SUCCESS;
            }
        }

        $this->info("Performing {$action} on server: {$server['name']} ({$server['id']})");

        try {
            $result = match ($action) {
                'power-on' => $service->powerOn($server['id']),
                'power-off' => $service->powerOff($server['id']),
                'shutdown' => $service->shutdown($server['id']),
                'reboot' => $service->reboot($server['id']),
                'power-cycle' => $service->powerCycle($server['id']),
                'resize' => $service->resize($server['id'], $value),
                'rename' => $service->rename($server['id'], $value),
            };

            $actionId = $result['action']['id'] ?? null;

            $this->info('Action submitted successfully!');

            if ($actionId) {
                $this->line("  Action ID: {$actionId}");
                $this->line("  Status: {$result['action']['status']}");

                if ($wait) {
                    $this->newLine();
                    $this->info('Waiting for action to complete...');

                    try {
                        $finalAction = $service->waitForAction($actionId, 300, 5);
                        $this->info("Action completed with status: {$finalAction['status']}");
                    } catch (\Exception $e) {
                        $this->warn("Timed out waiting: {$e->getMessage()}");
                    }
                }
            }

            // Show updated server info
            $this->newLine();
            sleep(2); // Brief pause for status update
            $updatedServer = $service->getServer($server['id']);
            $this->info('Current server status:');
            $this->line("  Status: {$updatedServer['status']}");
            $this->line('  IPv4: '.($updatedServer['ipv4'] ?? 'N/A'));
            if ($action === 'resize') {
                $this->line("  Size: {$updatedServer['size_slug']}");
            }
            if ($action === 'rename') {
                $this->line("  Name: {$updatedServer['name']}");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to perform action: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    protected function resolveServer(BinaryLaneService $service, string $serverArg): ?array
    {
        // Try by ID first
        if (is_numeric($serverArg)) {
            return $service->getServer((int) $serverArg);
        }

        // Search by name
        $servers = $service->listServers();
        $server = $servers->firstWhere('name', $serverArg);

        if (! $server) {
            $this->error("Server not found: {$serverArg}");
            $this->info('Use shbl to list available servers.');

            return null;
        }

        return $server;
    }

    protected function getActionDescription(string $action, ?string $value): string
    {
        return match ($action) {
            'power-on' => 'power on',
            'power-off' => 'force power off',
            'shutdown' => 'gracefully shutdown',
            'reboot' => 'reboot',
            'power-cycle' => 'power cycle',
            'resize' => "resize to '{$value}'",
            'rename' => "rename to '{$value}'",
            default => $action,
        };
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
