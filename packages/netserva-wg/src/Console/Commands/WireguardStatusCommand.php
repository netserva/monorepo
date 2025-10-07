<?php

namespace NetServa\Wg\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Wg\Models\WireguardServer;
use NetServa\Wg\Services\WireguardService;

class WireguardStatusCommand extends Command
{
    protected $signature = 'wireguard:status {server?}';

    protected $description = 'Check status of WireGuard servers';

    public function handle(WireguardService $service): int
    {
        $serverName = $this->argument('server');

        if ($serverName) {
            $server = WireguardServer::where('name', $serverName)->first();
            if (! $server) {
                $this->error("Server '{$serverName}' not found");

                return 1;
            }

            $this->checkServer($server, $service);
        } else {
            $servers = WireguardServer::where('is_active', true)->get();

            if ($servers->isEmpty()) {
                $this->info('No active WireGuard servers found');

                return 0;
            }

            foreach ($servers as $server) {
                $this->checkServer($server, $service);
                $this->newLine();
            }
        }

        return 0;
    }

    private function checkServer(WireguardServer $server, WireguardService $service): void
    {
        $this->info("Server: {$server->name}");
        $this->line("Network: {$server->network_cidr}");
        $this->line("Endpoint: {$server->endpoint}:{$server->listen_port}");
        $this->line("Status: {$server->status}");

        if ($server->ssh_host_id) {
            $status = $service->checkServerStatus($server);
            $this->line('Connection: '.ucfirst($status['status']));

            if (! empty($status['peers'])) {
                $this->line('Connected peers: '.count($status['peers']));
            }
        } else {
            $this->warn('No SSH configuration - cannot check live status');
        }

        $stats = $service->getServerStats($server);
        $this->line("Total peers: {$stats['total_peers']}");
        $this->line("Active peers: {$stats['active_peers']}");
        $this->line("Connected peers: {$stats['connected_peers']}");
    }
}
