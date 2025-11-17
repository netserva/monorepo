<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

/**
 * VHost CRUD Command
 *
 * Full CRUD operations for VHosts matching Filament admin panel
 */
class FleetVhostCommand extends Command
{
    protected $signature = 'fleet:vhost
                            {action : Action to perform (list|show|create|edit|delete)}
                            {id? : VHost ID or domain for show/edit/delete actions}
                            {--vnode= : Filter by VNode}
                            {--vsite= : Filter by VSite}';

    protected $description = 'Manage VHosts (Virtual Hosts/Instances) - CRUD operations';

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'list' => $this->listVHosts(),
            'show' => $this->showVHost(),
            'create' => $this->createVHost(),
            'edit' => $this->editVHost(),
            'delete' => $this->deleteVHost(),
            'env' => $this->manageEnvironmentVars(),
            default => $this->showUsage(),
        };
    }

    protected function listVHosts(): int
    {
        $query = FleetVhost::with(['vnode.vsite']);

        if ($vnodeName = $this->option('vnode')) {
            $vnode = FleetVnode::where('name', $vnodeName)->first();
            if (! $vnode) {
                error("VNode '{$vnodeName}' not found.");

                return self::FAILURE;
            }
            $query->where('vnode_id', $vnode->id);
        }

        if ($vsiteName = $this->option('vsite')) {
            $query->whereHas('vnode.vsite', fn ($q) => $q->where('name', $vsiteName));
        }

        $vhosts = $query->get();

        if ($vhosts->isEmpty()) {
            info('No VHosts found.');

            return self::SUCCESS;
        }

        table(
            ['ID', 'Domain', 'VNode', 'VSite', 'Type', 'Instance ID', 'IP Addresses', 'Status'],
            $vhosts->map(fn ($vh) => [
                $vh->id,
                $vh->domain,
                $vh->vnode->name,
                $vh->vnode->vsite->name,
                $vh->instance_type ?? '-',
                $vh->instance_id ?? '-',
                $vh->ip_addresses ? implode(', ', array_slice($vh->ip_addresses, 0, 2)) : '-',
                $vh->status,
            ])->toArray()
        );

        return self::SUCCESS;
    }

    protected function showVHost(): int
    {
        $vhost = $this->getVHost();
        if (! $vhost) {
            return self::FAILURE;
        }

        info("VHost Details: {$vhost->domain}");

        table(['Property', 'Value'], [
            ['ID', $vhost->id],
            ['Domain', $vhost->domain],
            ['Slug', $vhost->slug],
            ['VNode', $vhost->vnode->name],
            ['VSite', $vhost->vnode->vsite->name],
            ['Instance Type', $vhost->instance_type ?? 'Not set'],
            ['Instance ID', $vhost->instance_id ?? 'Not set'],
            ['CPU Cores', $vhost->cpu_cores ?? 'Unknown'],
            ['Memory (MB)', $vhost->memory_mb ?? 'Unknown'],
            ['Memory (GB)', $vhost->memory_gb ?? 'Unknown'],
            ['Disk (GB)', $vhost->disk_gb ?? 'Unknown'],
            ['IP Addresses', $vhost->ip_addresses ? implode(', ', $vhost->ip_addresses) : 'None'],
            ['Primary IP', $vhost->primary_ip ?? 'None'],
            ['Services', $vhost->services ? implode(', ', $vhost->services) : 'None'],
            ['Environment Vars', $vhost->environment_vars ? count($vhost->environment_vars).' variables' : 'None'],
            ['Last Discovered', $vhost->last_discovered_at?->format('Y-m-d H:i:s') ?? 'Never'],
            ['Last Error', $vhost->last_error ?? 'None'],
            ['Description', $vhost->description ?? 'None'],
            ['Status', $vhost->status],
            ['Active', $vhost->is_active ? 'Yes' : 'No'],
            ['Created', $vhost->created_at->format('Y-m-d H:i:s')],
            ['Updated', $vhost->updated_at->format('Y-m-d H:i:s')],
        ]);

        // Show environment variables
        if ($vhost->environment_vars && ! empty($vhost->environment_vars)) {
            info("\nEnvironment Variables:");
            $envTable = [];
            foreach ($vhost->environment_vars as $key => $value) {
                $envTable[] = [$key, is_string($value) ? (strlen($value) > 50 ? substr($value, 0, 50).'...' : $value) : json_encode($value)];
            }
            table(['Key', 'Value'], $envTable);
        }

        // Show service detection
        if ($vhost->services) {
            info("\nDetected Services:");
            foreach (['nginx', 'apache2', 'mysql', 'mariadb', 'postgresql', 'postfix', 'dovecot'] as $service) {
                $status = $vhost->hasService($service) ? '✓' : '✗';
                $this->line("  {$status} {$service}");
            }
        }

        return self::SUCCESS;
    }

    protected function createVHost(): int
    {
        info('Creating new VHost');

        // Select VNode
        $vnodes = FleetVnode::with('vsite')->get()->mapWithKeys(fn ($vn) => [
            $vn->id => "{$vn->name} ({$vn->vsite->name})",
        ])->toArray();

        if (empty($vnodes)) {
            error('No VNodes found. Create a VNode first.');

            return self::FAILURE;
        }

        $vnodeId = select('Select VNode', $vnodes);

        $domain = text(
            label: 'Domain/hostname',
            placeholder: 'e.g., example.com, mail.domain.org',
            required: true,
            validate: fn ($value) => FleetVhost::where('domain', $value)->exists()
                ? 'VHost with this domain already exists'
                : null
        );

        $instanceType = select('Instance type', [
            'vm' => 'Virtual Machine',
            'lxc' => 'LXC Container',
            'ct' => 'Container',
            'docker' => 'Docker Container',
            'vps' => 'VPS Instance',
            'hardware' => 'Physical Server',
        ]);

        $instanceId = text('Instance ID (optional)', 'VM ID, container name, etc.');

        // Resources
        $askResources = confirm('Specify resources (CPU, memory, disk)?', default: false);
        $cpuCores = $memoryMb = $diskGb = null;

        if ($askResources) {
            $cpuCores = (int) text('CPU cores', default: '2');
            $memoryMb = (int) text('Memory (MB)', default: '2048');
            $diskGb = (int) text('Disk (GB)', default: '20');
        }

        // IP addresses
        $ipInput = text('IP addresses (comma-separated, optional)');
        $ipAddresses = $ipInput ? array_map('trim', explode(',', $ipInput)) : [];

        // Services
        $servicesInput = text('Services (comma-separated, optional)', 'nginx, mysql, postfix');
        $services = $servicesInput ? array_map('trim', explode(',', $servicesInput)) : [];

        $description = text('Description (optional)');

        try {
            $vhost = FleetVhost::create([
                'domain' => $domain,
                'slug' => str($domain)->slug(),
                'vnode_id' => $vnodeId,
                'instance_type' => $instanceType,
                'instance_id' => $instanceId ?: null,
                'cpu_cores' => $cpuCores,
                'memory_mb' => $memoryMb,
                'disk_gb' => $diskGb,
                'ip_addresses' => $ipAddresses,
                'services' => $services,
                'environment_vars' => [],
                'description' => $description ?: null,
                'status' => 'active',
                'is_active' => true,
                'last_discovered_at' => now(),
            ]);

            info("✅ VHost '{$vhost->domain}' created successfully!");

            return self::SUCCESS;
        } catch (\Exception $e) {
            error("Failed to create VHost: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function editVHost(): int
    {
        $vhost = $this->getVHost();
        if (! $vhost) {
            return self::FAILURE;
        }

        info("Editing VHost: {$vhost->domain}");

        $updates = [];

        if (confirm("Update domain? (current: {$vhost->domain})", default: false)) {
            $updates['domain'] = text('New domain', default: $vhost->domain, required: true);
        }

        if (confirm('Update instance type? (current: '.($vhost->instance_type ?? 'none').')', default: false)) {
            $updates['instance_type'] = select('Instance type', [
                'vm' => 'VM', 'lxc' => 'LXC', 'ct' => 'Container', 'docker' => 'Docker', 'vps' => 'VPS', 'hardware' => 'Hardware',
            ], default: $vhost->instance_type);
        }

        if (confirm('Update instance ID? (current: '.($vhost->instance_id ?? 'none').')', default: false)) {
            $updates['instance_id'] = text('Instance ID', default: $vhost->instance_id) ?: null;
        }

        if (confirm('Update resources?', default: false)) {
            $updates['cpu_cores'] = (int) text('CPU cores', default: (string) ($vhost->cpu_cores ?? 2));
            $updates['memory_mb'] = (int) text('Memory (MB)', default: (string) ($vhost->memory_mb ?? 2048));
            $updates['disk_gb'] = (int) text('Disk (GB)', default: (string) ($vhost->disk_gb ?? 20));
        }

        if (confirm('Update IP addresses?', default: false)) {
            $currentIps = $vhost->ip_addresses ? implode(', ', $vhost->ip_addresses) : '';
            $ipInput = text('IP addresses (comma-separated)', default: $currentIps);
            $updates['ip_addresses'] = $ipInput ? array_map('trim', explode(',', $ipInput)) : [];
        }

        if (confirm('Update services?', default: false)) {
            $currentServices = $vhost->services ? implode(', ', $vhost->services) : '';
            $servicesInput = text('Services (comma-separated)', default: $currentServices);
            $updates['services'] = $servicesInput ? array_map('trim', explode(',', $servicesInput)) : [];
        }

        if (confirm('Update description?', default: false)) {
            $updates['description'] = text('Description', default: $vhost->description) ?: null;
        }

        $updates['status'] = select('Status', [
            'active' => 'Active', 'inactive' => 'Inactive', 'maintenance' => 'Maintenance', 'error' => 'Error',
        ], default: $vhost->status);

        $updates['is_active'] = confirm('Is active?', default: $vhost->is_active);

        if (empty($updates)) {
            info('No changes made.');

            return self::SUCCESS;
        }

        try {
            $vhost->update($updates);
            info("✅ VHost '{$vhost->domain}' updated successfully!");

            return self::SUCCESS;
        } catch (\Exception $e) {
            error("Failed to update VHost: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function manageEnvironmentVars(): int
    {
        $vhost = $this->getVHost();
        if (! $vhost) {
            return self::FAILURE;
        }

        info("Managing environment variables for: {$vhost->domain}");

        $action = select('Action', [
            'list' => 'List all variables',
            'get' => 'Get specific variable',
            'set' => 'Set variable',
            'unset' => 'Remove variable',
            'import' => 'Import from JSON file',
            'export' => 'Export to JSON file',
        ]);

        $envVars = $vhost->environment_vars ?? [];

        switch ($action) {
            case 'list':
                if (empty($envVars)) {
                    info('No environment variables set.');
                } else {
                    table(['Key', 'Value'], array_map(fn ($k, $v) => [
                        $k,
                        is_string($v) ? (strlen($v) > 100 ? substr($v, 0, 100).'...' : $v) : json_encode($v),
                    ], array_keys($envVars), $envVars));
                }
                break;

            case 'get':
                $key = text('Variable name');
                $value = $vhost->getEnvVar($key);
                info($value ? "Value: {$value}" : 'Variable not found.');
                break;

            case 'set':
                $key = text('Variable name', required: true);
                $value = text('Variable value', required: true);
                $vhost->setEnvVar($key, $value);
                $vhost->save();
                info("✅ Variable '{$key}' set successfully!");
                break;

            case 'unset':
                if (empty($envVars)) {
                    info('No variables to remove.');
                    break;
                }
                $key = select('Select variable to remove', array_keys($envVars));
                $vhost->setEnvVar($key, null);
                $vhost->save();
                info("✅ Variable '{$key}' removed successfully!");
                break;

            case 'import':
                $file = text('JSON file path', required: true);
                if (! file_exists($file)) {
                    error('File not found.');
                    break;
                }
                $json = json_decode(file_get_contents($file), true);
                if (! $json) {
                    error('Invalid JSON file.');
                    break;
                }
                $vhost->environment_vars = array_merge($envVars, $json);
                $vhost->save();
                info('✅ Imported '.count($json).' variables!');
                break;

            case 'export':
                $file = text('Output file path', default: "/tmp/{$vhost->domain}-env.json");
                file_put_contents($file, json_encode($envVars, JSON_PRETTY_PRINT));
                info("✅ Exported to: {$file}");
                break;
        }

        return self::SUCCESS;
    }

    protected function deleteVHost(): int
    {
        $vhost = $this->getVHost();
        if (! $vhost) {
            return self::FAILURE;
        }

        if (! confirm("Are you sure you want to delete VHost '{$vhost->domain}'?", default: false)) {
            info('Deletion cancelled.');

            return self::SUCCESS;
        }

        try {
            $domain = $vhost->domain;
            $vhost->delete();
            info("✅ VHost '{$domain}' deleted successfully!");

            return self::SUCCESS;
        } catch (\Exception $e) {
            error("Failed to delete VHost: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function getVHost(): ?FleetVhost
    {
        $id = $this->argument('id');
        if (! $id) {
            error('ID or domain required for this action.');

            return null;
        }

        $vhost = FleetVhost::with(['vnode.vsite'])
            ->where('id', $id)
            ->orWhere('domain', $id)
            ->first();

        if (! $vhost) {
            error("VHost with ID or domain '{$id}' not found.");

            return null;
        }

        return $vhost;
    }

    protected function showUsage(): int
    {
        info('Usage: php artisan fleet:vhost {action} {id?} [options]');
        info('');
        info('Actions:');
        info('  list              List all VHosts');
        info('  show {id}         Show VHost details');
        info('  create            Create new VHost');
        info('  edit {id}         Edit existing VHost');
        info('  delete {id}       Delete VHost');
        info('  env {id}          Manage environment variables');
        info('');
        info('Options:');
        info('  --vnode=name      Filter by VNode name');
        info('  --vsite=name      Filter by VSite name');
        info('');
        info('Examples:');
        info('  php artisan fleet:vhost list');
        info('  php artisan fleet:vhost list --vnode=pve2');
        info('  php artisan fleet:vhost show example.com');
        info('  php artisan fleet:vhost create');
        info('  php artisan fleet:vhost edit 1');
        info('  php artisan fleet:vhost env example.com');
        info('  php artisan fleet:vhost delete example.com');

        return self::SUCCESS;
    }
}
