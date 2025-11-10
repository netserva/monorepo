<?php

namespace NetServa\Dns\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Dns\Services\DnsProviderManagementService;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Add DNS Provider Command
 *
 * Creates a new DNS provider (PowerDNS, Cloudflare, Route53, etc.)
 * Follows NetServa CRUD pattern: adddns (not "dns:provider:add")
 *
 * Usage: adddns <name> <type> [options]
 * Example: adddns "Homelab PowerDNS" powerdns --endpoint=http://192.168.1.1:8081 --api-key=secret
 */
class AddDnsCommand extends Command
{
    protected $signature = 'adddns
        {name? : Provider name (e.g., "Homelab PowerDNS")}
        {type? : Provider type (powerdns, cloudflare, route53, digitalocean, linode, hetzner, custom)}
        {--endpoint= : API endpoint URL}
        {--api-key= : API authentication key}
        {--api-secret= : API secret (Cloudflare/Route53)}
        {--vnode= : VNode identifier (auto-set from ssh-host if not provided)}
        {--ssh-host= : SSH host for tunnel access (PowerDNS remote)}
        {--port=8081 : API port (default: 8081 for PowerDNS)}
        {--timeout=30 : Request timeout in seconds}
        {--rate-limit=100 : Max requests per minute}
        {--provider-version= : Provider version (e.g., "4.8.0")}
        {--priority=0 : Sort order/priority}
        {--description= : Provider description}
        {--inactive : Create as inactive (default: active)}
        {--email= : Account email (Cloudflare)}
        {--region=us-east-1 : AWS region (Route53)}
        {--access-key= : AWS access key ID (Route53)}
        {--secret-key= : AWS secret access key (Route53)}
        {--no-test : Skip connection test after creation}
        {--dry-run : Show what would be created without creating}';

    protected $description = 'Add a new DNS provider (NetServa CRUD pattern)';

    protected DnsProviderManagementService $providerService;

    public function __construct(DnsProviderManagementService $providerService)
    {
        parent::__construct();
        $this->providerService = $providerService;
    }

    public function handle(): int
    {
        // Get provider name
        $name = $this->argument('name');
        if (! $name) {
            $name = text(
                label: 'Provider name',
                placeholder: 'Homelab PowerDNS',
                required: true
            );
        }

        // Get provider type
        $type = $this->argument('type');
        if (! $type) {
            $type = select(
                label: 'Provider type',
                options: [
                    'powerdns' => 'PowerDNS',
                    'cloudflare' => 'Cloudflare',
                    'route53' => 'AWS Route53',
                    'digitalocean' => 'DigitalOcean DNS',
                    'linode' => 'Linode DNS',
                    'hetzner' => 'Hetzner DNS',
                    'custom' => 'Custom Provider',
                ],
                default: 'powerdns'
            );
        }

        // Validate type
        $validTypes = ['powerdns', 'cloudflare', 'route53', 'digitalocean', 'linode', 'hetzner', 'custom'];
        if (! in_array($type, $validTypes)) {
            $this->error("Invalid provider type: {$type}");
            $this->line('Valid types: '.implode(', ', $validTypes));

            return self::FAILURE;
        }

        // Build connection config based on provider type
        $connectionConfig = $this->buildConnectionConfig($type);

        if ($connectionConfig === null) {
            return self::FAILURE; // User cancelled or error
        }

        // Auto-populate vnode from ssh-host if not explicitly set
        $vnode = $this->option('vnode');
        if (! $vnode && isset($connectionConfig['ssh_host'])) {
            $vnode = $connectionConfig['ssh_host'];
        }

        // Build options
        $options = [
            'description' => $this->option('description'),
            'active' => ! $this->option('inactive'),
            'version' => $this->option('provider-version'),
            'rate_limit' => (int) $this->option('rate-limit'),
            'timeout' => (int) $this->option('timeout'),
            'sort_order' => (int) $this->option('priority'),
            'test_connection' => ! $this->option('no-test'),
            'vnode' => $vnode,
        ];

        // Show what we're about to create
        $this->newLine();
        $this->line("🚀 Creating DNS Provider: <fg=yellow>{$name}</>");
        $this->line('   Type: <fg=cyan>'.ucfirst($type).'</>');

        if ($vnode) {
            $this->line("   VNode: <fg=cyan>{$vnode}</>");
        }

        $this->line('   Active: <fg='.($options['active'] ? 'green' : 'red').'>'.($options['active'] ? 'Yes' : 'No').'</>');

        if ($type === 'powerdns') {
            $endpoint = $connectionConfig['api_endpoint'] ?? 'Not set';
            $sshHost = $connectionConfig['ssh_host'] ?? null;
            $this->line("   Endpoint: <fg=blue>{$endpoint}</>");
            if ($sshHost) {
                $this->line("   SSH Host: <fg=blue>{$sshHost}</>");
            }
            $this->line("   Port: <fg=blue>{$connectionConfig['api_port']}</>");
        } elseif ($type === 'cloudflare') {
            $email = $connectionConfig['email'] ?? 'Not set';
            $this->line("   Email: <fg=blue>{$email}</>");
        } elseif ($type === 'route53') {
            $region = $connectionConfig['region'] ?? 'us-east-1';
            $this->line("   Region: <fg=blue>{$region}</>");
        }

        $this->line("   Timeout: <fg=blue>{$options['timeout']}s</>");
        $this->line("   Rate Limit: <fg=blue>{$options['rate_limit']} req/min</>");

        if ($options['version']) {
            $this->line("   Version: <fg=blue>{$options['version']}</>");
        }

        // Dry run check
        if ($this->option('dry-run')) {
            $this->newLine();
            $this->info('✅ Dry run complete - no changes made');
            $this->line('');
            $this->line('Would create provider with:');
            $this->line('  Name: '.$name);
            $this->line('  Type: '.$type);
            $this->line('  Connection config: '.json_encode($connectionConfig, JSON_PRETTY_PRINT));
            $this->line('  Options: '.json_encode($options, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->newLine();

        // Create the provider
        $result = $this->providerService->createProvider(
            name: $name,
            type: $type,
            connectionConfig: $connectionConfig,
            options: $options
        );

        if (! $result['success']) {
            $this->error('❌ Failed to create DNS provider');
            $this->line("   Error: {$result['message']}");

            return self::FAILURE;
        }

        $provider = $result['provider'];

        $this->info('✅ DNS Provider created successfully');
        $this->line("   ID: <fg=yellow>{$provider->id}</>");
        $this->line("   Name: <fg=yellow>{$provider->name}</>");
        $this->line('   Status: <fg=green>Active</>');

        // Show connection test results
        if (isset($result['connection_test'])) {
            $this->newLine();
            $this->line('🔍 Testing connection...');

            $test = $result['connection_test'];

            if ($test['success']) {
                $this->info('✅ Connection successful');

                if (isset($test['server_info'])) {
                    $this->line("   Server: <fg=cyan>{$test['server_info']}</>");
                }

                if (isset($test['zones_count'])) {
                    $this->line("   Zones: <fg=cyan>{$test['zones_count']}</>");
                }

                if (isset($test['latency_ms'])) {
                    $this->line("   Response time: <fg=cyan>{$test['latency_ms']}ms</>");
                }
            } else {
                $this->warn('⚠️ Connection test failed');
                $this->line("   Error: {$test['message']}");
                $this->line('');
                $this->line('💡 Provider created but connection could not be verified.');
                $this->line('   Check your configuration and try: shdns '.$provider->id.' --test');
            }
        }

        // Show next steps
        $this->newLine();
        $this->info('💡 Next steps:');
        $this->line('   - Assign to venue: See FleetVenueResource in Filament UI');
        $this->line("   - Create zone: addzone example.com {$provider->id}");
        $this->line("   - View provider: shdns {$provider->id}");
        $this->line("   - Update provider: chdns {$provider->id} --endpoint=...");

        return self::SUCCESS;
    }

    /**
     * Build connection configuration based on provider type
     *
     * @return array|null Connection config or null if cancelled
     */
    protected function buildConnectionConfig(string $type): ?array
    {
        $config = [];

        switch ($type) {
            case 'powerdns':
                $config = $this->buildPowerDnsConfig();
                break;

            case 'cloudflare':
                $config = $this->buildCloudflareConfig();
                break;

            case 'route53':
                $config = $this->buildRoute53Config();
                break;

            case 'digitalocean':
            case 'linode':
            case 'hetzner':
                $config = $this->buildGenericCloudConfig($type);
                break;

            case 'custom':
                $config = $this->buildCustomConfig();
                break;
        }

        return $config;
    }

    /**
     * Build PowerDNS connection configuration
     */
    protected function buildPowerDnsConfig(): array
    {
        $config = [];

        // API endpoint
        $config['api_endpoint'] = $this->option('endpoint');
        if (! $config['api_endpoint']) {
            $config['api_endpoint'] = text(
                label: 'API endpoint',
                placeholder: 'http://192.168.1.1:8081',
                required: true,
                hint: 'PowerDNS API endpoint URL'
            );
        }

        // API key
        $config['api_key'] = $this->option('api-key');
        if (! $config['api_key']) {
            $config['api_key'] = password(
                label: 'API key',
                placeholder: 'your-api-key-here',
                required: true,
                hint: 'PowerDNS API-Key header value'
            );
        }

        // SSH host (optional)
        $config['ssh_host'] = $this->option('ssh-host');
        if (! $config['ssh_host'] && ! $this->option('ssh-host')) {
            $useSsh = confirm(
                label: 'Use SSH tunnel for remote access?',
                default: false,
                hint: 'Enable if PowerDNS is on a remote server'
            );

            if ($useSsh) {
                $config['ssh_host'] = text(
                    label: 'SSH host',
                    placeholder: 'ns1.example.com',
                    required: true,
                    hint: 'SSH host for tunnel access'
                );
            }
        }

        // API port
        $config['api_port'] = (int) $this->option('port');

        return $config;
    }

    /**
     * Build Cloudflare connection configuration
     */
    protected function buildCloudflareConfig(): array
    {
        $config = [];

        // API key
        $config['api_key'] = $this->option('api-key');
        if (! $config['api_key']) {
            $config['api_key'] = password(
                label: 'Cloudflare Global API Key',
                placeholder: 'your-global-api-key',
                required: true
            );
        }

        // API secret (token)
        $config['api_secret'] = $this->option('api-secret');
        if (! $config['api_secret']) {
            $config['api_secret'] = password(
                label: 'Cloudflare API Token',
                placeholder: 'your-api-token',
                required: false,
                hint: 'Optional - use token OR global API key'
            );
        }

        // Email
        $config['email'] = $this->option('email');
        if (! $config['email']) {
            $config['email'] = text(
                label: 'Cloudflare account email',
                placeholder: 'admin@example.com',
                required: true
            );
        }

        return array_filter($config);
    }

    /**
     * Build Route53 connection configuration
     */
    protected function buildRoute53Config(): array
    {
        $config = [];

        // Access key ID
        $config['access_key_id'] = $this->option('access-key');
        if (! $config['access_key_id']) {
            $config['access_key_id'] = text(
                label: 'AWS Access Key ID',
                placeholder: 'AKIAIOSFODNN7EXAMPLE',
                required: true
            );
        }

        // Secret access key
        $config['secret_access_key'] = $this->option('secret-key');
        if (! $config['secret_access_key']) {
            $config['secret_access_key'] = password(
                label: 'AWS Secret Access Key',
                placeholder: 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
                required: true
            );
        }

        // Region
        $config['region'] = $this->option('region') ?: 'us-east-1';

        return $config;
    }

    /**
     * Build generic cloud provider configuration
     */
    protected function buildGenericCloudConfig(string $type): array
    {
        $config = [];

        // API key/token
        $config['api_key'] = $this->option('api-key');
        if (! $config['api_key']) {
            $config['api_key'] = password(
                label: ucfirst($type).' API Token',
                placeholder: 'your-api-token',
                required: true
            );
        }

        // Endpoint (optional for some providers)
        if ($this->option('endpoint')) {
            $config['api_endpoint'] = $this->option('endpoint');
        }

        return $config;
    }

    /**
     * Build custom provider configuration
     */
    protected function buildCustomConfig(): array
    {
        $config = [];

        // API endpoint
        $config['api_endpoint'] = $this->option('endpoint');
        if (! $config['api_endpoint']) {
            $config['api_endpoint'] = text(
                label: 'API endpoint',
                placeholder: 'https://api.example.com',
                required: true
            );
        }

        // API key
        $config['api_key'] = $this->option('api-key');
        if (! $config['api_key']) {
            $config['api_key'] = password(
                label: 'API key',
                required: false,
                hint: 'Optional - depends on provider'
            );
        }

        return array_filter($config);
    }
}
