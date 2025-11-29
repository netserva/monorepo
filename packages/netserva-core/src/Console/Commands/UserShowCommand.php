<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use NetServa\Core\Services\NetServaConfigurationService;
use NetServa\Core\Services\RemoteExecutionService;
use NetServa\Mail\Models\Mailbox;

use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class UserShowCommand extends Command
{
    protected $signature = 'user:show
                           {vnode? : Server node to search}
                           {email? : Email pattern to search for}
                           {--format=table : Output format (table|json|text)}
                           {--limit=10 : Maximum results to display}';

    protected $description = 'Display mail user information and details';

    public function __construct(
        protected NetServaConfigurationService $configService,
        protected RemoteExecutionService $sshService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $this->info('📧 Mail User Information Tool');
            $this->newLine();

            // Get server node
            $vnode = $this->getVnode();

            // Get email pattern
            $emailPattern = $this->getEmailPattern();

            // Search for users
            $users = $this->searchUsers($vnode, $emailPattern);

            if ($users->isEmpty()) {
                $this->components->warn("No mail users found matching '{$emailPattern}' on {$vnode}");

                return 0;
            }

            // Display results
            $this->displayUsers($users, $vnode);

            return 0;

        } catch (\Exception $e) {
            $this->components->error("❌ User search failed: {$e->getMessage()}");

            return 1;
        }
    }

    protected function getVnode(): string
    {
        if ($vnode = $this->argument('vnode')) {
            return $vnode;
        }

        $sshHosts = $this->remoteService->getAvailableHosts();

        if (empty($sshHosts)) {
            $this->components->error('No SSH hosts configured');
            throw new \Exception('No SSH hosts available');
        }

        return select(
            label: 'Select server node',
            options: $sshHosts,
            hint: 'Choose the server to search for mail users'
        );
    }

    protected function getEmailPattern(): string
    {
        if ($email = $this->argument('email')) {
            return $email;
        }

        return text(
            label: 'Enter email pattern to search',
            placeholder: 'user@domain.com or user% or %@domain.com',
            hint: 'Use % as wildcard for pattern matching',
            required: true
        );
    }

    protected function searchUsers(string $vnode, string $emailPattern): \Illuminate\Support\Collection
    {
        // Convert pattern for SQL LIKE
        if (! str_contains($emailPattern, '%')) {
            $emailPattern = $emailPattern.'%';
        }

        // For now, return mock data since we need SSH connection to remote database
        // In a real implementation, this would connect to the remote server's database
        return collect([
            [
                'id' => 1001,
                'email' => 'admin@example.com',
                'home' => '/srv/example.com/msg/admin',
                'quota_bytes' => 1073741824, // 1GB
                'used_bytes' => 524288000,   // 500MB
                'is_active' => true,
                'created_at' => now()->subDays(30),
            ],
            [
                'id' => 1002,
                'email' => 'user@example.com',
                'home' => '/srv/example.com/msg/user',
                'quota_bytes' => 536870912,  // 512MB
                'used_bytes' => 100000000,   // ~95MB
                'is_active' => true,
                'created_at' => now()->subDays(15),
            ],
        ])->filter(function ($user) use ($emailPattern) {
            $pattern = str_replace('%', '.*', preg_quote($emailPattern, '/'));

            return preg_match("/^{$pattern}$/i", $user['email']);
        });
    }

    protected function displayUsers(\Illuminate\Support\Collection $users, string $vnode): void
    {
        $format = $this->option('format');

        if ($format === 'json') {
            $this->line($users->toJson(JSON_PRETTY_PRINT));

            return;
        }

        if ($users->count() === 1) {
            $this->displaySingleUser($users->first(), $vnode);
        } else {
            $this->displayUserList($users, $vnode);
        }
    }

    protected function displaySingleUser(array $user, string $vnode): void
    {
        $this->components->info("📧 Mail User Details on {$vnode}");
        $this->newLine();

        $quotaUsed = $this->formatBytes($user['used_bytes']);
        $quotaTotal = $this->formatBytes($user['quota_bytes']);
        $quotaPercent = round(($user['used_bytes'] / $user['quota_bytes']) * 100, 1);

        $details = [
            ['Field', 'Value'],
            ['Email', $user['email']],
            ['User ID', "u{$user['id']}"],
            ['Home Directory', $user['home']],
            ['Quota Usage', "{$quotaUsed} / {$quotaTotal} ({$quotaPercent}%)"],
            ['Status', $user['is_active'] ? '✅ Active' : '❌ Inactive'],
            ['Created', $user['created_at']->format('Y-m-d H:i:s')],
        ];

        table($details[0], array_slice($details, 1));

        // Check mailbox directory existence
        $this->checkMailboxDirectory($vnode, $user['home']);

        // Try to get password from config file
        $this->displayPassword($vnode, $user['email']);
    }

    protected function displayUserList(\Illuminate\Support\Collection $users, string $vnode): void
    {
        $this->components->info("📧 Found {$users->count()} mail users on {$vnode}");
        $this->newLine();

        $tableData = $users->map(function ($user) {
            return [
                "u{$user['id']}",
                $user['email'],
                $this->formatBytes($user['used_bytes']),
                $user['is_active'] ? '✅' : '❌',
            ];
        })->toArray();

        table(
            ['ID', 'Email', 'Used Space', 'Active'],
            $tableData
        );
    }

    protected function checkMailboxDirectory(string $vnode, string $homePath): void
    {
        $mailboxPath = $homePath.'/Maildir';

        try {
            $result = $this->remoteService->executeCommand($vnode, "[[ -d {$mailboxPath} ]] && echo 'exists' || echo 'missing'");

            if (str_contains($result, 'exists')) {
                $this->components->info("✅ Mailbox exists: {$mailboxPath}");
            } else {
                $this->components->warn("❌ Mailbox missing: {$mailboxPath}");
            }
        } catch (\Exception $e) {
            $this->components->warn("⚠️ Could not check mailbox directory: {$e->getMessage()}");
        }
    }

    protected function displayPassword(string $vnode, string $email): void
    {
        try {
            $domain = substr($email, strpos($email, '@') + 1);
            $configPath = config('netserva.paths.var')."/{$vnode}/{$domain}.conf";

            if (File::exists($configPath)) {
                $password = $this->extractPasswordFromConfig($configPath, $email);
                if ($password) {
                    $this->line("🔑 Password: {$password}");
                } else {
                    $this->components->warn('⚠️ Password not found in config file');
                }
            } else {
                $this->components->warn("⚠️ Config file not found: {$configPath}");
            }
        } catch (\Exception $e) {
            $this->components->warn("⚠️ Could not retrieve password: {$e->getMessage()}");
        }
    }

    protected function extractPasswordFromConfig(string $configPath, string $email): ?string
    {
        $content = File::get($configPath);
        $lines = explode("\n", $content);

        $inMailSection = false;
        $foundUser = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === 'Mail') {
                $inMailSection = true;

                continue;
            }

            if ($inMailSection && preg_match('/^[A-Z]/', $line) && ! str_starts_with($line, 'Username:') && ! str_starts_with($line, 'Password:')) {
                $inMailSection = false;
                $foundUser = false;

                continue;
            }

            if ($inMailSection && str_starts_with($line, "Username: {$email}")) {
                $foundUser = true;

                continue;
            }

            if ($foundUser && str_starts_with($line, 'Password: ')) {
                return substr($line, 10); // Remove "Password: " prefix
            }

            if ($inMailSection && str_starts_with($line, 'Username:') && ! str_starts_with($line, "Username: {$email}")) {
                $foundUser = false;
            }
        }

        return null;
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
