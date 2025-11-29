<?php

namespace NetServa\Core\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use NetServa\Core\Services\RemoteExecutionService;
use NetServa\Fleet\Models\FleetVnode;

use function Laravel\Prompts\confirm;

/**
 * Delete Virtual Alias Command
 *
 * NetServa 3.0 CRUD: DELETE operation for email aliases
 * Removes email forwarding/catch-all aliases from database
 *
 * Usage:
 *   delvalias <vnode> <source>           - Delete with confirmation
 *   delvalias <vnode> <source> --force   - Skip confirmation
 */
class DelvaliasCommand extends Command
{
    protected $signature = 'delvalias
                            {vnode : The vnode to delete from}
                            {source : Source address to remove}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Delete email alias (NetServa 3.0 CRUD: Delete)';

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
        $force = $this->option('force');

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
            // Check if alias exists and get info
            $aliasInfo = $this->getAliasInfo($vnodeName, $source, $sqlCmd);

            if (! $aliasInfo) {
                $this->error("Email alias not found: {$source}");

                return Command::FAILURE;
            }

            // Show what will be deleted
            $this->warn("Deleting email alias: {$source}");
            $this->info("Target: {$aliasInfo['target']}");

            // Determine alias type
            $aliasType = $this->determineAliasType($source, $aliasInfo['target']);
            $this->info("Type: {$aliasType}");

            // Confirmation prompt (unless --force)
            if (! $force) {
                $confirmed = confirm(
                    label: 'Are you sure you want to delete this email alias?',
                    default: false,
                    yes: 'Yes, delete it',
                    no: 'No, cancel'
                );

                if (! $confirmed) {
                    $this->info('Deletion cancelled');

                    return Command::SUCCESS;
                }
            }

            // Delete from database
            $this->deleteAlias($vnodeName, $source, $sqlCmd);

            $this->info('✓ Email alias deleted successfully');

            Log::info('Email alias deleted', [
                'vnode' => $vnodeName,
                'source' => $source,
                'target' => $aliasInfo['target'],
            ]);

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error("Failed to delete email alias: {$e->getMessage()}");

            Log::error('Failed to delete email alias', [
                'vnode' => $vnodeName,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Get alias information from database
     */
    private function getAliasInfo(string $vnode, string $source, string $sqlCmd): ?array
    {
        $sql = "cat <<EOS | {$sqlCmd}
SELECT target FROM valias WHERE source = '{$source}'
EOS";

        $result = $this->remoteExecution->executeAsRoot($vnode, $sql);

        if (! $result['success'] || empty(trim($result['output']))) {
            return null;
        }

        return ['target' => trim($result['output'])];
    }

    /**
     * Determine alias type for display
     */
    private function determineAliasType(string $source, string $target): string
    {
        // Check if catch-all
        if (str_starts_with($source, '@')) {
            return 'Catch-all (all mail to domain)';
        }

        // Check if multi-target
        $targetCount = count(explode(',', $target));
        if ($targetCount > 1) {
            return "Multi-target forwarding ({$targetCount} recipients)";
        }

        // Check if same domain forwarding
        if (str_contains($source, '@') && str_contains($target, '@')) {
            $sourceDomain = substr($source, strpos($source, '@') + 1);
            $targetDomain = substr($target, strpos($target, '@') + 1);

            if ($sourceDomain === $targetDomain) {
                return 'Same-domain alias';
            }
        }

        return 'External forwarding';
    }

    /**
     * Delete alias from database
     */
    private function deleteAlias(string $vnode, string $source, string $sqlCmd): void
    {
        $sql = "cat <<EOS | {$sqlCmd}
DELETE FROM valias WHERE source = '{$source}'
EOS";

        $result = $this->remoteExecution->executeAsRoot($vnode, $sql);

        if (! $result['success']) {
            throw new Exception("Failed to delete from database: {$result['error']}");
        }
    }
}
