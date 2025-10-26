<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Models\SshHost;
use NetServa\Fleet\Models\FleetVNode;
use NetServa\Fleet\Models\FleetVSite;
use NetServa\Fleet\Services\FleetDiscoveryService;

/**
 * Add VNode Command (NetServa 3.0 CRUD: CREATE)
 *
 * Creates a new virtual node and optionally discovers it
 */
class AddvnodeCommand extends Command
{
    protected $signature = 'addvnode
                            {vsite : VSite name where vnode will be created}
                            {name : VNode name (server hostname)}
                            {ssh-host : SSH host identifier (must exist)}
                            {--role=compute : VNode role (webserver, mailserver, database, compute, etc.)}
                            {--environment=production : Environment (production, staging, development)}
                            {--discover : Automatically discover vnode details via SSH}';

    protected $description = 'Add a new vnode (server instance) (NetServa 3.0 CRUD: Create)';

    protected FleetDiscoveryService $discoveryService;

    public function __construct(FleetDiscoveryService $discoveryService)
    {
        parent::__construct();
        $this->discoveryService = $discoveryService;
    }

    public function handle(): int
    {
        $vsiteName = $this->argument('vsite');
        $name = $this->argument('name');
        $sshHostName = $this->argument('ssh-host');
        $role = $this->option('role');
        $environment = $this->option('environment');
        $discover = $this->option('discover');

        // Validate vsite exists
        $vsite = FleetVSite::where('name', $vsiteName)->first();
        if (! $vsite) {
            $this->error("VSite not found: {$vsiteName}");
            $this->info('Available vsites: '.FleetVSite::pluck('name')->implode(', '));
            $this->warn("Create vsite first: addvsite <venue> {$vsiteName} <technology>");

            return Command::FAILURE;
        }

        // Validate SSH host exists
        $sshHost = SshHost::where('host', $sshHostName)->first();
        if (! $sshHost) {
            $this->error("SSH host not found: {$sshHostName}");
            $this->info('Available SSH hosts: '.SshHost::pluck('host')->implode(', '));
            $this->warn("Create SSH host first: addssh {$sshHostName} <hostname>");

            return Command::FAILURE;
        }

        // Check if vnode already exists
        $existing = FleetVNode::where('name', $name)->first();
        if ($existing) {
            $this->error("VNode '{$name}' already exists.");

            return Command::FAILURE;
        }

        // Validate role
        $validRoles = ['webserver', 'mailserver', 'database', 'nameserver', 'compute', 'storage', 'network', 'workstation', 'hypervisor', 'mixed'];
        if (! in_array($role, $validRoles)) {
            $this->warn("Unknown role: {$role}. Using 'compute' as default.");
            $this->info('Valid roles: '.implode(', ', $validRoles));
        }

        $this->info("Creating vnode: {$name} on vsite {$vsiteName}");

        // Create vnode
        $vnode = FleetVNode::create([
            'vsite_id' => $vsite->id,
            'ssh_host_id' => $sshHost->id,
            'name' => $name,
            'role' => $role,
            'environment' => $environment,
        ]);

        $this->info('✓ VNode created successfully');
        $this->newLine();
        $this->line('VNode Details:');
        $this->line("  Name: {$vnode->name}");
        $this->line("  VSite: {$vsite->name}");
        $this->line("  SSH Host: {$sshHost->host} ({$sshHost->hostname})");
        $this->line("  Role: {$role}");
        $this->line("  Environment: {$environment}");

        // Auto-discover if requested
        if ($discover) {
            $this->newLine();
            $this->info('🔍 Discovering vnode details via SSH...');

            try {
                $discovered = $this->discoveryService->discoverVNode($vnode);

                if ($discovered) {
                    $vnode->refresh();
                    $this->info('✓ Discovery successful');
                    $this->newLine();
                    $this->line('Discovered Information:');
                    $this->line("  IP Address: {$vnode->ip_address}");
                    $this->line("  OS: {$vnode->operating_system}");
                    $this->line("  CPU Cores: {$vnode->cpu_cores}");
                    $this->line("  Memory: {$vnode->memory_mb} MB");
                    $this->line("  Disk: {$vnode->disk_gb} GB");

                    if ($vnode->fqdn) {
                        $this->newLine();
                        $this->line('FQDN Configuration:');
                        $this->line("  FQDN: {$vnode->fqdn}");

                        if ($vnode->email_capable) {
                            $this->line('  ✅ FCrDNS: Validated (A + PTR records found)');
                            $this->line('  ✅ Email Capable: Yes');
                            if ($vnode->fcrdns_validated_at) {
                                $this->line("  ✅ Validated: {$vnode->fcrdns_validated_at->diffForHumans()}");
                            }
                        } else {
                            $this->line('  ⚠️  FCrDNS: Not available (using /etc/hosts fallback)');
                            $this->line('  ❌ Email Capable: No (DNS records required)');
                            $this->warn('  Note: Vnode will work but cannot send email reliably');
                        }
                    }
                } else {
                    $this->warn('⚠️  Discovery failed. Check SSH connectivity.');
                    $this->info("You can run discovery later: addfleet {$name}");
                }
            } catch (\Exception $e) {
                $this->error("Discovery error: {$e->getMessage()}");
                $this->info("You can run discovery later: addfleet {$name}");
            }
        }

        $this->newLine();
        $this->info('Next steps:');
        if (! $discover) {
            $this->line("  1. Discover details: addfleet {$name}");
        }
        $this->line("  2. Add vhosts: addvhost {$name} <domain>");
        $this->line('  3. View vnodes: shvnode');

        return Command::SUCCESS;
    }
}
