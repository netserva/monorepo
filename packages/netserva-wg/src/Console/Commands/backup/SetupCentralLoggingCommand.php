<?php

namespace NetServa\Wg\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Wg\Models\WireguardHub;
use NetServa\Wg\Services\HubTypes\LoggingHubService;
use NetServa\Wg\Services\LoggingIntegrationService;

class SetupCentralLoggingCommand extends Command
{
    protected $signature = 'wireguard:setup-logging
                          --create-hub : Create a new logging hub if none exists
                          --repair : Repair existing log forwarding configurations
                          --status : Show current logging status
                          --hub= : Specific hub to configure (ID or name)
                          --all : Configure all source hubs';

    protected $description = 'Setup and manage central WireGuard logging infrastructure';

    public function handle(
        LoggingIntegrationService $loggingService,
        LoggingHubService $loggingHubService
    ): int {
        $this->info('🔍 WireGuard Central Logging Management');

        if ($this->option('status')) {
            return $this->showLoggingStatus($loggingService);
        }

        // Check for existing logging hub
        $loggingHub = $this->getLoggingHub();

        if (! $loggingHub) {
            if ($this->option('create-hub')) {
                $loggingHub = $this->createLoggingHub();
                if (! $loggingHub) {
                    return 1;
                }
            } else {
                $this->error('❌ No logging hub found. Use --create-hub to create one or create manually first.');

                return 1;
            }
        }

        $this->info("📋 Using logging hub: $loggingHub->name");

        if ($this->option('repair')) {
            return $this->repairLogForwarding($loggingService);
        }

        // Setup global log forwarding
        return $this->setupGlobalLogging($loggingService, $loggingHubService, $loggingHub);
    }

    private function getLoggingHub(): ?WireguardHub
    {
        return WireguardHub::where('hub_type', 'logging')
            ->where('status', 'active')
            ->first();
    }

    private function createLoggingHub(): ?WireguardHub
    {
        $this->info('🏗️ Creating new logging hub...');

        $name = $this->ask('📝 Logging hub name', 'central-logging');
        $description = $this->ask('📝 Description', 'Central WireGuard logging hub');
        $networkCidr = $this->ask('🌐 Network CIDR', '10.200.0.0/24');
        $listenPort = $this->ask('🔌 Listen port', '51800');

        // Select SSH host
        $sshHosts = \NetServa\Core\Models\SshHost::all();
        if ($sshHosts->isEmpty()) {
            $this->error('❌ No SSH hosts configured. Please configure SSH hosts first.');

            return null;
        }

        $choices = $sshHosts->mapWithKeys(fn ($host) => [
            $host->id => "$host->hostname ($host->host)",
        ])->toArray();

        $sshHostId = $this->choice('🖥️ Select SSH host for logging hub', $choices);

        try {
            // Generate keys
            $keyPair = WireguardHub::generateKeyPair();

            $hub = WireguardHub::create([
                'name' => $name,
                'description' => $description,
                'hub_type' => 'logging',
                'network_cidr' => $networkCidr,
                'hub_ip' => $this->getFirstIpFromCidr($networkCidr),
                'network_prefix' => explode('/', $networkCidr)[1],
                'listen_port' => $listenPort,
                'interface_name' => 'wg-logging',
                'public_key' => $keyPair['public'],
                'private_key_encrypted' => encrypt($keyPair['private']),
                'ssh_host_id' => $sshHostId,
                'status' => 'active',
                'deployment_status' => 'pending',
                'health_status' => 'pending',
                'log_forwarding' => true,
                'monitoring_enabled' => true,
                'audit_logging' => true,
            ]);

            $this->info("✅ Created logging hub: $hub->name");

            return $hub;

        } catch (\Exception $e) {
            $this->error("❌ Failed to create logging hub: $e->getMessage()");

            return null;
        }
    }

    private function getFirstIpFromCidr(string $cidr): string
    {
        [$network, $prefix] = explode('/', $cidr);
        $ip = ip2long($network);

        return long2ip($ip + 1); // First usable IP
    }

    private function showLoggingStatus(LoggingIntegrationService $loggingService): int
    {
        $this->info('📊 WireGuard Central Logging Status');

        $status = $loggingService->getLogForwardingStatus();

        // Logging hub status
        if ($status['logging_hub']) {
            $hub = $status['logging_hub'];
            $this->info('🏢 Logging Hub: '.$hub['name'].' ('.$hub['status'].')');
            $this->info('📍 Endpoint: '.$hub['endpoint']);
        } else {
            $this->warn('⚠️ No logging hub configured');
        }

        // Source hubs status
        if (! empty($status['source_hubs'])) {
            $this->info('📤 Source Hubs Status:');

            $tableData = [];
            foreach ($status['source_hubs'] as $hubStatus) {
                $configStatus = $hubStatus['forwarding_configured'] ? '✅' : '❌';
                $rsyslogStatus = $hubStatus['rsyslog_active'] ? '✅' : '❌';
                $recentLogs = $hubStatus['recent_log_count'] ?? 0;
                $errors = ! empty($hubStatus['errors']) ? '⚠️ '.implode(', ', $hubStatus['errors']) : '';

                $tableData[] = [
                    $hubStatus['hub_name'],
                    $hubStatus['hub_type'],
                    $configStatus,
                    $rsyslogStatus,
                    $recentLogs,
                    $errors,
                ];
            }

            $this->table(
                ['Hub Name', 'Type', 'Config', 'Rsyslog', 'Recent Logs', 'Errors'],
                $tableData
            );
        } else {
            $this->info('ℹ️ No source hubs found');
        }

        return 0;
    }

    private function repairLogForwarding(LoggingIntegrationService $loggingService): int
    {
        $this->info('🔧 Repairing log forwarding configurations...');

        $sourceHubs = WireguardHub::where('hub_type', '!=', 'logging')
            ->where('status', 'active')
            ->get();

        if ($sourceHubs->isEmpty()) {
            $this->info('ℹ️ No source hubs found to repair');

            return 0;
        }

        $repaired = 0;
        $failed = 0;

        foreach ($sourceHubs as $hub) {
            $this->line('🔧 Repairing '.$hub->name.'...');

            if ($loggingService->repairLogForwarding($hub)) {
                $this->info('  ✅ '.$hub->name.' repaired successfully');
                $repaired++;
            } else {
                $this->error('  ❌ Failed to repair '.$hub->name);
                $failed++;
            }
        }

        $this->info('📊 Repair Summary: '.$repaired.' repaired, '.$failed.' failed');

        return $failed > 0 ? 1 : 0;
    }

    private function setupGlobalLogging(
        LoggingIntegrationService $loggingService,
        LoggingHubService $loggingHubService,
        WireguardHub $loggingHub
    ): int {
        $this->info('🚀 Setting up global WireGuard logging...');

        // Configure logging hub
        $this->line('🔧 Configuring logging hub...');
        if (! $loggingHubService->configureAsLoggingHub($loggingHub)) {
            $this->error('❌ Failed to configure logging hub');

            return 1;
        }
        $this->info('✅ Logging hub configured');

        // Setup global log forwarding
        $this->line('📤 Setting up log forwarding from source hubs...');
        if (! $loggingService->setupGlobalLogForwarding()) {
            $this->error('❌ Failed to setup global log forwarding');

            return 1;
        }
        $this->info('✅ Global log forwarding configured');

        // Verification
        $this->line('🔍 Verifying logging setup...');
        $this->runVerification($loggingService);

        $this->info('🎉 Central logging setup complete!');
        $this->line('');
        $this->info('💡 Next steps:');
        $this->info('• Run `php artisan wireguard:setup-logging --status` to check status');
        $this->info('• Monitor logs in the logging hub at /var/log/wireguard-central/');
        $this->info('• Check analytics integration at /var/log/wireguard-central/analytics/');
        $this->info('• Review security alerts at /var/log/wireguard-central/alerts/');

        return 0;
    }

    private function runVerification(LoggingIntegrationService $loggingService): void
    {
        $status = $loggingService->getLogForwardingStatus();

        $configuredHubs = 0;
        $totalHubs = count($status['source_hubs']);

        foreach ($status['source_hubs'] as $hubStatus) {
            if ($hubStatus['forwarding_configured'] && $hubStatus['rsyslog_active']) {
                $configuredHubs++;
            }
        }

        if ($configuredHubs === $totalHubs && $totalHubs > 0) {
            $this->info('✅ All '.$totalHubs.' source hubs are properly configured');
        } elseif ($configuredHubs > 0) {
            $this->warn('⚠️ '.$configuredHubs.'/'.$totalHubs.' source hubs are configured');
        } else {
            $this->error('❌ No source hubs are properly configured');
        }

        if ($status['logging_hub']) {
            $this->info('✅ Logging hub is active: '.$status['logging_hub']['name']);
        } else {
            $this->error('❌ Logging hub is not configured');
        }
    }
}
