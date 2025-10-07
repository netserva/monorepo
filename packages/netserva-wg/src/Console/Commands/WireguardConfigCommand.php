<?php

namespace NetServa\Wg\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Wg\Models\WireguardPeer;
use NetServa\Wg\Models\WireguardServer;
use NetServa\Wg\Services\WireguardService;

class WireguardConfigCommand extends Command
{
    protected $signature = 'wireguard:config {type} {name}
                            {--output= : Output file path}';

    protected $description = 'Generate WireGuard configuration (server or peer)';

    public function handle(WireguardService $service): int
    {
        $type = $this->argument('type');
        $name = $this->argument('name');
        $outputPath = $this->option('output');

        if (! in_array($type, ['server', 'peer'])) {
            $this->error("Type must be 'server' or 'peer'");

            return 1;
        }

        try {
            if ($type === 'server') {
                $config = $this->generateServerConfig($service, $name);
            } else {
                $config = $this->generatePeerConfig($service, $name);
            }

            if ($outputPath) {
                file_put_contents($outputPath, $config);
                $this->info("Configuration written to: {$outputPath}");
            } else {
                $this->line($config);
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('Failed to generate config: '.$e->getMessage());

            return 1;
        }
    }

    private function generateServerConfig(WireguardService $service, string $name): string
    {
        $server = WireguardServer::where('name', $name)->first();

        if (! $server) {
            throw new \Exception("Server '{$name}' not found");
        }

        return $service->generateServerConfig($server);
    }

    private function generatePeerConfig(WireguardService $service, string $name): string
    {
        $peer = WireguardPeer::where('name', $name)->first();

        if (! $peer) {
            throw new \Exception("Peer '{$name}' not found");
        }

        return $service->generatePeerConfig($peer);
    }
}
