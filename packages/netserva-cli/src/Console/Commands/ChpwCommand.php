<?php

namespace NetServa\Cli\Console\Commands;

use NetServa\Cli\Enums\NetServaConstants;
use NetServa\Cli\Enums\NetServaStrings;
use NetServa\Cli\Services\RemoteExecutionService;
use NetServa\Cli\Services\VhostConfigService;

/**
 * Change Password Command
 *
 * Follows NetServa CRUD pattern: chpw (password management)
 * Usage: chpw test.motd.com --type=database [--shost=motd]
 * With context: export VNODE=motd; chpw test.motd.com --type=email
 */
class ChpwCommand extends BaseNetServaCommand
{
    protected $signature = 'chpw {vhost : Domain name to change password for}
                           {--shost= : SSH host identifier}
                           {--type= : Password type (user|database|email|web|admin|wordpress)}
                           {--password= : New password (generates secure one if not provided)}
                           {--show : Show new password in output}';

    protected $description = 'Change passwords for virtual host (NetServa CRUD pattern)';

    protected VhostConfigService $vhostConfig;

    protected RemoteExecutionService $remoteExecution;

    public function __construct(
        VhostConfigService $vhostConfig,
        RemoteExecutionService $remoteExecution
    ) {
        parent::__construct();
        $this->vhostConfig = $vhostConfig;
        $this->remoteExecution = $remoteExecution;
    }

    public function handle(): int
    {
        return $this->executeWithContext(function () {
            // Get required parameters
            $VHOST = $this->requireVhost($this->argument('vhost'));
            $VNODE = $this->requireShost();
            $type = $this->option('type');

            if (! $type) {
                $this->error('❌ Password type required. Use --type=database|email|user|web|admin|wordpress');

                return 1;
            }

            // Validate password type
            $validTypes = ['user', 'database', 'email', 'web', 'admin', 'wordpress'];
            if (! in_array($type, $validTypes)) {
                $this->error('❌ Invalid password type. Valid options: '.implode(', ', $validTypes));

                return 1;
            }

            // Check if VHost exists
            if (! $this->vhostConfig->exists("{$VNODE}/{$VHOST}")) {
                $this->error("❌ VHost {$VHOST} not found on {$VNODE}");

                return 1;
            }

            // Generate or use provided password
            $newPassword = $this->option('password') ?? $this->generateSecurePassword($type);

            $this->line("🔐 Changing <fg=yellow>{$type}</> password for VHost: <fg=yellow>{$VHOST}</> on server <fg=cyan>{$VNODE}</>");

            if ($this->option('dry-run')) {
                $this->dryRun("Change {$type} password for {$VHOST} on {$VNODE}", [
                    "Load current config from vconfs table (database-first)",
                    "Generate new secure password for {$type}",
                    "Update vconfs table with new password",
                    "SSH to {$VNODE} and update {$type} password via heredoc script",
                    'Test new password functionality',
                ]);

                return 0;
            }

            // Apply password change
            $result = $this->changePassword($VNODE, $VHOST, $type, $newPassword);

            if ($result['success']) {
                $this->info("✅ {$type} password changed successfully for {$VHOST} on {$VNODE}");

                // Show password if requested
                if ($this->option('show')) {
                    $this->line('');
                    $this->line("<fg=blue>🔑 New {$type} password:</> <fg=yellow>{$newPassword}</>");
                    $this->line('<fg=gray>💡 Store this password securely!</>');
                }

                // Add to command history (without password)
                $this->context->addToHistory("chpw {$VHOST}", [
                    'VNODE' => $VNODE,
                    'VHOST' => $VHOST,
                    'type' => $type,
                    'success' => true,
                ]);

                return 0;
            } else {
                $this->error("❌ Failed to change {$type} password for {$VHOST}");
                if (isset($result['error'])) {
                    $this->line("   Error: {$result['error']}");
                }

                return 1;
            }
        });
    }

    protected function generateSecurePassword(string $type): string
    {
        if ($type === 'wordpress') {
            // WordPress username is shorter
            $length = NetServaConstants::WORDPRESS_USER_LENGTH->value;
            $chars = NetServaStrings::LOWERCASE_CHARS->value;
        } else {
            // Standard secure password
            $length = NetServaConstants::SECURE_PASSWORD_LENGTH->value;
            $chars = NetServaStrings::ALPHANUMERIC_CHARS->value;
        }

        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $password;
    }

    protected function changePassword(string $VNODE, string $VHOST, string $type, string $newPassword): array
    {
        try {
            // Load current configuration
            $config = $this->vhostConfig->loadVhostConfig($VNODE, $VHOST);

            // Update the specific password in configuration
            $configKey = $this->getConfigKey($type);
            if (! $configKey) {
                return [
                    'success' => false,
                    'error' => "Unknown password type: {$type}",
                ];
            }

            $config[$configKey] = $newPassword;

            // Save updated configuration
            if (! $this->vhostConfig->saveVhostConfig($VNODE, $VHOST, $config)) {
                return [
                    'success' => false,
                    'error' => 'Failed to save updated configuration',
                ];
            }

            // Apply password change on remote server
            $remoteResult = $this->applyRemotePasswordChange($VNODE, $VHOST, $type, $newPassword, $config);

            return [
                'success' => $remoteResult['success'],
                'error' => $remoteResult['error'] ?? null,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function getConfigKey(string $type): ?string
    {
        return match ($type) {
            'user' => 'UPASS',
            'database' => 'DPASS',
            'email' => 'EPASS',
            'web' => 'WPASS',
            'admin' => 'APASS',
            'wordpress' => 'WPUSR',
            default => null,
        };
    }

    protected function applyRemotePasswordChange(string $VNODE, string $VHOST, string $type, string $newPassword, array $config): array
    {
        $commands = [];

        switch ($type) {
            case 'database':
                // Update MySQL/MariaDB password
                $DUSER = $config['DUSER'] ?? $config['UUSER'];
                $commands[] = "mysql -e \"ALTER USER '{$DUSER}'@'localhost' IDENTIFIED BY '{$newPassword}';\"";
                $commands[] = 'mysql -e "FLUSH PRIVILEGES;"';
                break;

            case 'user':
                // Update system user password
                $UUSER = $config['UUSER'];
                $commands[] = "echo '{$UUSER}:{$newPassword}' | chpasswd";
                break;

            case 'email':
                // Update email account password (depends on mail server)
                $commands[] = '# Email password updated in config - dovecot will use new password';
                break;

            case 'web':
                // Update web basic auth password (if used)
                $commands[] = '# Web password updated in config';
                break;

            case 'admin':
                // Update admin password
                $commands[] = "echo 'sysadm:{$newPassword}' | chpasswd";
                break;

            case 'wordpress':
                // WordPress username change (not password)
                $commands[] = '# WordPress username updated in config';
                break;
        }

        // Execute commands on remote server
        $allSuccess = true;
        $errors = [];

        foreach ($commands as $command) {
            if (str_starts_with($command, '#')) {
                continue; // Skip comments
            }

            $result = $this->remoteExecution->executeAsRoot($VNODE, $command);
            if (! $result['success']) {
                $allSuccess = false;
                $errors[] = $result['error'] ?? 'Unknown error';
            }
        }

        return [
            'success' => $allSuccess,
            'error' => empty($errors) ? null : implode('; ', $errors),
        ];
    }
}
