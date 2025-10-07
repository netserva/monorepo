<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Models\SshHost;
use NetServa\Fleet\Models\FleetVNode;
use NetServa\Fleet\Models\FleetVSite;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * VNode CRUD Command
 *
 * Full CRUD operations for VNodes matching Filament admin panel
 */
class FleetVNodeCommand extends Command
{
    protected $signature = 'fleet:vnode
                            {action : Action to perform (list|show|create|edit|delete)}
                            {id? : VNode ID or name for show/edit/delete actions}
                            {--vsite= : Filter by VSite}';

    protected $description = 'Manage VNodes (Infrastructure Nodes) - CRUD operations';

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'list' => $this->listVNodes(),
            'show' => $this->showVNode(),
            'create' => $this->createVNode(),
            'edit' => $this->editVNode(),
            'delete' => $this->deleteVNode(),
            default => $this->showUsage(),
        };
    }

    protected function listVNodes(): int
    {
        $query = FleetVNode::with(['vsite', 'sshHost'])->withCount('vhosts');

        if ($vsiteName = $this->option('vsite')) {
            $vsite = FleetVSite::where('name', $vsiteName)->first();
            if (! $vsite) {
                error("VSite '{$vsiteName}' not found.");

                return self::FAILURE;
            }
            $query->where('vsite_id', $vsite->id);
        }

        $vnodes = $query->get();

        if ($vnodes->isEmpty()) {
            info('No VNodes found.');

            return self::SUCCESS;
        }

        table(
            ['ID', 'Name', 'VSite', 'Role', 'Environment', 'IP', 'VHosts', 'Status', 'SSH'],
            $vnodes->map(fn ($vn) => [
                $vn->id,
                $vn->name,
                $vn->vsite->name,
                $vn->role,
                $vn->environment,
                $vn->ip_address ?? '-',
                $vn->vhosts_count,
                $vn->status,
                $vn->sshHost ? '✓' : '✗',
            ])->toArray()
        );

        return self::SUCCESS;
    }

    protected function showVNode(): int
    {
        $vnode = $this->getVNode();
        if (! $vnode) {
            return self::FAILURE;
        }

        info("VNode Details: {$vnode->name}");

        table(['Property', 'Value'], [
            ['ID', $vnode->id],
            ['Name', $vnode->name],
            ['Slug', $vnode->slug],
            ['VSite', $vnode->vsite->name],
            ['SSH Host', $vnode->sshHost?->hostname ?? 'Not configured'],
            ['Role', $vnode->role],
            ['Environment', $vnode->environment],
            ['IP Address', $vnode->ip_address ?? 'Not set'],
            ['Operating System', $vnode->operating_system ?? 'Unknown'],
            ['Kernel Version', $vnode->kernel_version ?? 'Unknown'],
            ['CPU Cores', $vnode->cpu_cores ?? 'Unknown'],
            ['Memory (GB)', $vnode->memory_gb ?? 'Unknown'],
            ['Disk (GB)', $vnode->disk_gb ?? 'Unknown'],
            ['Services', $vnode->services ? implode(', ', $vnode->services) : 'None'],
            ['Discovery Method', $vnode->discovery_method],
            ['Last Discovered', $vnode->last_discovered_at?->format('Y-m-d H:i:s') ?? 'Never'],
            ['Last Error', $vnode->last_error ?? 'None'],
            ['Next Scan', $vnode->next_scan_at?->format('Y-m-d H:i:s') ?? 'Not scheduled'],
            ['Scan Frequency (hours)', $vnode->scan_frequency_hours],
            ['Description', $vnode->description ?? 'None'],
            ['Status', $vnode->status],
            ['Active', $vnode->is_active ? 'Yes' : 'No'],
            ['Created', $vnode->created_at->format('Y-m-d H:i:s')],
            ['Updated', $vnode->updated_at->format('Y-m-d H:i:s')],
        ]);

        // Show VHosts
        $vhosts = $vnode->vhosts()->get();
        if ($vhosts->isNotEmpty()) {
            info("\nVHosts on this VNode:");
            table(
                ['ID', 'Domain', 'Type', 'Instance ID', 'IP Addresses', 'Status'],
                $vhosts->map(fn ($vh) => [
                    $vh->id,
                    $vh->domain,
                    $vh->instance_type ?? '-',
                    $vh->instance_id ?? '-',
                    $vh->ip_addresses ? implode(', ', $vh->ip_addresses) : '-',
                    $vh->status,
                ])->toArray()
            );
        }

        return self::SUCCESS;
    }

    protected function createVNode(): int
    {
        info('Creating new VNode');

        // Select VSite
        $vsites = FleetVSite::pluck('name', 'id')->toArray();
        if (empty($vsites)) {
            error('No VSites found. Create a VSite first.');

            return self::FAILURE;
        }

        $vsiteId = select('Select VSite', $vsites);

        $name = text(
            label: 'VNode name',
            placeholder: 'e.g., pve1, k8s-master-01',
            required: true,
            validate: fn ($value) => FleetVNode::where('name', $value)->exists()
                ? 'VNode with this name already exists'
                : null
        );

        $role = select('Role', [
            'compute' => 'Compute (VMs, containers)',
            'network' => 'Network (routers, load balancers)',
            'storage' => 'Storage (NAS, backup)',
            'mixed' => 'Mixed (multiple roles)',
        ]);

        $environment = select('Environment', [
            'production' => 'Production',
            'staging' => 'Staging',
            'development' => 'Development',
        ]);

        $ipAddress = text('IP address (optional)');
        $description = text('Description (optional)');

        // SSH Host linking
        $sshHostId = null;
        if (confirm('Link to SSH host?', default: true)) {
            $sshHosts = SshHost::pluck('hostname', 'id')->toArray();
            if (! empty($sshHosts)) {
                $sshHosts = ['none' => '(None)'] + $sshHosts;
                $selected = select('Select SSH host', $sshHosts);
                $sshHostId = $selected !== 'none' ? $selected : null;
            } else {
                warning('No SSH hosts found. You can link one later.');
            }
        }

        try {
            $vnode = FleetVNode::create([
                'name' => $name,
                'slug' => str($name)->slug(),
                'vsite_id' => $vsiteId,
                'ssh_host_id' => $sshHostId,
                'role' => $role,
                'environment' => $environment,
                'ip_address' => $ipAddress ?: null,
                'description' => $description ?: null,
                'discovery_method' => 'manual',
                'scan_frequency_hours' => 24,
                'status' => 'active',
                'is_active' => true,
            ]);

            info("✅ VNode '{$vnode->name}' created successfully!");

            return self::SUCCESS;
        } catch (\Exception $e) {
            error("Failed to create VNode: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function editVNode(): int
    {
        $vnode = $this->getVNode();
        if (! $vnode) {
            return self::FAILURE;
        }

        info("Editing VNode: {$vnode->name}");

        $updates = [];

        if (confirm("Update name? (current: {$vnode->name})", default: false)) {
            $updates['name'] = text('New name', default: $vnode->name, required: true);
        }

        if (confirm("Update role? (current: {$vnode->role})", default: false)) {
            $updates['role'] = select('Role', [
                'compute' => 'Compute', 'network' => 'Network', 'storage' => 'Storage', 'mixed' => 'Mixed',
            ], default: $vnode->role);
        }

        if (confirm("Update environment? (current: {$vnode->environment})", default: false)) {
            $updates['environment'] = select('Environment', [
                'production' => 'Production', 'staging' => 'Staging', 'development' => 'Development',
            ], default: $vnode->environment);
        }

        if (confirm('Update IP address? (current: '.($vnode->ip_address ?? 'none').')', default: false)) {
            $updates['ip_address'] = text('IP address', default: $vnode->ip_address) ?: null;
        }

        if (confirm('Update description?', default: false)) {
            $updates['description'] = text('Description', default: $vnode->description) ?: null;
        }

        if (confirm('Update SSH host?', default: false)) {
            $sshHosts = ['none' => '(None)'] + SshHost::pluck('hostname', 'id')->toArray();
            $selected = select('SSH host', $sshHosts, default: $vnode->ssh_host_id ? (string) $vnode->ssh_host_id : 'none');
            $updates['ssh_host_id'] = $selected !== 'none' ? $selected : null;
        }

        if (confirm("Update scan frequency? (current: {$vnode->scan_frequency_hours} hours)", default: false)) {
            $updates['scan_frequency_hours'] = (int) text('Scan frequency (hours)', default: (string) $vnode->scan_frequency_hours);
        }

        $updates['status'] = select('Status', [
            'active' => 'Active', 'inactive' => 'Inactive', 'maintenance' => 'Maintenance', 'error' => 'Error',
        ], default: $vnode->status);

        $updates['is_active'] = confirm('Is active?', default: $vnode->is_active);

        if (empty($updates)) {
            info('No changes made.');

            return self::SUCCESS;
        }

        try {
            $vnode->update($updates);
            info("✅ VNode '{$vnode->name}' updated successfully!");

            return self::SUCCESS;
        } catch (\Exception $e) {
            error("Failed to update VNode: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function deleteVNode(): int
    {
        $vnode = $this->getVNode();
        if (! $vnode) {
            return self::FAILURE;
        }

        $vhostCount = $vnode->vhosts()->count();

        if ($vhostCount > 0) {
            warning("This VNode has {$vhostCount} VHosts. Deleting will also delete all VHosts!");
        }

        if (! confirm("Are you sure you want to delete VNode '{$vnode->name}'?", default: false)) {
            info('Deletion cancelled.');

            return self::SUCCESS;
        }

        try {
            $name = $vnode->name;
            $vnode->delete();
            info("✅ VNode '{$name}' deleted successfully!");

            return self::SUCCESS;
        } catch (\Exception $e) {
            error("Failed to delete VNode: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function getVNode(): ?FleetVNode
    {
        $id = $this->argument('id');
        if (! $id) {
            error('ID or name required for this action.');

            return null;
        }

        $vnode = FleetVNode::with(['vsite', 'sshHost'])
            ->where('id', $id)
            ->orWhere('name', $id)
            ->first();

        if (! $vnode) {
            error("VNode with ID or name '{$id}' not found.");

            return null;
        }

        return $vnode;
    }

    protected function showUsage(): int
    {
        info('Usage: php artisan fleet:vnode {action} {id?} [--vsite=name]');
        info('');
        info('Actions:');
        info('  list              List all VNodes');
        info('  show {id}         Show VNode details');
        info('  create            Create new VNode');
        info('  edit {id}         Edit existing VNode');
        info('  delete {id}       Delete VNode');
        info('');
        info('Options:');
        info('  --vsite=name      Filter by VSite name');
        info('');
        info('Examples:');
        info('  php artisan fleet:vnode list');
        info('  php artisan fleet:vnode list --vsite=goldcoast-proxmox-datacenter');
        info('  php artisan fleet:vnode show pve2');
        info('  php artisan fleet:vnode create');
        info('  php artisan fleet:vnode edit 1');
        info('  php artisan fleet:vnode delete pve2');

        return self::SUCCESS;
    }
}
