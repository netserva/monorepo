<?php

namespace NetServa\Ops\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Models\SshHost;
use NetServa\Core\Services\SshConnectionService;
use NetServa\Ops\Services\SystemStatusService;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class SystemStatusCommand extends Command
{
    protected $signature = 'monitor:status
                           {host? : Host to monitor (default: localhost)}
                           {--format=text : Output format (text|json|html)}
                           {--services : Show only services status}
                           {--metrics : Show only system metrics}
                           {--detailed : Show detailed information}
                           {--watch : Continuous monitoring mode}
                           {--interval=30 : Refresh interval in seconds for watch mode}';

    protected $description = 'Display comprehensive system status and monitoring information';

    public function __construct(
        protected SystemStatusService $statusService,
        protected SshConnectionService $sshService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $this->info('🖥️  System Status Monitor');
            $this->newLine();

            // Get hostname
            $host = $this->argument('host') ?: $this->getHostname();

            // Get monitoring mode
            $watchMode = $this->option('watch');
            $interval = (int) $this->option('interval');

            if ($watchMode) {
                return $this->runWatchMode($host, $interval);
            }

            return $this->runSingleCheck($host);

        } catch (\Exception $e) {
            $this->error("❌ System status check failed: {$e->getMessage()}");

            return 1;
        }
    }

    protected function getHostname(): string
    {
        $availableHosts = SshHost::where('is_active', true)
            ->get()
            ->pluck('host', 'host')
            ->toArray();

        if (empty($availableHosts)) {
            return 'localhost';
        }

        $hostOptions = array_merge(['localhost' => 'localhost (current system)'], $availableHosts);

        return select(
            label: 'Select host to monitor',
            options: $hostOptions,
            default: 'localhost'
        );
    }

    protected function runSingleCheck(string $host): int
    {
        $this->info("📊 Gathering system status for {$host}...");

        // Get status
        $status = $this->statusService->getSystemStatus($host);

        // Display results based on options
        $this->displayStatus($status);

        return 0;
    }

    protected function runWatchMode(string $host, int $interval): int
    {
        $this->info("👁️  Starting continuous monitoring for {$host}");
        $this->info("⏱️  Refresh interval: {$interval} seconds");
        $this->info('⌨️  Press Ctrl+C to stop');
        $this->newLine();

        $iteration = 0;
        while (true) {
            $iteration++;

            // Clear screen (simple version)
            if ($iteration > 1) {
                $this->output->write("\033[2J\033[H");
            }

            $this->line("🕐 Update #{$iteration} - ".now()->format('Y-m-d H:i:s'));
            $this->line(str_repeat('=', 60));

            try {
                $status = $this->statusService->getSystemStatus($host);
                $this->displayStatus($status);

                $this->newLine();
                $this->line("Next update in {$interval} seconds... (Ctrl+C to stop)");

                sleep($interval);
            } catch (\Exception $e) {
                $this->error("❌ Status check failed: {$e->getMessage()}");
                sleep($interval);
            }
        }
    }

    protected function displayStatus(array $status): void
    {
        $format = $this->option('format');
        $servicesOnly = $this->option('services');
        $metricsOnly = $this->option('metrics');
        $detailed = $this->option('detailed');

        if ($format === 'json') {
            $this->line($this->statusService->generateReport($status, 'json'));

            return;
        }

        if ($format === 'html') {
            $this->info('HTML report generated. Use --save option to save to file.');
            $this->line($this->statusService->generateReport($status, 'html'));

            return;
        }

        // Text format display
        $this->displayTextStatus($status, $servicesOnly, $metricsOnly, $detailed);
    }

    protected function displayTextStatus(array $status, bool $servicesOnly, bool $metricsOnly, bool $detailed): void
    {
        $this->newLine();
        $this->info("📋 System Status Report for {$status['host']}");
        $this->line(str_repeat('=', 60));
        $this->line("Generated: {$status['timestamp']->format('Y-m-d H:i:s')}");
        $this->newLine();

        // Services Status
        if (! $metricsOnly) {
            $this->displayServicesStatus($status['services'], $detailed);
        }

        // System Metrics
        if (! $servicesOnly) {
            $this->displaySystemMetrics($status['metrics'], $detailed);
        }

        // Additional sections only in detailed mode
        if ($detailed && ! $servicesOnly && ! $metricsOnly) {
            $this->displayDnsStatus($status['dns']);
            $this->displayContainerStatus($status['containers']);
            $this->displayStorageStatus($status['storage']);
            $this->displayNetworkStatus($status['network']);
        }
    }

    protected function displayServicesStatus(array $services, bool $detailed): void
    {
        $this->info('🔧 Services Status');
        $this->line(str_repeat('-', 30));

        $runningCount = 0;
        $totalCount = count($services);

        foreach ($services as $service) {
            $icon = $service['active'] ? '✅' : '❌';
            $status = $service['status'];

            if ($detailed && $service['active'] && ! empty($service['info'])) {
                $memory = isset($service['info']['memory_human']) ? " ({$service['info']['memory_human']})" : '';
                $pid = isset($service['info']['pid']) ? " PID: {$service['info']['pid']}" : '';
                $this->line("{$icon} {$service['name']}: {$status}{$memory}{$pid}");
            } else {
                $this->line("{$icon} {$service['name']}: {$status}");
            }

            if ($service['active']) {
                $runningCount++;
            }
        }

        $this->newLine();
        $this->info("📊 Services Summary: {$runningCount}/{$totalCount} running");
        $this->newLine();
    }

    protected function displaySystemMetrics(array $metrics, bool $detailed): void
    {
        $this->info('📈 System Metrics');
        $this->line(str_repeat('-', 30));

        // CPU Usage
        if (isset($metrics['cpu'])) {
            $cpu = $metrics['cpu'];
            $cpuIcon = match ($cpu['status']) {
                'high' => '🔥',
                'medium' => '⚡',
                default => '💚'
            };
            $this->line("{$cpuIcon} CPU Usage: {$cpu['usage_percent']}%");
        }

        // Memory Usage
        if (isset($metrics['memory'])) {
            $memory = $metrics['memory'];
            $memIcon = match ($memory['status']) {
                'high' => '🔥',
                'medium' => '⚡',
                default => '💚'
            };
            $this->line("{$memIcon} Memory: {$memory['used_human']}/{$memory['total_human']} ({$memory['usage_percent']}%)");
        }

        // Load Average
        if (isset($metrics['load'])) {
            $load = $metrics['load'];
            $this->line("⚖️  Load Average: {$load['1min']}, {$load['5min']}, {$load['15min']}");
        }

        // System Uptime
        if (isset($metrics['uptime'])) {
            $uptime = $metrics['uptime'];
            $this->line("⏰ Uptime: {$uptime['uptime']} (since {$uptime['boot_time']})");
        }

        // Process Count
        if (isset($metrics['processes'])) {
            $processes = $metrics['processes'];
            $processIcon = match ($processes['status']) {
                'high' => '🔥',
                'medium' => '⚡',
                default => '💚'
            };
            $this->line("{$processIcon} Processes: {$processes['total']}");
        }

        // Disk Usage
        if (isset($metrics['disk']) && is_array($metrics['disk'])) {
            $this->line('💾 Disk Usage:');
            foreach ($metrics['disk'] as $disk) {
                $diskIcon = match ($disk['status']) {
                    'high' => '🔥',
                    'medium' => '⚡',
                    default => '💚'
                };
                $mountPoint = $detailed ? $disk['mount_point'] : basename($disk['mount_point']);
                $this->line("   {$diskIcon} {$mountPoint}: {$disk['used']}/{$disk['size']} ({$disk['usage_percent']}%)");
            }
        }

        $this->newLine();
    }

    protected function displayDnsStatus(array $dns): void
    {
        $this->info('🌐 DNS Status');
        $this->line(str_repeat('-', 30));

        if (isset($dns['resolution'])) {
            foreach ($dns['resolution'] as $domain => $result) {
                $icon = $result['resolved'] ? '✅' : '❌';
                $this->line("{$icon} {$domain}: ".($result['resolved'] ? 'OK' : 'Failed'));
            }
        }

        if (isset($dns['dnssec'])) {
            $icon = $dns['dnssec']['enabled'] ? '✅' : '❌';
            $this->line("{$icon} DNSSEC: ".($dns['dnssec']['enabled'] ? 'Enabled' : 'Disabled'));
        }

        if (isset($dns['authoritative'])) {
            $icon = $dns['authoritative']['serving'] ? '✅' : '❌';
            $this->line("{$icon} Authoritative DNS: ".($dns['authoritative']['serving'] ? 'Active' : 'Inactive'));
        }

        $this->newLine();
    }

    protected function displayContainerStatus(array $containers): void
    {
        if (empty($containers)) {
            return;
        }

        $this->info('📦 Container Status');
        $this->line(str_repeat('-', 30));

        if (isset($containers['incus'])) {
            $this->line('Incus Containers:');
            foreach ($containers['incus'] as $container) {
                $icon = $container['status'] === 'Running' ? '✅' : '❌';
                $ip = $container['ipv4'] ? " ({$container['ipv4']})" : '';
                $this->line("   {$icon} {$container['name']}: {$container['status']}{$ip}");
            }
        }

        if (isset($containers['docker'])) {
            $this->line('Docker Containers:');
            foreach ($containers['docker'] as $container) {
                $icon = str_contains($container['status'], 'Up') ? '✅' : '❌';
                $this->line("   {$icon} {$container['name']}: {$container['status']}");
            }
        }

        $this->newLine();
    }

    protected function displayStorageStatus(array $storage): void
    {
        $this->info('💾 Storage Status');
        $this->line(str_repeat('-', 30));

        if (isset($storage['raid']['status']) && $storage['raid']['status'] === 'configured') {
            $this->line('✅ RAID: Configured');
        }

        if (isset($storage['zfs']['status']) && $storage['zfs']['status'] === 'configured') {
            $this->line('✅ ZFS: Configured');
        }

        if (isset($storage['mounts'])) {
            $this->line('Mount Points: '.count($storage['mounts']));
        }

        $this->newLine();
    }

    protected function displayNetworkStatus(array $network): void
    {
        $this->info('🌐 Network Status');
        $this->line(str_repeat('-', 30));

        if (isset($network['interfaces'])) {
            $activeInterfaces = array_filter($network['interfaces'], fn ($int) => $int['state'] === 'UP');
            $this->line('Active Interfaces: '.count($activeInterfaces));
        }

        if (isset($network['connections'])) {
            $this->line("Listening Ports: {$network['connections']['listening_ports']}");
        }

        if (isset($network['firewall'])) {
            $fw = $network['firewall'];
            $icon = $fw['status'] === 'active' ? '✅' : '❌';
            $this->line("{$icon} Firewall ({$fw['type']}): {$fw['status']}");
        }

        $this->newLine();
    }
}
