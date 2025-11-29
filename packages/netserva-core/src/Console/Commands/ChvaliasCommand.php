<?php

namespace NetServa\Core\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use NetServa\Core\Services\RemoteExecutionService;
use NetServa\Fleet\Models\FleetVnode;

/**
 * Change Virtual Alias Command
 *
 * NetServa 3.0 CRUD: UPDATE operation for email aliases
 * Allows updating target addresses or active status
 *
 * Usage:
 *   chvalias <vnode> <source> --target=new@example.com
 *   chvalias <vnode> <source> --active=0
 *   chvalias <vnode> sales@example.com --target=joe@ex.com,jane@ex.com
 */
class ChvaliasCommand extends Command
{
    protected $signature = 'chvalias
                            {vnode : The vnode to update}
                            {source : Source address to modify}
                            {--target= : New target address(es) - comma-separated}
                            {--active= : Active status (0=disabled, 1=enabled)}';

    protected $description = 'Change email alias settings (NetServa 3.0 CRUD: Update)';

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
        $target = $this->option('target');
        $active = $this->option('active');

        // Validate that at least one option is provided
        if (! $target && $active === null) {
            $this->error('At least one option must be provided: --target or --active');
            $this->info('Usage: chvalias <vnode> <source> --target=new@example.com');
            $this->info('       chvalias <vnode> <source> --active=0');

            return Command::FAILURE;
        }

        // Validate active flag
        if ($active !== null && ! in_array($active, ['0', '1'])) {
            $this->error('Active flag must be 0 (disabled) or 1 (enabled)');

            return Command::FAILURE;
        }

        // Validate target format (can be comma-separated)
        if ($target) {
            $targets = array_map('trim', explode(',', $target));
            foreach ($targets as $t) {
                if (! filter_var($t, FILTER_VALIDATE_EMAIL)) {
                    $this->error("Invalid target email format: {$t}");

                    return Command::FAILURE;
                }
            }
        }

        $this->info("Updating email alias: {$source} on {$vnodeName}");

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
            // Check if alias exists
            if (! $this->aliasExists($vnodeName, $source, $sqlCmd)) {
                $this->error("Email alias not found: {$source}");

                return Command::FAILURE;
            }

            $updates = [];
            $changes = [];

            // Handle target update
            if ($target) {
                $updates[] = "target = '{$target}'";
                $targetCount = count(array_map('trim', explode(',', $target)));
                $changes[] = $targetCount === 1 ? 'target updated' : "{$targetCount} targets updated";
            }

            // Handle active status update
            if ($active !== null) {
                $updates[] = "active = {$active}";
                $changes[] = $active == 1 ? 'enabled' : 'disabled';
            }

            // Always update the updated_at timestamp
            $date = date('Y-m-d H:i:s');
            $updates[] = "updated_at = '{$date}'";

            // Execute update
            $updateSql = implode(', ', $updates);
            $sql = "cat <<EOS | {$sqlCmd}
UPDATE valias
SET {$updateSql}
WHERE source = '{$source}'
EOS";

            $result = $this->remoteExecution->executeAsRoot($vnodeName, $sql);

            if (! $result['success']) {
                throw new Exception("Failed to update database: {$result['error']}");
            }

            $this->info('✓ Successfully updated: '.implode(', ', $changes));

            Log::info('Email alias updated', [
                'vnode' => $vnodeName,
                'source' => $source,
                'changes' => $changes,
            ]);

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error("Failed to update email alias: {$e->getMessage()}");

            Log::error('Failed to update email alias', [
                'vnode' => $vnodeName,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Check if alias exists
     */
    private function aliasExists(string $vnode, string $source, string $sqlCmd): bool
    {
        $sql = "cat <<EOS | {$sqlCmd}
SELECT COUNT(*) FROM valias WHERE source = '{$source}'
EOS";

        $result = $this->remoteExecution->executeAsRoot($vnode, $sql);

        return $result['success'] && trim($result['output']) === '1';
    }
}
