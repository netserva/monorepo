<?php

namespace NetServa\Core\Services;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * User Management Service
 *
 * Narrow implementation focused on user management via SSH.
 * Manages system users on remote servers using VHost configurations.
 */
class UserManagementService
{
    protected VhostConfigService $vhostConfig;

    protected RemoteExecutionService $remoteExecution;

    public function __construct(
        VhostConfigService $vhostConfig,
        RemoteExecutionService $remoteExecution
    ) {
        $this->vhostConfig = $vhostConfig;
        $this->remoteExecution = $remoteExecution;
    }

    /**
     * Add user to remote server using VHost configuration
     */
    public function addUser(string $host, string $shost, string $vhost, string $username): bool
    {
        try {
            // Load VHost configuration
            $config = $this->vhostConfig->loadVhostConfig($shost, $vhost);

            Log::info('Adding user to remote server', [
                'host' => $host,
                'shost' => $shost,
                'vhost' => $vhost,
                'username' => $username,
            ]);

            // Validate required configuration
            if (! $this->validateVhostConfig($config)) {
                throw new Exception('VHost configuration is missing required fields');
            }

            // First test if we can connect and have root access
            if (! $this->remoteExecution->testRootAccess($host)) {
                throw new Exception('Cannot establish root access to host');
            }

            // Check if user already exists
            if ($this->userExists($host, $username)) {
                Log::info('User already exists', ['host' => $host, 'username' => $username]);

                return true; // Not an error, user exists
            }

            // Create the user
            return $this->createSystemUser($host, $username, $config);

        } catch (Exception $e) {
            Log::error('Failed to add user', [
                'host' => $host,
                'shost' => $shost,
                'vhost' => $vhost,
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Remove user from remote server
     */
    public function removeUser(string $host, string $shost, string $vhost, string $username): bool
    {
        try {
            Log::info('Removing user from remote server', [
                'host' => $host,
                'shost' => $shost,
                'vhost' => $vhost,
                'username' => $username,
            ]);

            // Test root access
            if (! $this->remoteExecution->testRootAccess($host)) {
                throw new Exception('Cannot establish root access to host');
            }

            // Check if user exists
            if (! $this->userExists($host, $username)) {
                Log::info('User does not exist', ['host' => $host, 'username' => $username]);

                return true; // Not an error, user doesn't exist
            }

            // Remove the user
            $commands = [
                "userdel -r {$username}", // Remove user and home directory
                "groupdel {$username} 2>/dev/null || true", // Remove group if it exists
            ];

            $result = $this->remoteExecution->executeSequence($host, $commands, false);

            if ($result['success']) {
                Log::info('User removed successfully', [
                    'host' => $host,
                    'username' => $username,
                ]);

                return true;
            } else {
                throw new Exception('Failed to remove user: '.$this->formatCommandResults($result['results']));
            }

        } catch (Exception $e) {
            Log::error('Failed to remove user', [
                'host' => $host,
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * List users on remote server
     */
    public function listUsers(string $host): array
    {
        try {
            // Get all users with UID >= 1000 (regular users)
            $result = $this->remoteExecution->executeAsRoot($host, "getent passwd | awk -F: '\$3 >= 1000 && \$3 < 65534 {print \$1, \$3, \$5, \$6}' | sort");

            if (! $result['success']) {
                throw new Exception('Failed to list users: '.$result['output']);
            }

            $users = [];
            $lines = array_filter(explode("\n", trim($result['output'])));

            foreach ($lines as $line) {
                $parts = explode(' ', $line, 4);
                if (count($parts) >= 3) {
                    $users[] = [
                        'username' => $parts[0],
                        'uid' => $parts[1],
                        'name' => $parts[2] ?? '',
                        'home' => $parts[3] ?? '',
                    ];
                }
            }

            Log::info('Listed users on remote server', [
                'host' => $host,
                'user_count' => count($users),
            ]);

            return $users;

        } catch (Exception $e) {
            Log::error('Failed to list users', [
                'host' => $host,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Test user access on remote server
     */
    public function testUser(string $host, string $username): array
    {
        try {
            $tests = [];

            // Test if user exists
            $tests['user_exists'] = $this->userExists($host, $username);

            if ($tests['user_exists']) {
                // Test user's home directory
                $result = $this->remoteExecution->executeAsRoot($host, "test -d ~{$username}");
                $tests['home_directory'] = $result['success'];

                // Test user's shell
                $result = $this->remoteExecution->executeAsRoot($host, "getent passwd {$username} | cut -d: -f7");
                $tests['shell'] = $result['success'] ? trim($result['output']) : 'unknown';

                // Test user groups
                $result = $this->remoteExecution->executeAsRoot($host, "groups {$username}");
                $tests['groups'] = $result['success'] ? trim($result['output']) : 'unknown';

                // Test if user can login (check if shell is valid)
                $tests['can_login'] = ! in_array($tests['shell'], ['/usr/sbin/nologin', '/bin/false', '/sbin/nologin']);
            }

            return $tests;

        } catch (Exception $e) {
            Log::error('Failed to test user', [
                'host' => $host,
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Change user password on remote server
     */
    public function changeUserPassword(string $host, string $username, string $password): bool
    {
        try {
            Log::info('Changing user password', [
                'host' => $host,
                'username' => $username,
            ]);

            // Test root access
            if (! $this->remoteExecution->testRootAccess($host)) {
                throw new Exception('Cannot establish root access to host');
            }

            // Check if user exists
            if (! $this->userExists($host, $username)) {
                throw new Exception('User does not exist');
            }

            // Use chpasswd to set password
            $command = "echo '{$username}:{$password}' | chpasswd";
            $result = $this->remoteExecution->executeAsRoot($host, $command);

            if ($result['success']) {
                Log::info('User password changed successfully', [
                    'host' => $host,
                    'username' => $username,
                ]);

                return true;
            } else {
                throw new Exception('Failed to change password: '.$result['output']);
            }

        } catch (Exception $e) {
            Log::error('Failed to change user password', [
                'host' => $host,
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Set user permissions based on VHost configuration
     */
    public function setUserPermissions(string $host, string $shost, string $vhost, string $username): bool
    {
        try {
            // Load VHost configuration
            $config = $this->vhostConfig->loadVhostConfig($shost, $vhost);

            Log::info('Setting user permissions', [
                'host' => $host,
                'shost' => $shost,
                'vhost' => $vhost,
                'username' => $username,
            ]);

            if (! $this->validateVhostConfig($config)) {
                throw new Exception('VHost configuration is missing required fields');
            }

            $commands = [];

            // Create user directories if they don't exist
            if (isset($config['UPATH'])) {
                $userDir = $config['UPATH'].'/home/'.$username;
                $commands[] = "mkdir -p {$userDir}";
                $commands[] = "chown {$username}:{$config['WUGID']} {$userDir}";
                $commands[] = "chmod 755 {$userDir}";
            }

            // Set up web directory if user should have web access
            if (isset($config['WPATH'])) {
                $webDir = $config['WPATH'].'/'.$username;
                $commands[] = "mkdir -p {$webDir}";
                $commands[] = "chown {$username}:{$config['WUGID']} {$webDir}";
                $commands[] = "chmod 755 {$webDir}";
            }

            if (empty($commands)) {
                Log::info('No permission commands needed', ['username' => $username]);

                return true;
            }

            $result = $this->remoteExecution->executeSequence($host, $commands, true);

            if ($result['success']) {
                Log::info('User permissions set successfully', [
                    'host' => $host,
                    'username' => $username,
                ]);

                return true;
            } else {
                throw new Exception('Failed to set permissions: '.$this->formatCommandResults($result['results']));
            }

        } catch (Exception $e) {
            Log::error('Failed to set user permissions', [
                'host' => $host,
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    // Protected helper methods

    protected function validateVhostConfig(array $config): bool
    {
        $requiredFields = ['UUSER', 'U_UID', 'U_GID', 'WUGID'];

        foreach ($requiredFields as $field) {
            if (! isset($config[$field]) || empty($config[$field])) {
                Log::error('VHost config missing required field', ['field' => $field]);

                return false;
            }
        }

        return true;
    }

    protected function userExists(string $host, string $username): bool
    {
        $result = $this->remoteExecution->executeAsRoot($host, "id {$username} >/dev/null 2>&1");

        return $result['success'];
    }

    protected function createSystemUser(string $host, string $username, array $config): bool
    {
        // Generate a random password
        $password = $this->generatePassword();

        // Build user creation command
        $commands = [
            // Create user with specific UID/GID
            "useradd -m -u {$config['U_UID']} -g {$config['U_GID']} -s {$config['U_SHL']} {$username}",

            // Set password
            "echo '{$username}:{$password}' | chpasswd",
        ];

        // Add user to web group if needed
        if (isset($config['WUGID']) && $config['WUGID'] !== $config['U_GID']) {
            $commands[] = "usermod -a -G {$config['WUGID']} {$username}";
        }

        $result = $this->remoteExecution->executeSequence($host, $commands, true);

        if ($result['success']) {
            // Set up user permissions
            $this->setUserPermissions($host, $host, basename($config['VHOST'] ?? ''), $username);

            Log::info('System user created successfully', [
                'host' => $host,
                'username' => $username,
                'uid' => $config['U_UID'],
                'gid' => $config['U_GID'],
            ]);

            return true;
        } else {
            throw new Exception('Failed to create user: '.$this->formatCommandResults($result['results']));
        }
    }

    protected function generatePassword(int $length = 12): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $password;
    }

    protected function formatCommandResults(array $results): string
    {
        $errors = [];

        foreach ($results as $index => $result) {
            if (! $result['success']) {
                $errors[] = "Command {$index}: ".trim($result['output']);
            }
        }

        return implode('; ', $errors);
    }

    /**
     * Get user information from remote server
     */
    public function getUserInfo(string $host, string $username): ?array
    {
        try {
            $result = $this->remoteExecution->executeAsRoot($host, "getent passwd {$username}");

            if (! $result['success']) {
                return null;
            }

            $parts = explode(':', trim($result['output']));

            if (count($parts) >= 7) {
                return [
                    'username' => $parts[0],
                    'uid' => $parts[2],
                    'gid' => $parts[3],
                    'name' => $parts[4],
                    'home' => $parts[5],
                    'shell' => $parts[6],
                ];
            }

            return null;

        } catch (Exception $e) {
            Log::error('Failed to get user info', [
                'host' => $host,
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Add user to group on remote server
     */
    public function addUserToGroup(string $host, string $username, string $group): bool
    {
        try {
            Log::info('Adding user to group', [
                'host' => $host,
                'username' => $username,
                'group' => $group,
            ]);

            if (! $this->userExists($host, $username)) {
                throw new Exception('User does not exist');
            }

            $result = $this->remoteExecution->executeAsRoot($host, "usermod -a -G {$group} {$username}");

            if ($result['success']) {
                Log::info('User added to group successfully', [
                    'host' => $host,
                    'username' => $username,
                    'group' => $group,
                ]);

                return true;
            } else {
                throw new Exception('Failed to add user to group: '.$result['output']);
            }

        } catch (Exception $e) {
            Log::error('Failed to add user to group', [
                'host' => $host,
                'username' => $username,
                'group' => $group,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
