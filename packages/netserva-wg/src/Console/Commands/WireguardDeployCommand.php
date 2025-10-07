<?php

namespace NetServa\Wg\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Wg\Models\WireguardServer;
use NetServa\Wg\Services\WireguardService;

class WireguardDeployCommand extends Command
{
    protected $signature = 'wireguard:deploy {server}';

    protected $description = 'Deploy WireGuard server configuration';

    public function handle(WireguardService $service): int
    {
        $serverName = $this->argument('server');
        $server = WireguardServer::where('name', $serverName)->first();

        if (! $server) {
            $this->error("Server '{$serverName}' not found");

            return 1;
        }

        if (! $server->ssh_host_id) {
            $this->error("Server '{$serverName}' has no SSH configuration");

            return 1;
        }

        $this->info("Deploying WireGuard configuration for: {$server->name}");

        try {
            $service->deployServer($server);
            $this->info("✅ Successfully deployed {$server->name}");

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Deployment failed: '.$e->getMessage());

            return 1;
        }
    }
}
