<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Models\VPass;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

/**
 * Show Password Command - NetServa 3.0
 *
 * CRUD: Read - List credentials from unified vault
 *
 * Usage:
 *   shpw                  # Simple key/value list (all credentials)
 *   shpw mrn              # Simple key/value for mrn vnode
 *   shpw mrn --all        # Full table with headers
 *   shpw --csv            # CSV export (for backup)
 *
 * Smart Resolution:
 *   - No dots = VNode (mrn, gw, ns1gc)
 *   - Has dots = VHost (example.com)
 */
class ShpwCommand extends Command
{
    protected $signature = 'shpw
                            {name? : VNode name (no dots) or domain (has dots)}
                            {--all : Show full table with headers/status}
                            {--service= : Filter by service (mysql, ssh, cloudflare, etc.)}
                            {--csv : Export as CSV for backup}';

    protected $description = 'Show credentials from unified vault (READ)';

    public function handle(): int
    {
        if ($this->option('csv')) {
            return $this->exportCsv();
        }

        // Build query
        $query = VPass::query();

        // Smart owner resolution: dots = domain, no dots = vnode
        $name = $this->argument('name');

        if ($name) {
            if (str_contains($name, '.')) {
                // Has dots = domain = VHost
                $vhost = FleetVhost::where('domain', $name)->first();
                if (! $vhost) {
                    error("VHost not found: {$name}");

                    return Command::FAILURE;
                }
                $query->where('fleet_vhost_id', $vhost->id);
            } else {
                // No dots = VNode
                $vnode = FleetVnode::where('name', $name)->first();
                if (! $vnode) {
                    error("VNode not found: {$name}");

                    return Command::FAILURE;
                }
                $query->where('fleet_vnode_id', $vnode->id);
            }
        }

        // Apply service filter
        if ($service = $this->option('service')) {
            $query->where('service', $service);
        }

        // Execute query with eager loading
        $credentials = $query->with(['vsite', 'vnode', 'vhost'])
            ->orderBy('service')
            ->orderBy('name')
            ->get();

        if ($credentials->isEmpty()) {
            $this->line('No credentials found');

            return Command::SUCCESS;
        }

        // Full table mode with --all
        if ($this->option('all')) {
            return $this->showFullTable($credentials, $name);
        }

        // Default: simple key value output
        foreach ($credentials as $cred) {
            $this->line($this->formatSimple($cred));
        }

        return Command::SUCCESS;
    }

    /**
     * Show full table with headers
     */
    private function showFullTable($credentials, ?string $name): int
    {
        $context = $name ? (str_contains($name, '.') ? "VHost: {$name}" : "VNode: {$name}") : 'All';
        info("Credentials - {$context}");
        $this->line('');

        $rows = [];
        foreach ($credentials as $cred) {
            $owner = $cred->vhost?->domain ?? $cred->vnode?->name ?? $cred->vsite?->name ?? 'Global';
            $rows[] = [
                $owner,
                $cred->service,
                $cred->name,
                $cred->username ?? '-',
                $cred->getSecret(),
            ];
        }

        $this->table(['Owner', 'Service', 'Name', 'Username', 'Secret'], $rows);

        $this->line('');
        warning('Backup reminder: shpw --csv > backup.csv (APP_KEY loss = data loss)');

        return Command::SUCCESS;
    }

    /**
     * Format credential for simple output
     */
    private function formatSimple(VPass $cred): string
    {
        $secret = $cred->getSecret();
        $parts = [$cred->name, $secret];

        if ($cred->username) {
            $parts = [$cred->username, $secret];
        }

        if ($cred->url) {
            $parts[] = $cred->url;
        }

        return implode('  ', $parts);
    }

    /**
     * Export as CSV for backup
     */
    private function exportCsv(): int
    {
        $credentials = VPass::with(['vsite', 'vnode', 'vhost'])
            ->orderBy('service')
            ->get();

        if ($credentials->isEmpty()) {
            $this->error('No credentials to export');

            return Command::FAILURE;
        }

        $output = fopen('php://output', 'w');

        fputcsv($output, ['name', 'service', 'username', 'password', 'url', 'port', 'notes', 'vsite', 'vnode', 'vhost']);

        foreach ($credentials as $cred) {
            fputcsv($output, [
                $cred->name,
                $cred->service,
                $cred->username,
                $cred->getSecret(),
                $cred->url,
                $cred->port,
                $cred->notes,
                $cred->vsite?->name,
                $cred->vnode?->name,
                $cred->vhost?->domain,
            ]);
        }

        fclose($output);

        return Command::SUCCESS;
    }
}
