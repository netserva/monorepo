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
 *   shpw --csv            # CSV export (for backup, import via chpw --import)
 *   shpw --sql            # SQL export (same APP_KEY only)
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
                            {--service= : Filter by service}
                            {--type= : Filter by type (VMAIL, APKEY, DBPWD, SSLKY, OAUTH)}
                            {--csv : Export as CSV (for backup/import via chpw --import)}
                            {--sql : Export as SQL INSERT statements (same APP_KEY only)}';

    protected $description = 'Show credentials from unified vault (READ)';

    public function handle(): int
    {
        // Handle export modes
        if ($this->option('sql')) {
            return $this->exportSql();
        }

        if ($this->option('csv')) {
            return $this->exportCsv();
        }

        // Build query
        $query = VPass::query();

        // Smart owner resolution: dots = domain, no dots = vnode
        $name = $this->argument('name');

        if ($name) {
            $owner = $this->resolveOwner($name);
            if (! $owner) {
                return Command::FAILURE;
            }
            $query->byOwner($owner);
        }

        // Apply filters
        if ($service = $this->option('service')) {
            $query->byService($service);
        }

        if ($type = $this->option('type')) {
            $query->byType($type);
        }

        // Execute query
        $credentials = $query->with('owner')->orderBy('pserv')->orderBy('pname')->get();

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
     * Show full table with headers and status
     */
    private function showFullTable($credentials, ?string $name): int
    {
        $context = $name ? (str_contains($name, '.') ? "VHost: {$name}" : "VNode: {$name}") : 'All';
        info("Credentials - {$context}");
        $this->line('');

        $rows = [];
        foreach ($credentials as $cred) {
            $status = $cred->pstat ? '✓' : '✗';
            $rows[] = [
                $cred->owner->name ?? $cred->owner->fqdn ?? 'N/A',
                $cred->pserv,
                $cred->pname,
                $cred->getSecret(),
                $status,
            ];
        }

        $this->table(['Owner', 'Service', 'Name', 'Secret', ''], $rows);

        $this->line('');
        warning('Backup reminder: shpw --csv > backup.csv (APP_KEY loss = data loss)');

        return Command::SUCCESS;
    }

    /**
     * Format credential for simple output (double-space separated, all parts clickable)
     * Consistent: identifier  password  [url]
     * - Simple email: "user@domain.com  password"
     * - With URL: "username  password  url"
     */
    private function formatSimple(VPass $cred): string
    {
        $secret = $cred->getSecret();
        $meta = $cred->pmeta ?? [];
        $url = $meta['url'] ?? null;
        $user = $meta['username'] ?? $meta['user'] ?? null;

        // Has URL: show "user  pass  url" (or "pname  pass  url" if no user)
        if ($url) {
            $identifier = $user ?? $cred->pname;

            return "{$identifier}  {$secret}  {$url}";
        }

        // Has username in meta but no URL
        if ($user) {
            return "{$user}  {$secret}";
        }

        // Simple: just pname and password
        return "{$cred->pname}  {$secret}";
    }

    /**
     * Export as CSV (spreadsheet compatible, importable via chpw --import)
     * Uses PHP's fputcsv() for proper RFC 4180 compliance
     */
    private function exportCsv(): int
    {
        $credentials = VPass::with('owner')->orderBy('owner_type')->orderBy('pserv')->get();

        if ($credentials->isEmpty()) {
            $this->error('No credentials to export');

            return Command::FAILURE;
        }

        // Use stdout for proper CSV output
        $output = fopen('php://output', 'w');

        // Header row
        fputcsv($output, ['owner_type', 'owner_name', 'ptype', 'pserv', 'pname', 'pdata', 'pmeta_json']);

        foreach ($credentials as $cred) {
            $ownerName = $cred->owner->domain ?? $cred->owner->name ?? $cred->owner->fqdn ?? 'unknown';
            fputcsv($output, [
                $cred->owner_type,
                $ownerName,
                $cred->ptype,
                $cred->pserv,
                $cred->pname,
                $cred->getSecret(),
                $cred->pmeta ? json_encode($cred->pmeta) : '',
            ]);
        }

        fclose($output);

        return Command::SUCCESS;
    }

    /**
     * Export as SQL INSERT statements (MySQL/SQLite compatible)
     */
    private function exportSql(): int
    {
        $credentials = VPass::with('owner')->orderBy('owner_type')->orderBy('pserv')->get();

        if ($credentials->isEmpty()) {
            $this->error('No credentials to export');

            return Command::FAILURE;
        }

        $this->line('-- VPass Export - '.now()->format('Y-m-d H:i:s'));
        $this->line('-- Compatible with MySQL and SQLite');
        $this->line('-- Import: mysql < backup.sql  OR  sqlite3 db.sqlite < backup.sql');
        $this->line('');

        foreach ($credentials as $cred) {
            $ownerType = addslashes($cred->owner_type);
            $ownerId = $cred->owner_id;
            $ptype = addslashes($cred->ptype);
            $pserv = addslashes($cred->pserv);
            $pname = addslashes($cred->pname);
            $pdata = addslashes($cred->pdata); // Already encrypted in DB
            $pmeta = $cred->pmeta ? addslashes(json_encode($cred->pmeta)) : 'NULL';
            $pstat = $cred->pstat ? 1 : 0;
            $now = now()->format('Y-m-d H:i:s');

            $pmetaVal = $pmeta === 'NULL' ? 'NULL' : "'{$pmeta}'";

            $this->line("INSERT INTO vpass (owner_type, owner_id, ptype, pserv, pname, pdata, pmeta, pstat, pdate, created_at, updated_at) VALUES ('{$ownerType}', {$ownerId}, '{$ptype}', '{$pserv}', '{$pname}', '{$pdata}', {$pmetaVal}, {$pstat}, '{$now}', '{$now}', '{$now}');");
        }

        return Command::SUCCESS;
    }

    /**
     * Smart owner resolution: dots = domain (vhost), no dots = vnode
     */
    private function resolveOwner(string $name): ?object
    {
        if (str_contains($name, '.')) {
            // Has dots = domain = VHost
            $owner = FleetVhost::where('fqdn', $name)->orWhere('domain', $name)->first();
            if (! $owner) {
                error("VHost not found: {$name}");
            }
        } else {
            // No dots = VNode
            $owner = FleetVnode::where('name', $name)->first();
            if (! $owner) {
                error("VNode not found: {$name}");
            }
        }

        return $owner;
    }
}
