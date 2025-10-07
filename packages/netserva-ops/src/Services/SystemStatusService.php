<?php

namespace NetServa\Ops\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use NetServa\Core\Services\SshConnectionService;

class SystemStatusService
{
    protected array $coreServices = [
        'nginx',
        'postfix',
        'dovecot',
        'mariadb',
        'mysql',
        'php8.4-fpm',
        'php-fpm',
        'powerdns',
        'pdns',
    ];

    protected array $systemMetrics = [
        'cpu_usage',
        'memory_usage',
        'disk_usage',
        'load_average',
        'uptime',
        'processes',
    ];

    public function __construct(
        protected SshConnectionService $sshService
    ) {}

    public function getSystemStatus(string $host = 'localhost'): array
    {
        $cacheKey = "system_status:{$host}";

        return Cache::remember($cacheKey, 60, function () use ($host) {
            return [
                'host' => $host,
                'timestamp' => Carbon::now(),
                'services' => $this->getServicesStatus($host),
                'metrics' => $this->getSystemMetrics($host),
                'dns' => $this->getDnsStatus($host),
                'containers' => $this->getContainerStatus($host),
                'storage' => $this->getStorageStatus($host),
                'network' => $this->getNetworkStatus($host),
            ];
        });
    }

    public function getServicesStatus(string $host = 'localhost'): array
    {
        $services = [];

        foreach ($this->coreServices as $service) {
            $services[$service] = $this->checkServiceStatus($service, $host);
        }

        // Auto-detect additional services
        $additionalServices = $this->detectAdditionalServices($host);
        foreach ($additionalServices as $service) {
            if (! isset($services[$service])) {
                $services[$service] = $this->checkServiceStatus($service, $host);
            }
        }

        return $services;
    }

    public function getSystemMetrics(string $host = 'localhost'): array
    {
        $metrics = [];

        try {
            // CPU Usage
            $metrics['cpu'] = $this->getCpuUsage($host);

            // Memory Usage
            $metrics['memory'] = $this->getMemoryUsage($host);

            // Disk Usage
            $metrics['disk'] = $this->getDiskUsage($host);

            // Load Average
            $metrics['load'] = $this->getLoadAverage($host);

            // System Uptime
            $metrics['uptime'] = $this->getSystemUptime($host);

            // Process Count
            $metrics['processes'] = $this->getProcessCount($host);

        } catch (\Exception $e) {
            Log::error("Failed to get system metrics for {$host}: ".$e->getMessage());
            $metrics['error'] = $e->getMessage();
        }

        return $metrics;
    }

    public function getDnsStatus(string $host = 'localhost'): array
    {
        try {
            $dnsStatus = [];

            // Check DNS resolution
            $dnsStatus['resolution'] = $this->checkDnsResolution($host);

            // Check DNSSEC if applicable
            $dnsStatus['dnssec'] = $this->checkDnssecStatus($host);

            // Check authoritative servers
            $dnsStatus['authoritative'] = $this->checkAuthoritativeDns($host);

            return $dnsStatus;
        } catch (\Exception $e) {
            Log::error("Failed to get DNS status for {$host}: ".$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    public function getContainerStatus(string $host = 'localhost'): array
    {
        try {
            $containers = [];

            // Get Incus containers if available
            $incusContainers = $this->getIncusContainers($host);
            if (! empty($incusContainers)) {
                $containers['incus'] = $incusContainers;
            }

            // Check for Docker containers (if present)
            $dockerContainers = $this->getDockerContainers($host);
            if (! empty($dockerContainers)) {
                $containers['docker'] = $dockerContainers;
            }

            return $containers;
        } catch (\Exception $e) {
            Log::error("Failed to get container status for {$host}: ".$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    public function getStorageStatus(string $host = 'localhost'): array
    {
        try {
            return [
                'filesystems' => $this->getFilesystemUsage($host),
                'mounts' => $this->getMountPoints($host),
                'raid' => $this->getRaidStatus($host),
                'zfs' => $this->getZfsStatus($host),
            ];
        } catch (\Exception $e) {
            Log::error("Failed to get storage status for {$host}: ".$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    public function getNetworkStatus(string $host = 'localhost'): array
    {
        try {
            return [
                'interfaces' => $this->getNetworkInterfaces($host),
                'connections' => $this->getActiveConnections($host),
                'firewall' => $this->getFirewallStatus($host),
            ];
        } catch (\Exception $e) {
            Log::error("Failed to get network status for {$host}: ".$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    protected function checkServiceStatus(string $service, string $host): array
    {
        $command = "systemctl is-active {$service} 2>/dev/null";

        if ($host === 'localhost') {
            $result = Process::run($command);
        } else {
            $sshResult = $this->sshService->exec($host, $command);
            $result = new class($sshResult['success'], $sshResult['output'], $sshResult['exit_code'])
            {
                public function __construct(private bool $success, private string $output, private int $exitCode) {}

                public function successful(): bool
                {
                    return $this->success;
                }

                public function output(): string
                {
                    return $this->output;
                }

                public function exitCode(): int
                {
                    return $this->exitCode;
                }
            };
        }

        $active = $result->successful() && trim($result->output()) === 'active';

        // Get additional service info if active
        $info = [];
        if ($active) {
            $info = $this->getServiceInfo($service, $host);
        }

        return [
            'name' => $service,
            'active' => $active,
            'status' => $active ? 'running' : 'stopped',
            'info' => $info,
        ];
    }

    protected function getServiceInfo(string $service, string $host): array
    {
        $info = [];

        // Get memory usage
        $command = "systemctl show {$service} --property=MemoryCurrent --value 2>/dev/null";
        if ($host === 'localhost') {
            $result = Process::run($command);
        } else {
            $sshResult = $this->sshService->exec($host, $command);
            $result = new class($sshResult['success'], $sshResult['output'], $sshResult['exit_code'])
            {
                public function __construct(private bool $success, private string $output, private int $exitCode) {}

                public function successful(): bool
                {
                    return $this->success;
                }

                public function output(): string
                {
                    return $this->output;
                }

                public function exitCode(): int
                {
                    return $this->exitCode;
                }
            };
        }

        if ($result->successful() && is_numeric(trim($result->output()))) {
            $info['memory_bytes'] = (int) trim($result->output());
            $info['memory_human'] = $this->formatBytes($info['memory_bytes']);
        }

        // Get process ID
        $command = "systemctl show {$service} --property=MainPID --value 2>/dev/null";
        if ($host === 'localhost') {
            $result = Process::run($command);
        } else {
            $sshResult = $this->sshService->exec($host, $command);
            $result = new class($sshResult['success'], $sshResult['output'], $sshResult['exit_code'])
            {
                public function __construct(private bool $success, private string $output, private int $exitCode) {}

                public function successful(): bool
                {
                    return $this->success;
                }

                public function output(): string
                {
                    return $this->output;
                }

                public function exitCode(): int
                {
                    return $this->exitCode;
                }
            };
        }

        if ($result->successful() && is_numeric(trim($result->output()))) {
            $info['pid'] = (int) trim($result->output());
        }

        return $info;
    }

    protected function detectAdditionalServices(string $host): array
    {
        $command = "systemctl list-units --type=service --state=active --no-pager --no-legend | awk '{print \$1}' | sed 's/\\.service\$//'";

        if ($host === 'localhost') {
            $result = Process::run($command);
        } else {
            $sshResult = $this->sshService->exec($host, $command);
            $result = new class($sshResult['success'], $sshResult['output'], $sshResult['exit_code'])
            {
                public function __construct(private bool $success, private string $output, private int $exitCode) {}

                public function successful(): bool
                {
                    return $this->success;
                }

                public function output(): string
                {
                    return $this->output;
                }

                public function exitCode(): int
                {
                    return $this->exitCode;
                }
            };
        }

        if (! $result->successful()) {
            return [];
        }

        $detectedServices = array_filter(explode("\n", trim($result->output())));

        // Filter relevant services (mail, web, database, etc.)
        $relevantPatterns = ['apache', 'nginx', 'httpd', 'postfix', 'sendmail', 'dovecot', 'redis', 'memcached'];

        return array_filter($detectedServices, function ($service) use ($relevantPatterns) {
            foreach ($relevantPatterns as $pattern) {
                if (str_contains($service, $pattern)) {
                    return true;
                }
            }

            return false;
        });
    }

    protected function getCpuUsage(string $host): array
    {
        $command = "top -bn1 | grep 'Cpu(s)' | sed 's/.*, *\\([0-9.]*\\)%* id.*/\\1/' | awk '{print 100 - \$1}'";

        if ($host === 'localhost') {
            $result = Process::run($command);
        } else {
            $sshResult = $this->sshService->exec($host, $command);
            $result = new class($sshResult['success'], $sshResult['output'], $sshResult['exit_code'])
            {
                public function __construct(private bool $success, private string $output, private int $exitCode) {}

                public function successful(): bool
                {
                    return $this->success;
                }

                public function output(): string
                {
                    return $this->output;
                }

                public function exitCode(): int
                {
                    return $this->exitCode;
                }
            };
        }

        $usage = $result->successful() ? (float) trim($result->output()) : 0;

        return [
            'usage_percent' => round($usage, 2),
            'status' => $usage > 80 ? 'high' : ($usage > 60 ? 'medium' : 'normal'),
        ];
    }

    protected function getMemoryUsage(string $host): array
    {
        $command = 'free -b | grep Mem';

        if ($host === 'localhost') {
            $result = Process::run($command);
        } else {
            $sshResult = $this->sshService->exec($host, $command);
            $result = new class($sshResult['success'], $sshResult['output'], $sshResult['exit_code'])
            {
                public function __construct(private bool $success, private string $output, private int $exitCode) {}

                public function successful(): bool
                {
                    return $this->success;
                }

                public function output(): string
                {
                    return $this->output;
                }

                public function exitCode(): int
                {
                    return $this->exitCode;
                }
            };
        }

        if (! $result->successful()) {
            return ['error' => 'Unable to get memory information'];
        }

        $fields = preg_split('/\s+/', trim($result->output()));
        $total = (int) $fields[1];
        $used = (int) $fields[2];
        $available = (int) $fields[6] ?? ($total - $used);

        return [
            'total_bytes' => $total,
            'used_bytes' => $used,
            'available_bytes' => $available,
            'usage_percent' => round(($used / $total) * 100, 2),
            'total_human' => $this->formatBytes($total),
            'used_human' => $this->formatBytes($used),
            'available_human' => $this->formatBytes($available),
            'status' => ($used / $total) > 0.8 ? 'high' : (($used / $total) > 0.6 ? 'medium' : 'normal'),
        ];
    }

    protected function getDiskUsage(string $host): array
    {
        $command = "df -h | grep -vE '^Filesystem|tmpfs|cdrom|/dev/loop'";

        if ($host === 'localhost') {
            $result = Process::run($command);
        } else {
            $sshResult = $this->sshService->exec($host, $command);
            $result = new class($sshResult['success'], $sshResult['output'], $sshResult['exit_code'])
            {
                public function __construct(private bool $success, private string $output, private int $exitCode) {}

                public function successful(): bool
                {
                    return $this->success;
                }

                public function output(): string
                {
                    return $this->output;
                }

                public function exitCode(): int
                {
                    return $this->exitCode;
                }
            };
        }

        if (! $result->successful()) {
            return ['error' => 'Unable to get disk information'];
        }

        $filesystems = [];
        $lines = explode("\n", trim($result->output()));

        foreach ($lines as $line) {
            $fields = preg_split('/\s+/', trim($line));
            if (count($fields) >= 6) {
                $usagePercent = (int) str_replace('%', '', $fields[4]);
                $filesystems[] = [
                    'filesystem' => $fields[0],
                    'size' => $fields[1],
                    'used' => $fields[2],
                    'available' => $fields[3],
                    'usage_percent' => $usagePercent,
                    'mount_point' => $fields[5],
                    'status' => $usagePercent > 80 ? 'high' : ($usagePercent > 60 ? 'medium' : 'normal'),
                ];
            }
        }

        return $filesystems;
    }

    protected function getLoadAverage(string $host): array
    {
        $command = "uptime | awk -F'load average:' '{ print \$2 }'";

        if ($host === 'localhost') {
            $result = Process::run($command);
        } else {
            $sshResult = $this->sshService->exec($host, $command);
            $result = new class($sshResult['success'], $sshResult['output'], $sshResult['exit_code'])
            {
                public function __construct(private bool $success, private string $output, private int $exitCode) {}

                public function successful(): bool
                {
                    return $this->success;
                }

                public function output(): string
                {
                    return $this->output;
                }

                public function exitCode(): int
                {
                    return $this->exitCode;
                }
            };
        }

        if (! $result->successful()) {
            return ['error' => 'Unable to get load average'];
        }

        $loads = array_map('trim', explode(',', trim($result->output())));

        return [
            '1min' => (float) ($loads[0] ?? 0),
            '5min' => (float) ($loads[1] ?? 0),
            '15min' => (float) ($loads[2] ?? 0),
        ];
    }

    protected function getSystemUptime(string $host): array
    {
        $command = "uptime -s && uptime | awk '{print \$3, \$4}' | sed 's/,//'";

        if ($host === 'localhost') {
            $result = Process::run($command);
        } else {
            $sshResult = $this->sshService->exec($host, $command);
            $result = new class($sshResult['success'], $sshResult['output'], $sshResult['exit_code'])
            {
                public function __construct(private bool $success, private string $output, private int $exitCode) {}

                public function successful(): bool
                {
                    return $this->success;
                }

                public function output(): string
                {
                    return $this->output;
                }

                public function exitCode(): int
                {
                    return $this->exitCode;
                }
            };
        }

        if (! $result->successful()) {
            return ['error' => 'Unable to get uptime information'];
        }

        $lines = explode("\n", trim($result->output()));

        return [
            'boot_time' => $lines[0] ?? 'unknown',
            'uptime' => $lines[1] ?? 'unknown',
        ];
    }

    protected function getProcessCount(string $host): array
    {
        $command = 'ps aux | wc -l';

        if ($host === 'localhost') {
            $result = Process::run($command);
        } else {
            $sshResult = $this->sshService->exec($host, $command);
            $result = new class($sshResult['success'], $sshResult['output'], $sshResult['exit_code'])
            {
                public function __construct(private bool $success, private string $output, private int $exitCode) {}

                public function successful(): bool
                {
                    return $this->success;
                }

                public function output(): string
                {
                    return $this->output;
                }

                public function exitCode(): int
                {
                    return $this->exitCode;
                }
            };
        }

        $count = $result->successful() ? (int) trim($result->output()) - 1 : 0; // Subtract header line

        return [
            'total' => $count,
            'status' => $count > 300 ? 'high' : ($count > 150 ? 'medium' : 'normal'),
        ];
    }

    protected function checkDnsResolution(string $host): array
    {
        $testDomains = ['google.com', 'cloudflare.com'];
        $results = [];

        foreach ($testDomains as $domain) {
            $command = "dig +short {$domain} A";

            if ($host === 'localhost') {
                $result = Process::run($command);
            } else {
                $sshResult = $this->sshService->exec($host, $command);
                $result = new class($sshResult['success'], $sshResult['output'], $sshResult['exit_code'])
                {
                    public function __construct(private bool $success, private string $output, private int $exitCode) {}

                    public function successful(): bool
                    {
                        return $this->success;
                    }

                    public function output(): string
                    {
                        return $this->output;
                    }

                    public function exitCode(): int
                    {
                        return $this->exitCode;
                    }
                };
            }

            $results[$domain] = [
                'resolved' => $result->successful() && ! empty(trim($result->output())),
                'response' => trim($result->output()),
            ];
        }

        return $results;
    }

    protected function checkDnssecStatus(string $host): array
    {
        $command = "dig +dnssec google.com | grep -E 'flags:.*ad|DNSSEC'";

        if ($host === 'localhost') {
            $result = Process::run($command);
        } else {
            $sshResult = $this->sshService->exec($host, $command);
            $result = new class($sshResult['success'], $sshResult['output'], $sshResult['exit_code'])
            {
                public function __construct(private bool $success, private string $output, private int $exitCode) {}

                public function successful(): bool
                {
                    return $this->success;
                }

                public function output(): string
                {
                    return $this->output;
                }

                public function exitCode(): int
                {
                    return $this->exitCode;
                }
            };
        }

        return [
            'enabled' => $result->successful() && ! empty(trim($result->output())),
            'details' => trim($result->output()),
        ];
    }

    protected function checkAuthoritativeDns(string $host): array
    {
        // This would check if this host is serving DNS authoritatively
        $command = 'ss -tulpn | grep :53';

        if ($host === 'localhost') {
            $result = Process::run($command);
        } else {
            $sshResult = $this->sshService->exec($host, $command);
            $result = new class($sshResult['success'], $sshResult['output'], $sshResult['exit_code'])
            {
                public function __construct(private bool $success, private string $output, private int $exitCode) {}

                public function successful(): bool
                {
                    return $this->success;
                }

                public function output(): string
                {
                    return $this->output;
                }

                public function exitCode(): int
                {
                    return $this->exitCode;
                }
            };
        }

        return [
            'serving' => $result->successful() && ! empty(trim($result->output())),
            'listeners' => trim($result->output()),
        ];
    }

    protected function getIncusContainers(string $host): array
    {
        $command = 'incus list --format json 2>/dev/null';

        if ($host === 'localhost') {
            $result = Process::run($command);
        } else {
            $sshResult = $this->sshService->exec($host, $command);
            $result = new class($sshResult['success'], $sshResult['output'], $sshResult['exit_code'])
            {
                public function __construct(private bool $success, private string $output, private int $exitCode) {}

                public function successful(): bool
                {
                    return $this->success;
                }

                public function output(): string
                {
                    return $this->output;
                }

                public function exitCode(): int
                {
                    return $this->exitCode;
                }
            };
        }

        if (! $result->successful()) {
            return [];
        }

        $containers = json_decode($result->output(), true) ?? [];

        return collect($containers)->map(function ($container) {
            return [
                'name' => $container['name'],
                'status' => $container['status'],
                'type' => $container['type'],
                'ipv4' => $container['state']['network']['eth0']['addresses'][0]['address'] ?? null,
            ];
        })->toArray();
    }

    protected function getDockerContainers(string $host): array
    {
        $command = 'docker ps --format json 2>/dev/null';

        if ($host === 'localhost') {
            $result = Process::run($command);
        } else {
            $sshResult = $this->sshService->exec($host, $command);
            $result = new class($sshResult['success'], $sshResult['output'], $sshResult['exit_code'])
            {
                public function __construct(private bool $success, private string $output, private int $exitCode) {}

                public function successful(): bool
                {
                    return $this->success;
                }

                public function output(): string
                {
                    return $this->output;
                }

                public function exitCode(): int
                {
                    return $this->exitCode;
                }
            };
        }

        if (! $result->successful()) {
            return [];
        }

        $lines = explode("\n", trim($result->output()));
        $containers = [];

        foreach ($lines as $line) {
            if (! empty($line)) {
                $container = json_decode($line, true);
                if ($container) {
                    $containers[] = [
                        'name' => $container['Names'],
                        'image' => $container['Image'],
                        'status' => $container['Status'],
                        'ports' => $container['Ports'] ?? '',
                    ];
                }
            }
        }

        return $containers;
    }

    protected function getFilesystemUsage(string $host): array
    {
        return $this->getDiskUsage($host);
    }

    protected function getMountPoints(string $host): array
    {
        $command = "mount | grep -E '^/dev|^tmpfs|^/srv'";

        if ($host === 'localhost') {
            $result = Process::run($command);
        } else {
            $sshResult = $this->sshService->exec($host, $command);
            $result = new class($sshResult['success'], $sshResult['output'], $sshResult['exit_code'])
            {
                public function __construct(private bool $success, private string $output, private int $exitCode) {}

                public function successful(): bool
                {
                    return $this->success;
                }

                public function output(): string
                {
                    return $this->output;
                }

                public function exitCode(): int
                {
                    return $this->exitCode;
                }
            };
        }

        if (! $result->successful()) {
            return [];
        }

        $mounts = [];
        $lines = explode("\n", trim($result->output()));

        foreach ($lines as $line) {
            if (preg_match('/^(\S+)\s+on\s+(\S+)\s+type\s+(\S+)\s+\((.+)\)/', $line, $matches)) {
                $mounts[] = [
                    'device' => $matches[1],
                    'mount_point' => $matches[2],
                    'filesystem_type' => $matches[3],
                    'options' => $matches[4],
                ];
            }
        }

        return $mounts;
    }

    protected function getRaidStatus(string $host): array
    {
        $command = 'cat /proc/mdstat 2>/dev/null';

        if ($host === 'localhost') {
            $result = Process::run($command);
        } else {
            $sshResult = $this->sshService->exec($host, $command);
            $result = new class($sshResult['success'], $sshResult['output'], $sshResult['exit_code'])
            {
                public function __construct(private bool $success, private string $output, private int $exitCode) {}

                public function successful(): bool
                {
                    return $this->success;
                }

                public function output(): string
                {
                    return $this->output;
                }

                public function exitCode(): int
                {
                    return $this->exitCode;
                }
            };
        }

        if (! $result->successful() || empty(trim($result->output()))) {
            return ['status' => 'not_configured'];
        }

        return [
            'status' => 'configured',
            'details' => trim($result->output()),
        ];
    }

    protected function getZfsStatus(string $host): array
    {
        $command = 'zpool status 2>/dev/null';

        if ($host === 'localhost') {
            $result = Process::run($command);
        } else {
            $sshResult = $this->sshService->exec($host, $command);
            $result = new class($sshResult['success'], $sshResult['output'], $sshResult['exit_code'])
            {
                public function __construct(private bool $success, private string $output, private int $exitCode) {}

                public function successful(): bool
                {
                    return $this->success;
                }

                public function output(): string
                {
                    return $this->output;
                }

                public function exitCode(): int
                {
                    return $this->exitCode;
                }
            };
        }

        if (! $result->successful() || empty(trim($result->output()))) {
            return ['status' => 'not_configured'];
        }

        return [
            'status' => 'configured',
            'pools' => trim($result->output()),
        ];
    }

    protected function getNetworkInterfaces(string $host): array
    {
        $command = 'ip -j addr show';

        if ($host === 'localhost') {
            $result = Process::run($command);
        } else {
            $sshResult = $this->sshService->exec($host, $command);
            $result = new class($sshResult['success'], $sshResult['output'], $sshResult['exit_code'])
            {
                public function __construct(private bool $success, private string $output, private int $exitCode) {}

                public function successful(): bool
                {
                    return $this->success;
                }

                public function output(): string
                {
                    return $this->output;
                }

                public function exitCode(): int
                {
                    return $this->exitCode;
                }
            };
        }

        if (! $result->successful()) {
            return [];
        }

        $interfaces = json_decode($result->output(), true) ?? [];

        return collect($interfaces)->map(function ($interface) {
            $addresses = collect($interface['addr_info'] ?? [])->map(function ($addr) {
                return [
                    'family' => $addr['family'] === 'inet' ? 'ipv4' : 'ipv6',
                    'address' => $addr['local'],
                    'prefix' => $addr['prefixlen'],
                ];
            });

            return [
                'name' => $interface['ifname'],
                'state' => $interface['operstate'],
                'mtu' => $interface['mtu'],
                'addresses' => $addresses->toArray(),
            ];
        })->toArray();
    }

    protected function getActiveConnections(string $host): array
    {
        $command = 'ss -tuln | grep LISTEN | wc -l';

        if ($host === 'localhost') {
            $result = Process::run($command);
        } else {
            $sshResult = $this->sshService->exec($host, $command);
            $result = new class($sshResult['success'], $sshResult['output'], $sshResult['exit_code'])
            {
                public function __construct(private bool $success, private string $output, private int $exitCode) {}

                public function successful(): bool
                {
                    return $this->success;
                }

                public function output(): string
                {
                    return $this->output;
                }

                public function exitCode(): int
                {
                    return $this->exitCode;
                }
            };
        }

        $listenCount = $result->successful() ? (int) trim($result->output()) : 0;

        return [
            'listening_ports' => $listenCount,
        ];
    }

    protected function getFirewallStatus(string $host): array
    {
        // Check ufw status
        $command = 'ufw status 2>/dev/null | head -1';

        if ($host === 'localhost') {
            $result = Process::run($command);
        } else {
            $sshResult = $this->sshService->exec($host, $command);
            $result = new class($sshResult['success'], $sshResult['output'], $sshResult['exit_code'])
            {
                public function __construct(private bool $success, private string $output, private int $exitCode) {}

                public function successful(): bool
                {
                    return $this->success;
                }

                public function output(): string
                {
                    return $this->output;
                }

                public function exitCode(): int
                {
                    return $this->exitCode;
                }
            };
        }

        if ($result->successful() && str_contains($result->output(), 'Status:')) {
            return [
                'type' => 'ufw',
                'status' => str_contains($result->output(), 'active') ? 'active' : 'inactive',
            ];
        }

        // Check iptables
        $command = "iptables -L -n | grep -c 'Chain'";

        if ($host === 'localhost') {
            $result = Process::run($command);
        } else {
            $sshResult = $this->sshService->exec($host, $command);
            $result = new class($sshResult['success'], $sshResult['output'], $sshResult['exit_code'])
            {
                public function __construct(private bool $success, private string $output, private int $exitCode) {}

                public function successful(): bool
                {
                    return $this->success;
                }

                public function output(): string
                {
                    return $this->output;
                }

                public function exitCode(): int
                {
                    return $this->exitCode;
                }
            };
        }

        $chainCount = $result->successful() ? (int) trim($result->output()) : 0;

        return [
            'type' => 'iptables',
            'status' => $chainCount > 3 ? 'configured' : 'default',
            'chains' => $chainCount,
        ];
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2).' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }

    public function generateReport(array $status, string $format = 'text'): string
    {
        return match ($format) {
            'json' => json_encode($status, JSON_PRETTY_PRINT),
            'html' => $this->generateHtmlReport($status),
            default => $this->generateTextReport($status),
        };
    }

    protected function generateTextReport(array $status): string
    {
        $report = [];
        $report[] = "System Status Report for {$status['host']}";
        $report[] = str_repeat('=', 50);
        $report[] = "Generated: {$status['timestamp']->format('Y-m-d H:i:s')}";
        $report[] = '';

        // Services Status
        $report[] = 'Services:';
        $report[] = str_repeat('-', 20);
        foreach ($status['services'] as $service) {
            $icon = $service['active'] ? '✅' : '❌';
            $report[] = "{$icon} {$service['name']}: {$service['status']}";
        }
        $report[] = '';

        // System Metrics
        if (isset($status['metrics']['memory'])) {
            $memory = $status['metrics']['memory'];
            $report[] = "Memory Usage: {$memory['used_human']}/{$memory['total_human']} ({$memory['usage_percent']}%)";
        }

        if (isset($status['metrics']['cpu'])) {
            $cpu = $status['metrics']['cpu'];
            $report[] = "CPU Usage: {$cpu['usage_percent']}%";
        }

        return implode("\n", $report);
    }

    protected function generateHtmlReport(array $status): string
    {
        $html = '<!DOCTYPE html><html><head><title>System Status Report</title></head><body>';
        $html .= '<h1>System Status Report</h1>';
        $html .= '<p><strong>Host:</strong> '.htmlspecialchars($status['host']).'</p>';
        $html .= '<p><strong>Generated:</strong> '.$status['timestamp']->format('Y-m-d H:i:s').'</p>';

        $html .= '<h2>Services</h2><ul>';
        foreach ($status['services'] as $service) {
            $icon = $service['active'] ? '✅' : '❌';
            $html .= "<li>{$icon} {$service['name']}: {$service['status']}</li>";
        }
        $html .= '</ul>';

        $html .= '</body></html>';

        return $html;
    }
}
