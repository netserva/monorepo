<?php

namespace NetServa\Core\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use NetServa\Core\Services\RemoteExecutionService;
use NetServa\Fleet\Models\FleetVnode;

use function Laravel\Prompts\confirm;

/**
 * Delete Virtual Mail User Command
 *
 * NetServa 3.0 CRUD: DELETE operation for virtual mailboxes
 * Removes virtual mail user from database and optionally deletes maildir
 *
 * Usage:
 *   delvmail <vnode> <email>                    - Delete with confirmation
 *   delvmail <vnode> <email> --force            - Skip confirmation
 *   delvmail <vnode> <email> --keep-maildir     - Keep mailbox files
 */
class DelvmailCommand extends Command
{
    protected $signature = 'delvmail
                            {vnode : The vnode to delete from}
                            {email : Email address to remove}
                            {--force : Skip confirmation prompt}
                            {--keep-maildir : Keep mailbox directory (don\'t delete files)}';

    protected $description = 'Delete virtual mail user (NetServa 3.0 CRUD: Delete)';

    protected RemoteExecutionService $remoteExecution;

    public function __construct(RemoteExecutionService $remoteExecution)
    {
        parent::__construct();
        $this->remoteExecution = $remoteExecution;
    }

    public function handle(): int
    {
        $vnodeName = $this->argument('vnode');
        $email = $this->argument('email');
        $force = $this->option('force');
        $keepMaildir = $this->option('keep-maildir');

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
            // Check if user exists and get maildir path
            $userInfo = $this->getUserInfo($vnodeName, $email, $sqlCmd);

            if (! $userInfo) {
                $this->error("Virtual mail user not found: {$email}");

                return Command::FAILURE;
            }

            // Show what will be deleted
            $this->warn("Deleting virtual mail user: {$email}");
            $this->info("Maildir: /srv/{$userInfo['maildir']}/Maildir");
            $this->info('This will remove:');
            $this->line('  • Database entry in vmails table');
            $this->line('  • All related aliases in valias table');
            if (! $keepMaildir) {
                $this->line('  • All mailbox files and folders');
            }

            // Confirmation prompt (unless --force)
            if (! $force) {
                $confirmed = confirm(
                    label: 'Are you sure you want to delete this virtual mail user?',
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
            $this->deleteFromDatabase($vnodeName, $email, $sqlCmd);

            // Delete mailbox directory (unless --keep-maildir)
            if (! $keepMaildir) {
                $this->deleteMaildir($vnodeName, $userInfo['maildir']);
            }

            $this->info('✓ Virtual mail user deleted successfully');

            Log::info('Virtual mail user deleted', [
                'vnode' => $vnodeName,
                'email' => $email,
                'maildir_deleted' => ! $keepMaildir,
            ]);

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error("Failed to delete virtual mail user: {$e->getMessage()}");

            Log::error('Failed to delete virtual mail user', [
                'vnode' => $vnodeName,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Get user information from database
     */
    private function getUserInfo(string $vnode, string $email, string $sqlCmd): ?array
    {
        $sql = "cat <<EOS | {$sqlCmd}
SELECT maildir FROM vmails WHERE user = '{$email}'
EOS";

        $result = $this->remoteExecution->executeAsRoot($vnode, $sql);

        if (! $result['success'] || empty(trim($result['output']))) {
            return null;
        }

        return ['maildir' => trim($result['output'])];
    }

    /**
     * Delete user from database (vmails and valias tables)
     */
    private function deleteFromDatabase(string $vnode, string $email, string $sqlCmd): void
    {
        // Delete from vmails table
        $sql = "cat <<EOS | {$sqlCmd}
DELETE FROM vmails WHERE user = '{$email}';
DELETE FROM valias WHERE target = '{$email}';
EOS";

        $result = $this->remoteExecution->executeAsRoot($vnode, $sql);

        if (! $result['success']) {
            throw new Exception("Failed to delete from database: {$result['error']}");
        }

        $this->info('✓ Removed database entries');
    }

    /**
     * Delete mailbox directory from remote server
     */
    private function deleteMaildir(string $vnode, string $maildir): void
    {
        $fullPath = "/srv/{$maildir}";

        // Safety check: ensure path starts with /srv/
        if (! str_starts_with($fullPath, '/srv/')) {
            throw new Exception("Invalid maildir path: {$fullPath}");
        }

        // Check if directory exists
        $result = $this->remoteExecution->executeAsRoot($vnode, "test -d {$fullPath}");

        if (! $result['success']) {
            $this->warn("Mailbox directory not found: {$fullPath}");

            return;
        }

        // Delete the directory
        $result = $this->remoteExecution->executeAsRoot($vnode, "rm -rf {$fullPath}");

        if (! $result['success']) {
            throw new Exception("Failed to delete maildir: {$result['error']}");
        }

        $this->info("✓ Deleted mailbox directory: {$fullPath}");
    }
}
