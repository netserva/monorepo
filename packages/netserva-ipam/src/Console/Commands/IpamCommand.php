<?php

namespace NetServa\Ipam\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Ipam\Services\IpAddressService;
use NetServa\Ipam\Services\SubnetService;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class IpamCommand extends Command
{
    protected $signature = 'ns:ipam
                          {action? : The IPAM action to perform (allocate|release|subnet)}
                          {--network= : Network range to work with (e.g., 192.168.1.0/24)}
                          {--ip= : Specific IP address}
                          {--dry-run : Show what would be done without executing}
                          {--force : Force operation without confirmation}';

    protected $description = 'Manage IP addresses and subnets';

    protected IpAddressService $ipService;

    protected SubnetService $subnetService;

    public function __construct(
        IpAddressService $ipService,
        SubnetService $subnetService
    ) {
        parent::__construct();
        $this->ipService = $ipService;
        $this->subnetService = $subnetService;
    }

    public function handle(): int
    {
        try {
            $action = $this->argument('action');

            if (! $action) {
                $action = $this->selectAction();
            }

            return match ($action) {
                'allocate' => $this->handleAllocate(),
                'release' => $this->handleRelease(),
                'subnet' => $this->handleSubnet(),
                default => $this->showHelp(),
            };
        } catch (\Exception $e) {
            $this->error("❌ IPAM operation failed: {$e->getMessage()}");

            return 1;
        }
    }

    protected function selectAction(): string
    {
        return select(
            'What IPAM operation would you like to perform?',
            [
                'allocate' => 'Allocate IP address',
                'release' => 'Release IP address',
                'subnet' => 'Subnet management',
            ]
        );
    }

    protected function handleAllocate(): int
    {
        $network = $this->option('network') ?: $this->getNetworkInput();
        $ip = $this->option('ip');

        if ($this->option('dry-run')) {
            $this->info("📝 Would allocate IP from network: {$network}");
            if ($ip) {
                $this->info("📝 Would allocate specific IP: {$ip}");
            }

            return 0;
        }

        if ($ip) {
            $allocated = $this->ipService->allocateSpecificIp($ip);
        } else {
            $allocated = $this->ipService->allocateFromSubnet($network);
        }

        if ($allocated) {
            $this->info("✅ IP address allocated: {$allocated['ip_address']}");
            $this->table(['Property', 'Value'], [
                ['IP Address', $allocated['ip_address']],
                ['Subnet', $allocated['subnet']],
                ['Status', $allocated['status']],
                ['Allocated At', $allocated['allocated_at']],
            ]);
        } else {
            $this->error('❌ Failed to allocate IP address');

            return 1;
        }

        return 0;
    }

    protected function handleRelease(): int
    {
        $ip = $this->option('ip') ?: text('Enter IP address to release:');

        if ($this->option('dry-run')) {
            $this->info("🔄 Would release IP address: {$ip}");

            return 0;
        }

        if (! $this->option('force') && ! confirm("Release IP address {$ip}?")) {
            $this->info('Release cancelled');

            return 0;
        }

        $released = $this->ipService->releaseIp($ip);

        if ($released) {
            $this->info("✅ IP address released: {$ip}");
        } else {
            $this->error("❌ Failed to release IP address: {$ip}");

            return 1;
        }

        return 0;
    }

    protected function handleSubnet(): int
    {
        $subnetAction = select(
            'What subnet operation?',
            [
                'create' => 'Create new subnet',
                'list' => 'List subnets',
                'analyze' => 'Analyze subnet utilization',
            ]
        );

        return match ($subnetAction) {
            'create' => $this->createSubnet(),
            'list' => $this->listSubnets(),
            'analyze' => $this->analyzeSubnets(),
            default => 0,
        };
    }

    protected function createSubnet(): int
    {
        $network = $this->getNetworkInput();
        $name = text('Subnet name:');
        $description = text('Description (optional):', '', false);

        if ($this->option('dry-run')) {
            $this->info("🏗️ Would create subnet: {$name} ({$network})");

            return 0;
        }

        $subnet = $this->subnetService->createSubnet([
            'name' => $name,
            'network_address' => $network,
            'description' => $description,
        ]);

        $this->info("✅ Subnet created: {$subnet['name']} ({$subnet['network_address']})");

        return 0;
    }

    protected function listSubnets(): int
    {
        $subnets = $this->subnetService->getAllSubnets();

        if ($subnets->isEmpty()) {
            $this->warn('⚠️ No subnets found');

            return 0;
        }

        $this->info('📊 Available subnets:');
        $this->table(
            ['Name', 'Network', 'Total IPs', 'Used', 'Available', 'Utilization'],
            $subnets->map(function ($subnet) {
                return [
                    $subnet['name'],
                    $subnet['network_address'],
                    $subnet['total_ips'],
                    $subnet['used_ips'],
                    $subnet['available_ips'],
                    $subnet['utilization_percentage'].'%',
                ];
            })->toArray()
        );

        return 0;
    }

    protected function analyzeSubnets(): int
    {
        $analysis = $this->subnetService->analyzeUtilization();

        $this->info('📊 Subnet utilization analysis:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Subnets', $analysis['total_subnets']],
                ['Total IP Addresses', $analysis['total_ips']],
                ['Used IP Addresses', $analysis['used_ips']],
                ['Average Utilization', $analysis['average_utilization'].'%'],
                ['Highest Utilization', $analysis['highest_utilization'].'%'],
                ['Subnets > 80% Full', $analysis['high_utilization_count']],
            ]
        );

        return 0;
    }

    protected function getNetworkInput(): string
    {
        return text(
            'Enter network (CIDR format):',
            '192.168.1.0/24'
        );
    }

    protected function showHelp(): int
    {
        $this->info('IPAM Manager Commands:');
        $this->line('  allocate  - Allocate IP addresses');
        $this->line('  release   - Release IP addresses');
        $this->line('  subnet    - Subnet management operations');
        $this->line('');
        $this->line('Options:');
        $this->line('  --network=CIDR      Network range (e.g., 192.168.1.0/24)');
        $this->line('  --ip=ADDRESS        Specific IP address');
        $this->line('  --dry-run           Show what would be done');
        $this->line('  --force             Skip confirmations');

        return 0;
    }
}
