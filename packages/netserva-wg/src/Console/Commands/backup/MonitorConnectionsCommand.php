<?php

namespace NetServa\Wg\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use NetServa\Core\Services\SshConnectionService;
use NetServa\Wg\Models\WireguardConnection;
use NetServa\Wg\Models\WireguardHub;
use NetServa\Wg\Services\DeploymentService;

class MonitorConnectionsCommand extends Command
{
    protected $signature = 'wireguard:monitor
                          --hub= : Monitor specific hub (ID or name)
                          --interval=30 : Monitoring interval in seconds
                          --duration=0 : Duration to monitor in minutes (0 = indefinite)
                          --log-connections : Log connection events to database
                          --alert-threshold=300 : Alert if no handshake for X seconds
                          --export= : Export monitoring data to file';

    protected $description = 'Monitor WireGuard connections and log activity';

    private int $startTime;

    private array $monitoringData = [];

    public function handle(
        DeploymentService $deploymentService,
        SshConnectionService $sshService
    ): int {
        $this->startTime = time();
        $this->info('🔍 WireGuard Connection Monitor');

        $hubs = $this->getHubsToMonitor();

        if ($hubs->isEmpty()) {
            $this->error('❌ No hubs found for monitoring');

            return 1;
        }

        $interval = (int) $this->option('interval');
        $duration = (int) $this->option('duration');
        $endTime = $duration > 0 ? $this->startTime + ($duration * 60) : 0;

        $this->info("📊 Monitoring $hubs->count() hub(s)");
        $this->info("⏱️ Interval: $interval seconds".($duration > 0 ? ", Duration: $duration minutes" : ', Duration: indefinite'));

        $this->line('');
        $this->info('Press Ctrl+C to stop monitoring');
        $this->line('');

        try {
            while (true) {
                $this->monitorIteration($hubs, $deploymentService, $sshService);

                if ($endTime > 0 && time() >= $endTime) {
                    $this->info('⏰ Monitoring duration completed');
                    break;
                }

                $this->line(''); // Separator between iterations
                sleep($interval);
            }
        } catch (\Exception $e) {
            $this->error("❌ Monitoring error: $e->getMessage()");

            return 1;
        }

        // Export data if requested
        if ($exportFile = $this->option('export')) {
            $this->exportMonitoringData($exportFile);
        }

        $this->info('✅ Monitoring completed');

        return 0;
    }

    private function getHubsToMonitor()
    {
        if ($hubIdentifier = $this->option('hub')) {
            $hub = is_numeric($hubIdentifier)
                ? WireguardHub::find($hubIdentifier)
                : WireguardHub::where('name', $hubIdentifier)->first();

            if (! $hub) {
                $this->error("❌ Hub '$hubIdentifier' not found");

                return collect();
            }

            return collect([$hub]);
        }

        return WireguardHub::where('status', 'active')
            ->where('deployment_status', 'deployed')
            ->get();
    }

    private function monitorIteration($hubs, DeploymentService $deploymentService, SshConnectionService $sshService): void
    {
        $timestamp = now();
        $alertThreshold = (int) $this->option('alert-threshold');

        $this->info("🕐 $timestamp->format('Y-m-d H:i:s') - Monitoring Iteration");

        foreach ($hubs as $hub) {
            $this->monitorHub($hub, $deploymentService, $sshService, $timestamp, $alertThreshold);
        }
    }

    private function monitorHub(WireguardHub $hub, DeploymentService $deploymentService, SshConnectionService $sshService, $timestamp, int $alertThreshold): void
    {
        try {
            $this->line("🔍 $hub->name ($hub->hub_type):");

            // Get deployment status
            $status = $deploymentService->getDeploymentStatus($hub);

            // Display basic status
            $statusIcon = $status['interface_status'] === 'up' && $status['service_status'] === 'active' ? '✅' : '❌';
            $this->line('  '.$statusIcon.' Interface: '.$status['interface_status'].', Service: '.$status['service_status'].', Peers: '.$status['peer_count']);

            if (! empty($status['errors'])) {
                foreach ($status['errors'] as $error) {
                    $this->line('  ⚠️ '.$error);
                }

                return;
            }

            // Get detailed peer information
            $peerInfo = $this->getPeerInformation($hub, $sshService);

            // Display peer status
            foreach ($peerInfo as $peer) {
                $handshakeAge = $peer['handshake_age'];
                $handshakeIcon = $handshakeAge <= $alertThreshold ? '🟢' : '🔴';

                $this->line('  '.$handshakeIcon.' Peer: '.$peer['peer_name'].' - Last handshake: '.$peer['handshake_time'].' ('.$handshakeAges.' ago)');
                $this->line('    📊 RX: '.$peer['rx_formatted'].', TX: '.$peer['tx_formatted']);

                // Alert for stale connections
                if ($handshakeAge > $alertThreshold) {
                    $this->warn('  ⚠️ ALERT: Peer '.$peer['peer_name'].' last handshake '.$handshakeAge.' seconds ago (threshold: '.$alertThreshold.')');
                }

                // Log to database if enabled
                if ($this->option('log-connections')) {
                    $this->logConnectionToDatabase($hub, $peer, $timestamp);
                }

                // Store monitoring data
                $this->storeMonitoringData($hub, $peer, $timestamp);
            }

        } catch (\Exception $e) {
            $this->error("  ❌ Error monitoring $hub->name: $e->getMessage()");
        }
    }

    private function getPeerInformation(WireguardHub $hub, SshConnectionService $sshService): array
    {
        $connection = $sshService->getConnection($hub->sshHost->host);

        // Get detailed WireGuard dump
        $wgDump = $sshService->executeCommand($connection, "wg show $hub->interface_name dump");

        $peers = [];
        $lines = explode("\n", trim($wgDump));

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $parts = explode("\t", $line);
            if (count($parts) < 7) {
                continue;
            }

            $publicKey = $parts[1];
            $endpoint = $parts[3];
            $latestHandshake = (int) $parts[4];
            $rxBytes = (int) $parts[5];
            $txBytes = (int) $parts[6];

            // Find spoke by public key
            $spoke = $hub->spokes()->where('public_key', $publicKey)->first();
            $peerName = $spoke ? $spoke->name : 'Unknown ('.substr($publicKey, 0, 8).'...)';

            $handshakeAge = $latestHandshake > 0 ? time() - $latestHandshake : 999999;
            $handshakeTime = $latestHandshake > 0 ? date('H:i:s', $latestHandshake) : 'Never';

            $peers[] = [
                'public_key' => $publicKey,
                'peer_name' => $peerName,
                'endpoint' => $endpoint,
                'handshake_timestamp' => $latestHandshake,
                'handshake_time' => $handshakeTime,
                'handshake_age' => $handshakeAge,
                'rx_bytes' => $rxBytes,
                'tx_bytes' => $txBytes,
                'rx_formatted' => $this->formatBytes($rxBytes),
                'tx_formatted' => $this->formatBytes($txBytes),
                'spoke_id' => $spoke?->id,
            ];
        }

        return $peers;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    private function logConnectionToDatabase(WireguardHub $hub, array $peer, $timestamp): void
    {
        try {
            WireguardConnection::updateOrCreate(
                [
                    'wireguard_hub_id' => $hub->id,
                    'wireguard_spoke_id' => $peer['spoke_id'],
                    'peer_public_key' => $peer['public_key'],
                ],
                [
                    'endpoint' => $peer['endpoint'],
                    'last_handshake' => $peer['handshake_timestamp'] > 0 ?
                        \Carbon\Carbon::createFromTimestamp($peer['handshake_timestamp']) : null,
                    'bytes_received' => $peer['rx_bytes'],
                    'bytes_sent' => $peer['tx_bytes'],
                    'connection_status' => $peer['handshake_age'] <= 300 ? 'connected' : 'stale',
                    'last_seen' => $timestamp,
                    'session_duration' => null, // Would need to calculate from session start
                ]
            );
        } catch (\Exception $e) {
            $this->warn("⚠️ Failed to log connection to database: $e->getMessage()");
        }
    }

    private function storeMonitoringData(WireguardHub $hub, array $peer, $timestamp): void
    {
        $this->monitoringData[] = [
            'timestamp' => $timestamp->toISOString(),
            'hub_id' => $hub->id,
            'hub_name' => $hub->name,
            'hub_type' => $hub->hub_type,
            'peer_name' => $peer['peer_name'],
            'peer_public_key' => $peer['public_key'],
            'endpoint' => $peer['endpoint'],
            'handshake_age' => $peer['handshake_age'],
            'rx_bytes' => $peer['rx_bytes'],
            'tx_bytes' => $peer['tx_bytes'],
            'connection_status' => $peer['handshake_age'] <= 300 ? 'connected' : 'stale',
        ];
    }

    private function exportMonitoringData(string $filename): void
    {
        try {
            $data = [
                'monitoring_session' => [
                    'start_time' => date('Y-m-d H:i:s', $this->startTime),
                    'end_time' => now()->format('Y-m-d H:i:s'),
                    'duration_seconds' => time() - $this->startTime,
                    'total_records' => count($this->monitoringData),
                ],
                'data' => $this->monitoringData,
            ];

            $jsonData = json_encode($data, JSON_PRETTY_PRINT);

            if (str_ends_with($filename, '.json')) {
                file_put_contents($filename, $jsonData);
            } else {
                // Export as CSV
                $csvFile = fopen($filename, 'w');

                if (! empty($this->monitoringData)) {
                    // Write headers
                    fputcsv($csvFile, array_keys($this->monitoringData[0]));

                    // Write data
                    foreach ($this->monitoringData as $row) {
                        fputcsv($csvFile, $row);
                    }
                }

                fclose($csvFile);
            }

            $this->info("📄 Monitoring data exported to: $filename");

        } catch (\Exception $e) {
            $this->error("❌ Failed to export monitoring data: $e->getMessage()");
        }
    }
}
