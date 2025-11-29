<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Services\RemoteExecutionService;
use NetServa\Fleet\Models\FleetVnode;

/**
 * Show Virtual Aliases Command
 *
 * NetServa 3.0 CRUD: READ operation for email aliases
 * Lists all aliases or shows details for a specific source
 *
 * Usage:
 *   shvalias <vnode>                  - List all aliases
 *   shvalias <vnode> <source>         - Show specific alias
 *   shvalias <vnode> --domain=example.com - Show all aliases for domain
 */
class ShvaliasCommand extends Command
{
    protected $signature = 'shvalias
                            {vnode : The vnode to query}
                            {source? : Optional specific source to display}
                            {--domain= : Filter by domain}
                            {--format=table : Output format (table, json, csv)}';

    protected $description = 'Show email aliases (NetServa 3.0 CRUD: Read)';

    protected RemoteExecutionService $remoteExecution;

    public function __construct(RemoteExecutionService $remoteExecution)
    {
        parent::__construct();
        $this->remoteExecution = $remoteExecution;
    }

    public function handle(): int
    {
        $vnodeName = $this->argument('vnode');
        $source = $this->argument('source');
        $domain = $this->option('domain');
        $format = $this->option('format');

        $this->info("Querying email aliases on: {$vnodeName}");

        // NetServa 3.0: Get vnode from database
        $vnode = FleetVnode::where('name', $vnodeName)->first();

        if (! $vnode) {
            $this->error("VNode not found: {$vnodeName}");
            $this->warn("Run 'addfleet {$vnodeName}' first.");

            return Command::FAILURE;
        }

        // Get DPATH from any vhost on this vnode (mail DB is shared across all vhosts)
        // Fallback to vnode.mail_db_path if no vhosts exist yet
        $dpath = null;
        $dtype = $vnode->database_type ?? 'sqlite';

        // Try to get from vhost vconfs first
        $vhost = $vnode->vhosts()->first();
        if ($vhost) {
            $dpathConf = $vhost->vconfs()->where('name', 'DPATH')->first();
            $dtypeConf = $vhost->vconfs()->where('name', 'DTYPE')->first();

            if ($dpathConf) {
                $dpath = $dpathConf->value;
            }
            if ($dtypeConf) {
                $dtype = $dtypeConf->value;
            }
        }

        // Fallback to vnode.mail_db_path (for vnodes without vhosts yet)
        if (! $dpath && $vnode->mail_db_path) {
            $dpath = $vnode->mail_db_path;
        }

        if (! $dpath) {
            $this->error("Mail database path (DPATH) not configured for {$vnodeName}");
            $this->warn("Run 'addfleet {$vnodeName}' to discover mail configuration.");

            return Command::FAILURE;
        }

        // Build database command based on type
        if ($dtype === 'mysql') {
            $sqlCmd = 'mysql sysadm';
        } else {
            $sqlCmd = "sqlite3 {$dpath}";
        }

        if ($this->option('verbose')) {
            $this->info("Using {$dtype} database: {$dpath}");
        }

        if ($source) {
            return $this->showSingleAlias($vnodeName, $source, $sqlCmd, $format);
        } elseif ($domain) {
            return $this->listDomainAliases($vnodeName, $domain, $sqlCmd, $format);
        } else {
            return $this->listAllAliases($vnodeName, $sqlCmd, $format);
        }
    }

    /**
     * Show details for a single alias
     */
    private function showSingleAlias(string $vnode, string $source, string $sqlCmd, string $format): int
    {
        $sql = "cat <<EOS | {$sqlCmd}
SELECT
    source,
    target,
    active,
    created_at,
    updated_at
FROM valias
WHERE source = '{$source}'
EOS";

        $result = $this->remoteExecution->executeAsRoot($vnode, $sql);

        if (! $result['success']) {
            $error = $result['error'] ?? 'Unknown error';
            $this->error("Failed to query database: {$error}");

            return Command::FAILURE;
        }

        $output = trim($result['output']);

        if (empty($output)) {
            $this->warn("Email alias not found: {$source}");

            return Command::FAILURE;
        }

        // Parse output (SQLite default format: value|value|value)
        $fields = explode('|', $output);

        if (count($fields) < 5) {
            $this->error('Unexpected database output format');

            return Command::FAILURE;
        }

        $alias = [
            'Source' => $fields[0],
            'Target' => $fields[1],
            'Active' => $fields[2] == 1 ? 'Yes' : 'No',
            'Created' => $fields[3],
            'Updated' => $fields[4],
        ];

        // Show target count if comma-separated
        $targetCount = count(explode(',', $fields[1]));
        if ($targetCount > 1) {
            $alias['Targets'] = $targetCount.' recipients';
        }

        if ($format === 'json') {
            $this->line(json_encode($alias, JSON_PRETTY_PRINT));
        } elseif ($format === 'csv') {
            $this->line(implode(',', array_keys($alias)));
            $this->line(implode(',', array_values($alias)));
        } else {
            $this->newLine();
            $this->table(['Field', 'Value'], array_map(
                fn ($key, $value) => [$key, $value],
                array_keys($alias),
                array_values($alias)
            ));

            // Show individual targets if multiple
            if ($targetCount > 1) {
                $this->newLine();
                $this->info('Target Recipients:');
                foreach (explode(',', $fields[1]) as $i => $target) {
                    $this->line('  '.($i + 1).'. '.trim($target));
                }
            }
        }

        return Command::SUCCESS;
    }

    /**
     * List aliases for a specific domain
     */
    private function listDomainAliases(string $vnode, string $domain, string $sqlCmd, string $format): int
    {
        $sql = "cat <<EOS | {$sqlCmd}
.mode csv
.headers on
SELECT
    source AS Source,
    target AS Target,
    CASE WHEN active = 1 THEN 'Yes' ELSE 'No' END AS Active,
    created_at AS Created
FROM valias
WHERE source LIKE '%@{$domain}' OR source = '@{$domain}'
ORDER BY source
EOS";

        return $this->processListOutput($vnode, $sql, $sqlCmd, $format, "aliases for {$domain}");
    }

    /**
     * List all aliases
     */
    private function listAllAliases(string $vnode, string $sqlCmd, string $format): int
    {
        $sql = "cat <<EOS | {$sqlCmd}
.mode csv
.headers on
SELECT
    source AS Source,
    target AS Target,
    CASE WHEN active = 1 THEN 'Yes' ELSE 'No' END AS Active,
    created_at AS Created
FROM valias
ORDER BY source
EOS";

        return $this->processListOutput($vnode, $sql, $sqlCmd, $format, 'email aliases');
    }

    /**
     * Process and display list output
     */
    private function processListOutput(string $vnode, string $sql, string $sqlCmd, string $format, string $description): int
    {
        $result = $this->remoteExecution->executeAsRoot($vnode, $sql);

        if (! $result['success']) {
            $error = $result['error'] ?? 'Unknown error';
            $this->error("Failed to query database: {$error}");

            return Command::FAILURE;
        }

        $output = trim($result['output']);

        if (empty($output)) {
            $this->warn("No {$description} found");

            return Command::SUCCESS;
        }

        if ($format === 'json') {
            $lines = explode("\n", $output);
            $headers = str_getcsv(array_shift($lines));
            $aliases = [];

            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    continue;
                }
                $values = str_getcsv($line);
                $aliases[] = array_combine($headers, $values);
            }

            $this->line(json_encode($aliases, JSON_PRETTY_PRINT));
        } elseif ($format === 'csv') {
            $this->line($output);
        } else {
            // Table format
            $lines = explode("\n", $output);
            $headers = str_getcsv(array_shift($lines));
            $rows = [];

            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    continue;
                }
                $values = str_getcsv($line);
                // Truncate long target lists for table display
                if (isset($values[1]) && strlen($values[1]) > 60) {
                    $targetCount = count(explode(',', $values[1]));
                    $values[1] = substr($values[1], 0, 57)."... ({$targetCount} targets)";
                }
                $rows[] = $values;
            }

            $this->newLine();
            $this->table($headers, $rows);
            $this->info('Total aliases: '.count($rows));
        }

        return Command::SUCCESS;
    }
}
