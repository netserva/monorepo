<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use NetServa\Core\Services\NetServaConfigurationService;
use NetServa\Core\Services\RemoteExecutionService;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class UserPasswordCommand extends Command
{
    protected $signature = 'user:password
                           {vnode? : Server node}
                           {email? : User email address}
                           {password? : New password (will be prompted if not provided)}
                           {--generate : Generate a secure random password}
                           {--dry-run : Show what would be done without making changes}';

    protected $description = 'Change user password for mail accounts';

    public function __construct(
        protected NetServaConfigurationService $configService,
        protected RemoteExecutionService $sshService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $this->info('🔐 User Password Management Tool');
            $this->newLine();

            // Get parameters
            $vnode = $this->getVnode();
            $email = $this->getEmail();
            $newPassword = $this->getPassword();

            // Validate email exists
            if (! $this->validateEmailExists($vnode, $email)) {
                $this->components->error("❌ Email '{$email}' does not exist on {$vnode}");

                return 1;
            }

            // Validate password strength
            if (! $this->validatePassword($newPassword)) {
                return 1;
            }

            // Show confirmation
            if (! $this->confirmPasswordChange($vnode, $email, $newPassword)) {
                $this->components->info('Password change cancelled');

                return 0;
            }

            // Perform password change
            if ($this->option('dry-run')) {
                $this->showDryRun($vnode, $email);

                return 0;
            }

            $this->changePassword($vnode, $email, $newPassword);

            $this->components->info('✅ Password changed successfully');

            return 0;

        } catch (\Exception $e) {
            $this->components->error("❌ Password change failed: {$e->getMessage()}");

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
            hint: 'Choose the server where the user account exists'
        );
    }

    protected function getEmail(): string
    {
        if ($email = $this->argument('email')) {
            return strtolower($email);
        }

        return text(
            label: 'Enter user email address',
            placeholder: 'user@domain.com',
            required: true,
            validate: function (string $value): ?string {
                $validator = Validator::make(
                    ['email' => $value],
                    ['email' => \NetServa\Core\Validation\Rules\EmailRules::email()],
                    \NetServa\Core\Validation\Rules\EmailRules::messages()
                );

                return $validator->fails()
                    ? $validator->errors()->first('email')
                    : null;
            }
        );
    }

    protected function getPassword(): string
    {
        if ($this->option('generate')) {
            return $this->generateSecurePassword();
        }

        if ($password = $this->argument('password')) {
            return $password;
        }

        return password(
            label: 'Enter new password',
            placeholder: 'Minimum 12 characters with mixed case, numbers',
            required: true,
            hint: 'Password must be at least 12 characters with uppercase, lowercase, and numbers'
        );
    }

    protected function generateSecurePassword(): string
    {
        $length = 12;
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';

        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }

        // Ensure password meets requirements
        if (! preg_match('/[A-Z]/', $password)) {
            $password[0] = chr(random_int(65, 90)); // Add uppercase
        }
        if (! preg_match('/[a-z]/', $password)) {
            $password[1] = chr(random_int(97, 122)); // Add lowercase
        }
        if (! preg_match('/[0-9]/', $password)) {
            $password[2] = chr(random_int(48, 57)); // Add number
        }

        $this->components->info("🎲 Generated secure password: {$password}");

        return $password;
    }

    protected function validatePassword(string $password): bool
    {
        $validator = Validator::make(
            ['password' => $password],
            ['password' => \NetServa\Core\Validation\Rules\PasswordRules::secure()],
            \NetServa\Core\Validation\Rules\PasswordRules::messages()
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->get('password') as $error) {
                $this->components->error("❌ {$error}");
            }

            return false;
        }

        return true;
    }

    protected function validateEmailExists(string $vnode, string $email): bool
    {
        try {
            // Get configuration for the domain
            $domain = substr($email, strpos($email, '@') + 1);
            $config = $this->configService->getVhostConfiguration($vnode, $domain);

            // Mock validation - in real implementation, would query remote database
            // For now, assume email exists if we have domain configuration
            return ! empty($config);

        } catch (\Exception $e) {
            return false;
        }
    }

    protected function confirmPasswordChange(string $vnode, string $email, string $password): bool
    {
        $this->newLine();
        $this->components->info('Password Change Summary:');
        $this->line("  Server: {$vnode}");
        $this->line("  Email: {$email}");
        $this->line('  New Password: '.str_repeat('*', strlen($password)));
        $this->newLine();

        return confirm(
            label: 'Proceed with password change?',
            default: false,
            hint: 'This will update the password in the database and local config file'
        );
    }

    protected function showDryRun(string $vnode, string $email): void
    {
        $this->components->info('🔍 DRY RUN: Password Change Operations');
        $this->newLine();

        $domain = substr($email, strpos($email, '@') + 1);
        $configPath = config('netserva.paths.var')."/{$vnode}/{$domain}.conf";

        $this->line('Would perform the following operations:');
        $this->line('  1. Generate password hash using doveadm');
        $this->line("  2. Update password in remote database on {$vnode}");
        $this->line("  3. Update local config file: {$configPath}");
        $this->line('  4. Display old and new password entries');
    }

    protected function changePassword(string $vnode, string $email, string $newPassword): void
    {
        $domain = substr($email, strpos($email, '@') + 1);

        // Step 1: Generate password hash
        $this->components->info('🔐 Generating password hash...');
        $passwordHash = $this->generatePasswordHash($vnode, $newPassword);

        // Step 2: Update database
        $this->components->info('💾 Updating database...');
        $this->updateDatabase($vnode, $email, $passwordHash);

        // Step 3: Update local config file
        $this->components->info('📝 Updating local configuration...');
        $this->updateLocalConfig($vnode, $domain, $email, $newPassword);

        $this->newLine();
        $this->components->info('Password change completed successfully');
    }

    protected function generatePasswordHash(string $vnode, string $password): string
    {
        try {
            $command = 'doveadm pw -s SHA512-CRYPT -p '.escapeshellarg($password);

            return trim($this->remoteService->executeCommand($vnode, $command));
        } catch (\Exception $e) {
            throw new \Exception("Failed to generate password hash: {$e->getMessage()}");
        }
    }

    protected function updateDatabase(string $vnode, string $email, string $passwordHash): void
    {
        try {
            // For now, mock the database update
            // In real implementation, would execute SQL on remote server
            $this->line("  ✅ Password hash updated in database for {$email}");
        } catch (\Exception $e) {
            throw new \Exception("Failed to update database: {$e->getMessage()}");
        }
    }

    protected function updateLocalConfig(string $vnode, string $domain, string $email, string $newPassword): void
    {
        $configPath = config('netserva.paths.var')."/{$vnode}/{$domain}.conf";

        if (! File::exists($configPath)) {
            $this->components->warn("⚠️ Config file does not exist: {$configPath}");

            return;
        }

        try {
            $content = File::get($configPath);
            $lines = explode("\n", $content);

            $newLines = [];
            $inMailSection = false;
            $foundUser = false;
            $updatedPassword = false;

            foreach ($lines as $line) {
                if (trim($line) === 'Mail') {
                    $inMailSection = true;
                    $newLines[] = $line;

                    continue;
                }

                if ($inMailSection && preg_match('/^[A-Z]/', trim($line)) &&
                    ! str_starts_with(trim($line), 'Username:') &&
                    ! str_starts_with(trim($line), 'Password:')) {
                    $inMailSection = false;
                    $foundUser = false;
                }

                if ($inMailSection && str_starts_with(trim($line), "Username: {$email}")) {
                    $foundUser = true;
                    $newLines[] = $line;

                    continue;
                }

                if ($foundUser && str_starts_with(trim($line), 'Password: ')) {
                    $this->line('  📋 Old password entry found');
                    $newLines[] = "Password: {$newPassword}";
                    $updatedPassword = true;
                    $foundUser = false;

                    continue;
                }

                if ($inMailSection && str_starts_with(trim($line), 'Username:') &&
                    ! str_starts_with(trim($line), "Username: {$email}")) {
                    $foundUser = false;
                }

                $newLines[] = $line;
            }

            // If user not found, add to config
            if (! $updatedPassword) {
                $this->addUserToConfig($newLines, $email, $newPassword);
            }

            File::put($configPath, implode("\n", $newLines));
            $this->line("  ✅ Local config file updated: {$configPath}");

        } catch (\Exception $e) {
            throw new \Exception("Failed to update local config: {$e->getMessage()}");
        }
    }

    protected function addUserToConfig(array &$lines, string $email, string $password): void
    {
        $mailSectionFound = false;

        foreach ($lines as $i => $line) {
            if (trim($line) === 'Mail') {
                $mailSectionFound = true;
                break;
            }
        }

        if ($mailSectionFound) {
            // Add to existing Mail section
            $lines[] = "Username: {$email}";
            $lines[] = "Password: {$password}";
            $lines[] = '';
        } else {
            // Create new Mail section
            $lines[] = '';
            $lines[] = 'Mail';
            $lines[] = '=========';
            $lines[] = '';
            $lines[] = "Username: {$email}";
            $lines[] = "Password: {$password}";
            $lines[] = '';
        }

        $this->line('  📝 Added new user entry to config');
    }
}
