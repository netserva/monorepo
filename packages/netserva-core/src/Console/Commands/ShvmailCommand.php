<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Services\RemoteExecutionService;
use NetServa\Fleet\Models\FleetVnode;

/**
 * Show Virtual Mail Users Command
 *
 * NetServa 3.0 CRUD: READ operation for virtual mailboxes
 * Lists all virtual mail users or shows details for a specific user
 *
 * Usage:
 *   shvmail <vnode>              - List all virtual mail users
 *   shvmail <vnode> <email>      - Show specific user details
 */
class ShvmailCommand extends Command
{
    protected $signature = 'shvmail
                            {vnode : The vnode to query}
                            {email? : Optional specific email to display}
                            {--format=table : Output format (table, json, csv)}';

    protected $description = 'Show virtual mail users (NetServa 3.0 CRUD: Read)';

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
        $format = $this->option('format');

        $this->info("Querying virtual mail users on: {$vnodeName}");

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

        if ($email) {
            return $this->showSingleUser($vnodeName, $email, $sqlCmd, $format);
        } else {
            return $this->listAllUsers($vnodeName, $sqlCmd, $format);
        }
    }

    /**
     * Show details for a single user
     */
    private function showSingleUser(string $vnode, string $email, string $sqlCmd, string $format): int
    {
        $sql = "cat <<EOS | {$sqlCmd}
SELECT
    user,
    home,
    pass,
    uid,
    gid,
    active,
    created_at,
    updated_at
FROM vmails
WHERE user = '{$email}'
EOS";

        $result = $this->remoteExecution->executeAsRoot($vnode, $sql);

        if (! $result['success']) {
            $error = $result['error'] ?? 'Unknown error';
            $this->error("Failed to query database: {$error}");

            return Command::FAILURE;
        }

        $output = trim($result['output']);

        if (empty($output)) {
            $this->warn("Virtual mail user not found: {$email}");

            return Command::FAILURE;
        }

        // Parse output (SQLite default format: value|value|value)
        $fields = explode('|', $output);

        if (count($fields) < 8) {
            $this->error('Unexpected database output format');

            return Command::FAILURE;
        }

        $user = [
            'Email' => $fields[0],
            'Home' => $fields[1],
            'Password' => '(hashed)',  // Never show password hash
            'UID' => $fields[3],
            'GID' => $fields[4],
            'Active' => $fields[5] == 1 ? 'Yes' : 'No',
            'Created' => $fields[6],
            'Updated' => $fields[7],
        ];

        if ($format === 'json') {
            $this->line(json_encode($user, JSON_PRETTY_PRINT));
        } elseif ($format === 'csv') {
            $this->line(implode(',', array_keys($user)));
            $this->line(implode(',', array_values($user)));
        } else {
            $this->newLine();
            $this->table(['Field', 'Value'], array_map(
                fn ($key, $value) => [$key, $value],
                array_keys($user),
                array_values($user)
            ));
        }

        // Also check valias entries
        $this->showAliases($vnode, $email, $sqlCmd);

        return Command::SUCCESS;
    }

    /**
     * List all virtual mail users
     */
    private function listAllUsers(string $vnode, string $sqlCmd, string $format): int
    {
        $sql = "cat <<EOS | {$sqlCmd}
.mode csv
.headers on
SELECT
    user AS Email,
    home AS Home,
    CASE WHEN active = 1 THEN 'Yes' ELSE 'No' END AS Active,
    created_at AS Created
FROM vmails
ORDER BY user
EOS";

        $result = $this->remoteExecution->executeAsRoot($vnode, $sql);

        if (! $result['success']) {
            $error = $result['error'] ?? 'Unknown error';
            $this->error("Failed to query database: {$error}");

            return Command::FAILURE;
        }

        $output = trim($result['output']);

        if (empty($output)) {
            $this->warn('No virtual mail users found');

            return Command::SUCCESS;
        }

        if ($format === 'json') {
            $lines = explode("\n", $output);
            $headers = str_getcsv(array_shift($lines));
            $users = [];

            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    continue;
                }
                $values = str_getcsv($line);
                $users[] = array_combine($headers, $values);
            }

            $this->line(json_encode($users, JSON_PRETTY_PRINT));
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
                $rows[] = str_getcsv($line);
            }

            $this->newLine();
            $this->table($headers, $rows);
            $this->info('Total users: '.count($rows));
        }

        return Command::SUCCESS;
    }

    /**
     * Show aliases for a user
     */
    private function showAliases(string $vnode, string $email, string $sqlCmd): void
    {
        $sql = "cat <<EOS | {$sqlCmd}
SELECT source, target, active
FROM valias
WHERE target LIKE '%{$email}%'
EOS";

        $result = $this->remoteExecution->executeAsRoot($vnode, $sql);

        if ($result['success'] && ! empty(trim($result['output']))) {
            $this->newLine();
            $this->info('Email Aliases:');

            $lines = explode("\n", trim($result['output']));
            $rows = [];

            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    continue;
                }
                $fields = explode('|', $line);
                $rows[] = [
                    'Source' => $fields[0] ?? '',
                    'Target' => $fields[1] ?? '',
                    'Active' => ($fields[2] ?? '0') == 1 ? 'Yes' : 'No',
                ];
            }

            $this->table(['Source', 'Target', 'Active'], $rows);
        }
    }
}
