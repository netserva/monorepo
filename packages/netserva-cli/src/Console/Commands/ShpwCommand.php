<?php

namespace NetServa\Cli\Console\Commands;

use NetServa\Cli\Services\VhostConfigService;

/**
 * Show Password Command
 *
 * Follows NetServa CRUD pattern: shpw (show passwords)
 * Usage: shpw admin@motd.com [--shost=motd]
 * With context: export VNODE=motd; shpw admin@motd.com
 */
class ShpwCommand extends BaseNetServaCommand
{
    protected $signature = 'shpw {vhost : Domain name or email to show passwords for}
                           {--shost= : SSH host identifier}
                           {--type= : Specific password type (user|database|email|web|admin|wordpress)}
                           {--all : Show all passwords}
                           {--masked : Show passwords masked with asterisks}
                           {--dry-run : Show what would be done}';

    protected $description = 'Show passwords for virtual host (NetServa CRUD pattern)';

    protected VhostConfigService $vhostConfig;

    public function __construct(VhostConfigService $vhostConfig)
    {
        parent::__construct();
        $this->vhostConfig = $vhostConfig;
    }

    public function handle(): int
    {
        return $this->executeWithContext(function () {
            // Get parameters - handle email format (admin@motd.com -> motd.com)
            $input = $this->argument('vhost');
            $VHOST = $this->parseVhostFromInput($input);
            $VNODE = $this->requireShost();

            // Check if VHost exists
            if (! $this->vhostConfig->exists("{$VNODE}/{$VHOST}")) {
                $this->error("❌ VHost {$VHOST} not found on {$VNODE}");
                $this->line("   Use 'shvhost --list --shost={$VNODE}' to see available vhosts");

                return 1;
            }

            try {
                // Load VHost configuration
                $config = $this->vhostConfig->loadVhostConfig($VNODE, $VHOST);

                $this->line("🔑 Passwords for VHost: <fg=yellow>{$VHOST}</> on server <fg=cyan>{$VNODE}</>");
                $this->line('');

                // Show specific password type or all
                if ($type = $this->option('type')) {
                    $this->showSpecificPassword($config, $type);
                } else {
                    $this->showAllPasswords($config);
                }

                // Security warning
                $this->line('');
                $this->line('<fg=red>⚠️  Security Warning:</> Store these passwords securely!');
                $this->line('<fg=gray>💡 Use --masked to show asterisks instead of actual passwords</>');

                return 0;

            } catch (\Exception $e) {
                $this->error('❌ Failed to load passwords: '.$e->getMessage());

                return 1;
            }
        });
    }

    protected function parseVhostFromInput(string $input): string
    {
        // Handle email format: admin@motd.com -> motd.com
        if (str_contains($input, '@')) {
            $parts = explode('@', $input);

            return end($parts); // Get domain part
        }

        return $input; // Regular domain
    }

    protected function showSpecificPassword(array $config, string $type): void
    {
        $passwordKey = $this->getPasswordKey($type);

        if (! $passwordKey) {
            $this->error("❌ Invalid password type: {$type}");
            $this->line('   Valid types: user, database, email, web, admin, wordpress');

            return;
        }

        if (! isset($config[$passwordKey])) {
            $this->error("❌ {$type} password not found in configuration");

            return;
        }

        $password = $config[$passwordKey];
        $displayPassword = $this->option('masked') ? str_repeat('*', strlen($password)) : $password;

        $this->line("<fg=blue>🔑 {$type} password:</> <fg=yellow>{$displayPassword}</>");
    }

    protected function showAllPasswords(array $config): void
    {
        $passwords = [
            'Admin' => $config['APASS'] ?? 'N/A',
            'User' => $config['UPASS'] ?? 'N/A',
            'Database' => $config['DPASS'] ?? 'N/A',
            'Email' => $config['EPASS'] ?? 'N/A',
            'Web' => $config['WPASS'] ?? 'N/A',
            'WordPress User' => $config['WPUSR'] ?? 'N/A',
        ];

        $masked = $this->option('masked');

        foreach ($passwords as $type => $password) {
            if ($password === 'N/A') {
                $this->line("   <fg=gray>{$type}:</> <fg=gray>Not set</>");

                continue;
            }

            $displayPassword = $masked ? str_repeat('*', strlen($password)) : $password;
            $this->line("   <fg=blue>{$type}:</> <fg=yellow>{$displayPassword}</>");
        }

        // Show additional info
        $this->line('');
        $this->line('<fg=blue>📋 Related Information:</>');
        $this->line('   Username: <fg=yellow>'.($config['UUSER'] ?? 'N/A').'</>');
        $this->line('   Database: <fg=yellow>'.($config['DNAME'] ?? 'N/A').'</>');
        $this->line('   DB User: <fg=yellow>'.($config['DUSER'] ?? 'N/A').'</>');
    }

    protected function getPasswordKey(string $type): ?string
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
}
