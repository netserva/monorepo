<?php

namespace NetServa\Core\Console\Commands;

use NetServa\Core\Services\TunnelService;
use NetServa\Fleet\Models\FleetVnode;

use function Laravel\Prompts\select;

/**
 * SSH Tunnel Management Command
 *
 * Follows NetServa CRUD pattern: tunnel (infrastructure operation)
 * Usage: tunnel <action> [host] [service]
 * Example: tunnel create markc powerdns
 * Example: tunnel list
 * Example: tunnel endpoint markc mysql
 *
 * Manages SSH tunnels for accessing remote services (PowerDNS, MySQL, Redis, etc.)
 * Uses SSH multiplexing with automatic port assignment based on hostname hash.
 */
class TunnelCommand extends BaseNetServaCommand
{
    protected $signature = 'tunnel
                           {action : Action (create, close, check, endpoint, ensure, list)}
                           {host? : SSH host identifier (VNode name)}
                           {service? : Service type (powerdns|mysql|redis|api)}
                           {--local-port= : Override local port}
                           {--remote-port= : Override remote port}
                           {--remote-host=localhost : Remote host for tunnel}
                           {--interactive : Interactive mode}';

    protected $description = 'Manage SSH tunnels for remote service access (NetServa infrastructure operation)';

    protected TunnelService $tunnelService;

    public function __construct(TunnelService $tunnelService)
    {
        parent::__construct();
        $this->tunnelService = $tunnelService;
    }

    public function handle(): int
    {
        return $this->executeWithContext(function () {
            $action = $this->argument('action');

            return match ($action) {
                'create' => $this->createTunnel(),
                'close' => $this->closeTunnel(),
                'check' => $this->checkTunnel(),
                'endpoint' => $this->getEndpoint(),
                'ensure' => $this->ensureTunnel(),
                'list' => $this->listTunnels(),
                default => $this->handleInvalidAction($action),
            };
        });
    }

    /**
     * Create SSH tunnel
     */
    protected function createTunnel(): int
    {
        $host = $this->getHost();
        if (! $host) {
            return 1;
        }

        $service = $this->getService();
        $localPort = $this->option('local-port');
        $remotePort = $this->option('remote-port');
        $remoteHost = $this->option('remote-host');

        $this->line("🔧 Creating SSH tunnel for <fg=cyan>{$host}</> service <fg=yellow>{$service}</>");

        $result = $this->tunnelService->create(
            host: $host,
            service: $service,
            localPort: $localPort ? (int) $localPort : null,
            remotePort: $remotePort ? (int) $remotePort : null,
            remoteHost: $remoteHost
        );

        if ($result['success']) {
            $this->info("✅ {$result['message']}");
            $this->line("   🌐 Endpoint: <fg=green>{$result['endpoint']}</>");
            $this->line("   📡 Local port: <fg=cyan>{$result['local_port']}</>");

            return 0;
        } else {
            $this->error("❌ Failed to create tunnel: {$result['error']}");

            return 1;
        }
    }

    /**
     * Close SSH tunnel
     */
    protected function closeTunnel(): int
    {
        $host = $this->getHost();
        if (! $host) {
            return 1;
        }

        $localPort = $this->option('local-port');

        if ($localPort) {
            $this->line("🔧 Closing SSH tunnel on port <fg=cyan>{$localPort}</>");
        } else {
            $this->line("🔧 Closing all SSH tunnels for <fg=cyan>{$host}</>");
        }

        $result = $this->tunnelService->close(
            host: $host,
            localPort: $localPort ? (int) $localPort : null
        );

        if ($result['success']) {
            $this->info("✅ {$result['message']}");

            return 0;
        } else {
            $this->error("❌ Failed to close tunnel: {$result['error']}");

            return 1;
        }
    }

    /**
     * Check if tunnel is active
     */
    protected function checkTunnel(): int
    {
        $host = $this->getHost();
        if (! $host) {
            return 1;
        }

        $localPort = $this->option('local-port');
        $service = $this->argument('service');

        if (! $localPort && $service) {
            $localPort = $this->tunnelService->calculateLocalPort($host, $service);
        }

        $isActive = $this->tunnelService->isActive($host, $localPort ? (int) $localPort : null);

        if ($isActive) {
            if ($localPort) {
                $this->info("✅ Tunnel is active on port {$localPort}");
                $this->line("   🌐 Endpoint: http://localhost:{$localPort}");
            } else {
                $this->info("✅ Tunnel(s) active for {$host}");
            }

            return 0;
        } else {
            if ($localPort) {
                $this->line("ℹ️  No active tunnel on port {$localPort}");
            } else {
                $this->line("ℹ️  No active tunnels for {$host}");
            }

            return 1;
        }
    }

    /**
     * Get tunnel endpoint URL
     */
    protected function getEndpoint(): int
    {
        $host = $this->getHost();
        if (! $host) {
            return 1;
        }

        $service = $this->getService();

        $result = $this->tunnelService->getEndpoint($host, $service);

        if ($result['success']) {
            // Output only the endpoint for easy scripting
            $this->line($result['endpoint']);

            return 0;
        } else {
            $this->error("❌ {$result['error']}");
            $this->line("   💡 Use: tunnel create {$host} {$service}");

            return 1;
        }
    }

    /**
     * Ensure tunnel exists (create if not active)
     */
    protected function ensureTunnel(): int
    {
        $host = $this->getHost();
        if (! $host) {
            return 1;
        }

        $service = $this->getService();
        $remotePort = $this->option('remote-port');

        $result = $this->tunnelService->ensure(
            host: $host,
            service: $service,
            remotePort: $remotePort ? (int) $remotePort : null
        );

        if ($result['success']) {
            if ($result['created']) {
                $this->info("✅ Tunnel created on port {$result['local_port']}");
            } else {
                $this->info("✅ Tunnel already active on port {$result['local_port']}");
            }
            $this->line("   🌐 Endpoint: <fg=green>{$result['endpoint']}</>");

            return 0;
        } else {
            $this->error("❌ Failed to ensure tunnel: {$result['error']}");

            return 1;
        }
    }

    /**
     * List all active tunnels
     */
    protected function listTunnels(): int
    {
        $tunnels = $this->tunnelService->listActive();

        if (empty($tunnels)) {
            $this->line('ℹ️  No active SSH tunnels');

            return 0;
        }

        $this->line('📡 Active SSH Tunnels:');
        $this->line('');

        $rows = [];
        foreach ($tunnels as $tunnel) {
            $rows[] = [
                'host' => $tunnel['host'],
                'port' => $tunnel['local_port'],
                'endpoint' => $tunnel['endpoint'],
            ];
        }

        $this->table(
            ['Host', 'Local Port', 'Endpoint'],
            array_map(fn ($r) => [$r['host'], $r['port'], $r['endpoint']], $rows)
        );

        $this->line('');
        $this->line('Total: <fg=cyan>'.count($tunnels).'</> tunnel(s)');

        return 0;
    }

    /**
     * Get host from argument or prompt
     */
    protected function getHost(): ?string
    {
        $host = $this->argument('host');

        if (! $host || $this->option('interactive')) {
            // Get available VNodes from database
            $vnodes = FleetVnode::pluck('name')->toArray();

            if (empty($vnodes)) {
                $this->error('❌ No VNodes found in database');
                $this->line('   💡 Run: php artisan addfleet');

                return null;
            }

            $host = select(
                label: 'Select SSH host',
                options: $vnodes,
                default: $host
            );
        }

        // Validate host exists
        if (! FleetVnode::where('name', $host)->exists()) {
            $this->error("❌ VNode '{$host}' not found in database");
            $this->line('   💡 Run: php artisan addfleet');

            return null;
        }

        return $host;
    }

    /**
     * Get service from argument or prompt
     */
    protected function getService(): string
    {
        $service = $this->argument('service');

        if (! $service || $this->option('interactive')) {
            $service = select(
                label: 'Select service',
                options: [
                    'powerdns' => 'PowerDNS API (port 8081)',
                    'mysql' => 'MySQL/MariaDB (port 3306)',
                    'redis' => 'Redis (port 6379)',
                    'api' => 'Generic API (port 8080)',
                ],
                default: $service ?? 'api'
            );
        }

        return $service;
    }

    /**
     * Handle invalid action
     */
    protected function handleInvalidAction(string $action): int
    {
        $this->error("❌ Invalid action: {$action}");
        $this->line('');
        $this->line('Valid actions:');
        $this->line('  • create   - Create SSH tunnel');
        $this->line('  • close    - Close SSH tunnel');
        $this->line('  • check    - Check if tunnel is active');
        $this->line('  • endpoint - Get tunnel endpoint URL');
        $this->line('  • ensure   - Create tunnel if not active');
        $this->line('  • list     - List all active tunnels');
        $this->line('');
        $this->line('Examples:');
        $this->line('  tunnel create markc powerdns');
        $this->line('  tunnel endpoint markc mysql');
        $this->line('  tunnel list');
        $this->line('  tunnel close markc --local-port=10621');

        return 1;
    }
}
