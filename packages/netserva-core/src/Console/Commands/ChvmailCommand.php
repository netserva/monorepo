<?php

namespace NetServa\Core\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use NetServa\Core\Services\RemoteExecutionService;
use NetServa\Fleet\Models\FleetVnode;

/**
 * Change Virtual Mail User Command
 *
 * NetServa 3.0 CRUD: UPDATE operation for virtual mailboxes
 * Allows updating password, active status, or other mailbox properties
 *
 * Usage:
 *   chvmail <vnode> <email> --password=newpass
 *   chvmail <vnode> <email> --active=0
 *   chvmail <vnode> <email> --password=newpass --active=1
 */
class ChvmailCommand extends Command
{
    protected $signature = 'chvmail
                            {vnode : The vnode to update}
                            {email : Email address to modify}
                            {--password= : New password}
                            {--active= : Active status (0=disabled, 1=enabled)}';

    protected $description = 'Change virtual mail user settings (NetServa 3.0 CRUD: Update)';

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
        $password = $this->option('password');
        $active = $this->option('active');

        // Validate that at least one option is provided
        if (! $password && $active === null) {
            $this->error('At least one option must be provided: --password or --active');
            $this->info('Usage: chvmail <vnode> <email> --password=newpass');
            $this->info('       chvmail <vnode> <email> --active=0');

            return Command::FAILURE;
        }

        // Validate active flag
        if ($active !== null && ! in_array($active, ['0', '1'])) {
            $this->error('Active flag must be 0 (disabled) or 1 (enabled)');

            return Command::FAILURE;
        }

        $this->info("Updating virtual mail user: {$email} on {$vnodeName}");

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
            // Check if user exists
            if (! $this->userExists($vnodeName, $email, $sqlCmd)) {
                $this->error("Virtual mail user not found: {$email}");

                return Command::FAILURE;
            }

            $updates = [];
            $changes = [];

            // Handle password update
            if ($password) {
                $passwordHash = $this->generatePasswordHash($vnodeName, $password);
                $updates[] = "password = '{$passwordHash}'";
                $updates[] = "clearpw = '{$password}'";
                $changes[] = 'password updated';
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
UPDATE vmails
SET {$updateSql}
WHERE user = '{$email}'
EOS";

            $result = $this->remoteExecution->executeAsRoot($vnodeName, $sql);

            if (! $result['success']) {
                throw new Exception("Failed to update database: {$result['error']}");
            }

            $this->info('✓ Successfully updated: '.implode(', ', $changes));

            Log::info('Virtual mail user updated', [
                'vnode' => $vnodeName,
                'email' => $email,
                'changes' => $changes,
            ]);

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error("Failed to update virtual mail user: {$e->getMessage()}");

            Log::error('Failed to update virtual mail user', [
                'vnode' => $vnodeName,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Check if user exists
     */
    private function userExists(string $vnode, string $email, string $sqlCmd): bool
    {
        $sql = "cat <<EOS | {$sqlCmd}
SELECT COUNT(*) FROM vmails WHERE user = '{$email}'
EOS";

        $result = $this->remoteExecution->executeAsRoot($vnode, $sql);

        return $result['success'] && trim($result['output']) === '1';
    }

    /**
     * Generate password hash using doveadm
     */
    private function generatePasswordHash(string $vnode, string $password): string
    {
        $result = $this->remoteExecution->executeAsRoot($vnode,
            "doveadm pw -s SHA512-CRYPT -p '{$password}'"
        );

        if (! $result['success']) {
            throw new Exception('Failed to generate password hash: '.$result['error']);
        }

        return trim($result['output']);
    }
}
