<?php

namespace NetServa\Core\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use NetServa\Core\Services\RemoteExecutionService;
use NetServa\Fleet\Models\FleetVnode;

/**
 * Add Virtual Alias Command
 *
 * NetServa 3.0 CRUD: CREATE operation for email aliases
 * Creates email forwarding/catch-all aliases
 *
 * Usage:
 *   addvalias <vnode> <source> <target>
 *   addvalias markc sales@example.com joe@example.com,jane@example.com
 *   addvalias markc @example.com admin@example.com
 */
class AddvaliasCommand extends Command
{
    protected $signature = 'addvalias
                            {vnode : The vnode to create on}
                            {source : Source address (user@domain or @domain for catch-all)}
                            {target : Target address(es) - comma-separated for multiple}';

    protected $description = 'Add email alias (NetServa 3.0 CRUD: Create)';

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
        $target = $this->argument('target');

        // Validate source format
        if (! $this->validateSource($source)) {
            $this->error('Invalid source format. Must be user@domain or @domain (catch-all)');

            return Command::FAILURE;
        }

        // Validate target format (can be comma-separated)
        $targets = array_map('trim', explode(',', $target));
        foreach ($targets as $t) {
            if (! filter_var($t, FILTER_VALIDATE_EMAIL)) {
                $this->error("Invalid target email format: {$t}");

                return Command::FAILURE;
            }
        }

        $this->info("Creating email alias on: {$vnodeName}");
        $this->line("  Source: {$source}");
        $this->line("  Target: {$target}");

        // NetServa 3.0: Get vnode from database
        $vnode = FleetVnode::where('name', $vnodeName)->first();

        if (! $vnode) {
            $this->error("VNode not found: {$vnodeName}");
            $this->warn("Run 'addfleet {$vnodeName}' first.");

            return Command::FAILURE;
        }

        // Use SQLite database on remote vnode
        $sqlCmd = 'sqlite3 /var/lib/sqlite/sysadm/sysadm.db';

        try {
            // Check if alias already exists
            if ($this->aliasExists($vnodeName, $source, $sqlCmd)) {
                $this->error("Email alias already exists: {$source}");
                $this->warn("Use 'chvalias' to update existing alias");

                return Command::FAILURE;
            }

            // Verify domain exists in vhosts
            $domain = $this->extractDomain($source);
            if (! $this->domainExists($vnodeName, $domain, $sqlCmd)) {
                $this->error("Domain not found in vhosts: {$domain}");
                $this->warn("Run 'addvhost {$vnodeName} {$domain}' first");

                return Command::FAILURE;
            }

            // Create alias
            $this->createAlias($vnodeName, $source, $target, $sqlCmd);

            $this->info('✓ Email alias created successfully');
            $this->newLine();
            $this->line('Alias details:');
            $this->line("  Source: {$source}");
            $this->line("  Target: {$target}");
            $this->line('  Status: Active');

            Log::info('Email alias created', [
                'vnode' => $vnodeName,
                'source' => $source,
                'target' => $target,
            ]);

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error("Failed to create email alias: {$e->getMessage()}");

            Log::error('Failed to create email alias', [
                'vnode' => $vnodeName,
                'source' => $source,
                'target' => $target,
                'error' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Validate source format
     */
    private function validateSource(string $source): bool
    {
        // Catch-all: @domain.com
        if (preg_match('/^@[a-z0-9.-]+\.[a-z]{2,}$/i', $source)) {
            return true;
        }

        // Regular email: user@domain.com
        return filter_var($source, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Extract domain from source
     */
    private function extractDomain(string $source): string
    {
        // Remove @ prefix if catch-all
        $source = ltrim($source, '@');

        // Extract domain part
        if (str_contains($source, '@')) {
            return substr($source, strpos($source, '@') + 1);
        }

        return $source;
    }

    /**
     * Check if domain exists in vhosts
     */
    private function domainExists(string $vnode, string $domain, string $sqlCmd): bool
    {
        $sql = "cat <<EOS | {$sqlCmd}
SELECT COUNT(*) FROM vhosts WHERE domain = '{$domain}' AND active = 1
EOS";

        $result = $this->remoteExecution->executeAsRoot($vnode, $sql);

        return $result['success'] && trim($result['output']) === '1';
    }

    /**
     * Check if alias already exists
     */
    private function aliasExists(string $vnode, string $source, string $sqlCmd): bool
    {
        $sql = "cat <<EOS | {$sqlCmd}
SELECT COUNT(*) FROM valias WHERE source = '{$source}'
EOS";

        $result = $this->remoteExecution->executeAsRoot($vnode, $sql);

        return $result['success'] && trim($result['output']) === '1';
    }

    /**
     * Create alias in database
     */
    private function createAlias(string $vnode, string $source, string $target, string $sqlCmd): void
    {
        $date = date('Y-m-d H:i:s');

        $sql = "cat <<EOS | {$sqlCmd}
INSERT INTO valias (
    source,
    target,
    active,
    created_at,
    updated_at
) VALUES (
    '{$source}',
    '{$target}',
    1,
    '{$date}',
    '{$date}'
)
EOS";

        $result = $this->remoteExecution->executeAsRoot($vnode, $sql);

        if (! $result['success']) {
            throw new Exception("Failed to insert into database: {$result['error']}");
        }
    }
}
