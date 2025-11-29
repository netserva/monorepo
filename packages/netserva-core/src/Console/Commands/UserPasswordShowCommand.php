<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use NetServa\Core\Services\NetServaConfigurationService;
use NetServa\Core\Services\RemoteExecutionService;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class UserPasswordShowCommand extends Command
{
    protected $signature = 'user:password:show
                           {vnode? : Server node}
                           {email? : User email address}
                           {--mask : Mask the password output for security}
                           {--copy : Copy password to clipboard (if available)}';

    protected $description = 'Display user password from local configuration file';

    public function __construct(
        protected NetServaConfigurationService $configService,
        protected RemoteExecutionService $sshService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $this->info('🔑 User Password Display Tool');
            $this->newLine();

            // Get parameters
            $vnode = $this->getVnode();
            $email = $this->getEmail();

            // Retrieve password
            $password = $this->getPasswordFromConfig($vnode, $email);

            if (! $password) {
                $this->components->error("❌ Password for '{$email}' not found");

                return 1;
            }

            // Display password
            $this->displayPassword($email, $password);

            return 0;

        } catch (\Exception $e) {
            $this->components->error("❌ Password retrieval failed: {$e->getMessage()}");

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
            hint: 'Choose the server where the user configuration exists'
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
            validate: fn (string $value) => match (true) {
                empty($value) => 'Email address is required',
                ! filter_var($value, FILTER_VALIDATE_EMAIL) => 'Invalid email format',
                default => null
            }
        );
    }

    protected function getPasswordFromConfig(string $vnode, string $email): ?string
    {
        $domain = substr($email, strpos($email, '@') + 1);
        $configPath = config('netserva.paths.var')."/{$vnode}/{$domain}.conf";

        if (! File::exists($configPath)) {
            throw new \Exception("Config file not found: {$configPath}");
        }

        return $this->extractPasswordFromConfig($configPath, $email);
    }

    protected function extractPasswordFromConfig(string $configPath, string $email): ?string
    {
        $content = File::get($configPath);
        $lines = explode("\n", $content);

        $inMailSection = false;
        $foundUser = false;

        foreach ($lines as $line) {
            $line = trim($line);

            // Check for Mail section start
            if ($line === 'Mail') {
                $inMailSection = true;

                continue;
            }

            // Check for section end (new section starting with capital letter)
            if ($inMailSection && preg_match('/^[A-Z]/', $line) &&
                ! str_starts_with($line, 'Username:') &&
                ! str_starts_with($line, 'Password:')) {
                $inMailSection = false;
                $foundUser = false;

                continue;
            }

            // Look for our user
            if ($inMailSection && str_starts_with($line, "Username: {$email}")) {
                $foundUser = true;

                continue;
            }

            // Extract password for found user
            if ($foundUser && str_starts_with($line, 'Password: ')) {
                return substr($line, 10); // Remove "Password: " prefix
            }

            // Reset if we encounter a different username
            if ($inMailSection && str_starts_with($line, 'Username:') &&
                ! str_starts_with($line, "Username: {$email}")) {
                $foundUser = false;
            }
        }

        return null;
    }

    protected function displayPassword(string $email, string $password): void
    {
        $this->newLine();
        $this->components->info("🔑 Password for {$email}:");

        if ($this->option('mask')) {
            $maskedPassword = $this->maskPassword($password);
            $this->line("   {$maskedPassword}");
            $this->newLine();
            $this->components->warn('Password is masked for security. Use --no-mask to see full password.');
        } else {
            $this->line("   {$password}");
        }

        // Additional security information
        $this->newLine();
        $this->displayPasswordAnalysis($password);

        // Copy to clipboard if requested and available
        if ($this->option('copy') && $this->isClipboardAvailable()) {
            $this->copyToClipboard($password);
            $this->components->info('📋 Password copied to clipboard');
        }
    }

    protected function maskPassword(string $password): string
    {
        $length = strlen($password);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        $visible = 2; // Show first 2 and last 2 characters
        $masked = $length - ($visible * 2);

        return substr($password, 0, $visible).
               str_repeat('*', $masked).
               substr($password, -$visible);
    }

    protected function displayPasswordAnalysis(string $password): void
    {
        $length = strlen($password);
        $hasUpper = preg_match('/[A-Z]/', $password);
        $hasLower = preg_match('/[a-z]/', $password);
        $hasNumber = preg_match('/[0-9]/', $password);
        $hasSpecial = preg_match('/[^A-Za-z0-9]/', $password);

        $this->components->info('📊 Password Analysis:');
        $this->line("   Length: {$length} characters ".($length >= 12 ? '✅' : '❌'));
        $this->line('   Uppercase: '.($hasUpper ? '✅' : '❌'));
        $this->line('   Lowercase: '.($hasLower ? '✅' : '❌'));
        $this->line('   Numbers: '.($hasNumber ? '✅' : '❌'));
        $this->line('   Special chars: '.($hasSpecial ? '✅' : '❌'));

        $strength = $this->calculatePasswordStrength($password);
        $strengthLabel = match ($strength) {
            'weak' => '🔴 Weak',
            'medium' => '🟡 Medium',
            'strong' => '🟢 Strong',
            'very_strong' => '🟢 Very Strong',
        };

        $this->line("   Strength: {$strengthLabel}");

        if ($strength === 'weak') {
            $this->newLine();
            $this->components->warn('⚠️ Consider updating to a stronger password using user:password command');
        }
    }

    protected function calculatePasswordStrength(string $password): string
    {
        $length = strlen($password);
        $score = 0;

        // Length scoring
        if ($length >= 8) {
            $score += 1;
        }
        if ($length >= 12) {
            $score += 1;
        }
        if ($length >= 16) {
            $score += 1;
        }

        // Character variety scoring
        if (preg_match('/[A-Z]/', $password)) {
            $score += 1;
        }
        if (preg_match('/[a-z]/', $password)) {
            $score += 1;
        }
        if (preg_match('/[0-9]/', $password)) {
            $score += 1;
        }
        if (preg_match('/[^A-Za-z0-9]/', $password)) {
            $score += 1;
        }

        return match (true) {
            $score <= 2 => 'weak',
            $score <= 4 => 'medium',
            $score <= 6 => 'strong',
            default => 'very_strong',
        };
    }

    protected function isClipboardAvailable(): bool
    {
        // Check for common clipboard utilities
        $clipboardCommands = ['xclip', 'pbcopy', 'clip'];

        foreach ($clipboardCommands as $command) {
            if (shell_exec("which {$command} 2>/dev/null")) {
                return true;
            }
        }

        return false;
    }

    protected function copyToClipboard(string $password): void
    {
        try {
            // Try different clipboard utilities
            if (shell_exec('which xclip 2>/dev/null')) {
                shell_exec('echo '.escapeshellarg($password).' | xclip -selection clipboard');
            } elseif (shell_exec('which pbcopy 2>/dev/null')) {
                shell_exec('echo '.escapeshellarg($password).' | pbcopy');
            } elseif (shell_exec('which clip 2>/dev/null')) {
                shell_exec('echo '.escapeshellarg($password).' | clip');
            }
        } catch (\Exception $e) {
            $this->components->warn("Could not copy to clipboard: {$e->getMessage()}");
        }
    }
}
