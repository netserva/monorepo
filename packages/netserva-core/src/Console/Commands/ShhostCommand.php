<?php

namespace NetServa\Core\Console\Commands;

use NetServa\Core\Services\RemoteExecutionService;
use Symfony\Component\Console\Helper\Table;

/**
 * Show Host/Users Command
 *
 * Follows NetServa CRUD pattern: shhost (show system users and their vhosts)
 * Usage: shhost [domain|uid|homedir|all] [--shost=motd]
 * With context: export VNODE=motd; shhost all
 */
class ShhostCommand extends BaseNetServaCommand
{
    protected $signature = 'shhost {query? : Domain, UID, home directory, or "all"}
                           {--shost= : SSH host identifier}
                           {--format=table : Output format (table|original)}';

    protected $description = 'Show system users and their virtual hosts (NetServa CRUD pattern)';

    protected RemoteExecutionService $remoteExecution;

    public function __construct(RemoteExecutionService $remoteExecution)
    {
        parent::__construct();
        $this->remoteExecution = $remoteExecution;
    }

    public function handle(): int
    {
        return $this->executeWithContext(function () {
            $query = $this->argument('query') ?? 'all';
            $VNODE = $this->requireShost();

            $this->line("🖥️  System users on server: <fg=cyan>{$VNODE}</>");

            if ($this->hasOption('dry-run') && $this->option('dry-run')) {
                $this->dryRun("Show system users on {$VNODE}", [
                    "SSH to {$VNODE}",
                    'Query system users with: getent passwd',
                    'Filter NetServa users (u[0-9]* and sysadm)',
                    'Parse user information and vhost associations',
                    'Format output as table or original format',
                ]);

                return 0;
            }

            // Get system users from remote server
            $users = $this->getSystemUsers($VNODE, $query);

            if (empty($users)) {
                if ($query === 'all') {
                    $this->line("   <fg=gray>No NetServa users found on {$VNODE}</>");
                } else {
                    $this->error("❌ No system user contains '{$query}'");
                }

                return 0;
            }

            // Display results
            if ($query === 'all') {
                $this->showAllUsers($users);
            } else {
                if (! empty($users)) {
                    $this->showSpecificUser($users[0]);
                } else {
                    $this->error("❌ No system user contains '{$query}'");

                    return 1;
                }
            }

            return 0;
        });
    }

    protected function getSystemUsers(string $VNODE, string $query): array
    {
        // Get NetServa users (u[0-9]* and sysadm)
        $command = 'getent passwd | grep -E "^u[0-9]|sysadm" | sort';

        $result = $this->remoteExecution->executeAsRoot($VNODE, $command);

        if (! $result['success']) {
            $this->error('❌ Failed to query system users: '.($result['error'] ?? 'Unknown error'));

            return [];
        }

        $allUsers = [];
        $lines = explode("\n", trim($result['output']));

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            $user = $this->parseUserLine($line);
            if ($user) {
                $allUsers[] = $user;
            }
        }

        // Filter based on query
        if ($query === 'all') {
            return $allUsers;
        }

        // Search for specific user by domain, UID, or home directory
        return array_values(array_filter($allUsers, function ($user) use ($query) {
            return str_contains($user['gecos'], $query) ||
                   str_contains($user['uid'], $query) ||
                   str_contains($user['home'], $query) ||
                   str_contains($user['username'], $query) ||
                   str_contains($user['host'], $query);
        }));
    }

    protected function parseUserLine(string $line): ?array
    {
        // Parse passwd format: username:password:uid:gid:gecos:home:shell
        $parts = explode(':', $line);
        if (count($parts) < 7) {
            return null;
        }

        $gecos = $parts[4];
        $host = '';
        $ip = '';

        // Parse gecos field for vhost information
        if (str_contains($gecos, ',')) {
            // Format: domain.com,192.168.1.100
            $gecosParts = explode(',', $gecos);
            $host = $gecosParts[0];
            $ip = $gecosParts[1] ?? '';
        } else {
            // Simple format: domain.com
            $host = $gecos;
        }

        return [
            'username' => $parts[0],
            'uid' => $parts[2],
            'gid' => $parts[3],
            'gecos' => $gecos,
            'host' => $host,
            'ip' => $ip,
            'home' => $parts[5],
            'shell' => $parts[6],
        ];
    }

    protected function showAllUsers(array $users): void
    {
        $this->line('');

        if ($this->option('format') === 'original') {
            // Original NetServa format
            foreach ($users as $user) {
                if (! empty($user['ip'])) {
                    printf("%-7s %-19s %-19s %s\n",
                        $user['username'],
                        $user['host'],
                        $user['ip'],
                        $user['home']
                    );
                } else {
                    printf("%-7s %-39s %s\n",
                        $user['username'],
                        $user['host'],
                        $user['home']
                    );
                }
            }
        } else {
            // Modern table format
            $table = new Table($this->output);
            $table->setHeaders(['User', 'VHost/Domain', 'IP Address', 'UID', 'Home Directory', 'Shell']);

            foreach ($users as $user) {
                $table->addRow([
                    $user['username'],
                    $user['host'] ?: 'N/A',
                    $user['ip'] ?: 'N/A',
                    $user['uid'],
                    $user['home'],
                    basename($user['shell']),
                ]);
            }

            $table->render();

            $this->line('');
            $this->line('<fg=gray>💡 Use --format=original for NetServa classic output</>');
        }
    }

    protected function showSpecificUser(array $user): void
    {
        $this->line('');
        $this->line('<fg=blue>👤 User Details:</>');
        $this->line('  <fg=blue>host:</> <fg=yellow>'.($user['host'] ?: 'N/A').'</>');

        if (! empty($user['ip'])) {
            $this->line('  <fg=blue>IP:</> <fg=yellow>'.$user['ip'].'</>');
        }

        $this->line('  <fg=blue>user:</> <fg=yellow>'.$user['username'].'</>');
        $this->line('  <fg=blue>uid:</> <fg=yellow>'.$user['uid'].'</>');
        $this->line('  <fg=blue>gid:</> <fg=yellow>'.$user['gid'].'</>');
        $this->line('  <fg=blue>home:</> <fg=yellow>'.$user['home'].'</>');
        $this->line('  <fg=blue>shell:</> <fg=yellow>'.$user['shell'].'</>');

        // Show related vhost config if available
        if (! empty($user['host']) && $user['host'] !== 'N/A') {
            $this->line('');
            $this->line('<fg=gray>💡 Use "shvhost '.$user['host'].'" for vhost details</>');
        }
    }
}
