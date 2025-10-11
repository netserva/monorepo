<?php

namespace NetServa\Dns\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Dns\Services\DnsProviderManagementService;

use function Laravel\Prompts\confirm;

/**
 * Change DNS Provider Command
 *
 * Update DNS provider configuration
 * Follows NetServa CRUD pattern: chdns (not "dns:provider:change")
 *
 * Usage: chdns <provider> [options]
 * Example: chdns 1 --endpoint=http://192.168.1.2:8081 --test
 * Example: chdns "Homelab PowerDNS" --activate
 */
class ChangeDnsCommand extends Command
{
    protected $signature = 'chdns
        {provider : Provider ID or name}
        {--name= : Change provider name}
        {--endpoint= : Update API endpoint}
        {--api-key= : Update API key}
        {--api-secret= : Update API secret}
        {--ssh-host= : Update SSH host}
        {--port= : Update API port}
        {--timeout= : Update timeout (seconds)}
        {--rate-limit= : Update rate limit (req/min)}
        {--provider-version= : Update version}
        {--priority= : Update priority/sort order}
        {--description= : Update description}
        {--activate : Set to active}
        {--deactivate : Set to inactive}
        {--test : Test connection after update}
        {--dry-run : Show what would change without changing}';

    protected $description = 'Change DNS provider configuration (NetServa CRUD pattern)';

    protected DnsProviderManagementService $providerService;

    public function __construct(DnsProviderManagementService $providerService)
    {
        parent::__construct();
        $this->providerService = $providerService;
    }

    public function handle(): int
    {
        $identifier = $this->argument('provider');

        // Build updates array from options
        $updates = [];
        $connectionUpdates = [];

        // Basic field updates
        if ($this->option('name')) {
            $updates['name'] = $this->option('name');
        }

        if ($this->option('description')) {
            $updates['description'] = $this->option('description');
        }

        if ($this->option('provider-version')) {
            $updates['version'] = $this->option('provider-version');
        }

        if ($this->option('timeout')) {
            $updates['timeout'] = (int) $this->option('timeout');
        }

        if ($this->option('rate-limit')) {
            $updates['rate_limit'] = (int) $this->option('rate-limit');
        }

        if ($this->option('priority')) {
            $updates['sort_order'] = (int) $this->option('priority');
        }

        // Active/inactive status
        if ($this->option('activate')) {
            $updates['active'] = true;
        } elseif ($this->option('deactivate')) {
            $updates['active'] = false;
        }

        // Connection config updates
        if ($this->option('endpoint')) {
            $connectionUpdates['api_endpoint'] = $this->option('endpoint');
        }

        if ($this->option('api-key')) {
            $connectionUpdates['api_key'] = $this->option('api-key');
        }

        if ($this->option('api-secret')) {
            $connectionUpdates['api_secret'] = $this->option('api-secret');
        }

        if ($this->option('ssh-host')) {
            $connectionUpdates['ssh_host'] = $this->option('ssh-host');
        }

        if ($this->option('port')) {
            $connectionUpdates['api_port'] = (int) $this->option('port');
        }

        if (! empty($connectionUpdates)) {
            $updates['connection_config'] = $connectionUpdates;
        }

        // Check if any updates provided
        if (empty($updates)) {
            $this->error('❌ No updates specified');
            $this->line('');
            $this->line('Available options:');
            $this->line('  --name, --endpoint, --api-key, --ssh-host, --port');
            $this->line('  --timeout, --rate-limit, --provider-version, --priority');
            $this->line('  --activate, --deactivate, --description');
            $this->line('');
            $this->line('Example: chdns 1 --endpoint=http://192.168.1.2:8081 --test');

            return self::FAILURE;
        }

        // Prepare options
        $options = [
            'test_connection' => $this->option('test'),
        ];

        // Dry run preview
        if ($this->option('dry-run')) {
            $this->newLine();
            $this->info('🔍 Dry run - no changes will be made');
            $this->line('');
            $this->line('Would update provider:');
            $this->line('  Identifier: '.$identifier);
            $this->line('  Updates: '.json_encode($updates, JSON_PRETTY_PRINT));
            $this->line('  Options: '.json_encode($options, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        // Update the provider
        $this->newLine();
        $this->line("🔧 Updating DNS Provider: <fg=yellow>{$identifier}</>");

        $result = $this->providerService->updateProvider(
            identifier: $identifier,
            updates: $updates,
            options: $options
        );

        if (! $result['success']) {
            $this->error("❌ {$result['message']}");

            if (isset($result['error'])) {
                $this->line("   Error: {$result['error']}");
            }

            return self::FAILURE;
        }

        $provider = $result['provider'];
        $changes = $result['changes'] ?? [];

        // Display changes
        if (! empty($changes)) {
            $this->newLine();
            $this->info('Changes:');

            foreach ($changes as $field => $change) {
                if ($field === 'connection_config') {
                    $this->line('  Connection Config:');
                    foreach ($change['new'] as $key => $value) {
                        if ($key === 'api_key' || $key === 'api_secret') {
                            $value = '••••••••';
                        }
                        $oldValue = $change['old'][$key] ?? 'Not set';
                        if ($key === 'api_key' || $key === 'api_secret') {
                            $oldValue = '••••••••';
                        }
                        if (($change['old'][$key] ?? null) !== $value) {
                            $this->line("    {$key}: <fg=gray>{$oldValue}</> → <fg=cyan>{$value}</>");
                        }
                    }
                } elseif ($field === 'active') {
                    $old = $change['old'] ? 'Active' : 'Inactive';
                    $new = $change['new'] ? 'Active' : 'Inactive';
                    $this->line("  Status: <fg=gray>{$old}</> → <fg=cyan>{$new}</>");
                } else {
                    $fieldName = ucfirst(str_replace('_', ' ', $field));
                    $old = $change['old'] ?? 'Not set';
                    $new = $change['new'];
                    $this->line("  {$fieldName}: <fg=gray>{$old}</> → <fg=cyan>{$new}</>");
                }
            }

            $this->newLine();
            $this->info("✅ DNS Provider '{$provider->name}' updated successfully");
        } else {
            $this->info('ℹ️ No changes made (values already set)');
        }

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

                if (isset($test['latency_ms'])) {
                    $this->line("   Response time: <fg=cyan>{$test['latency_ms']}ms</>");
                }
            } else {
                $this->warn('⚠️ Connection test failed');
                $this->line("   Error: {$test['message']}");
                $this->line('');
                $this->line('💡 Check your configuration:');
                $this->line("   shdns {$provider->id} --all");
            }
        }

        // Show next steps
        if (! empty($changes)) {
            $this->newLine();
            $this->line('💡 Next steps:');
            $this->line("   - View provider: shdns {$provider->id} --all");

            if (! $this->option('test')) {
                $this->line("   - Test connection: chdns {$provider->id} --test");
            }
        }

        return self::SUCCESS;
    }
}
