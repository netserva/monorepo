<?php

namespace NetServa\Mail\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Laravel\Prompts\password;
use Laravel\Prompts\Progress;
use NetServa\Cli\Services\NetServaConfigurationService;
use NetServa\Cli\Services\RemoteExecutionService;

/**
 * Add Virtual Mail User Command
 *
 * Laravel Artisan port of the bash addvmail command.
 * Creates virtual mail users on remote servers with Maildir and database entries.
 */
class AddVmailCommand extends Command
{
    protected $signature = 'mail:adduser
                           {vnode : SSH host/server identifier}
                           {email : Email address (user@domain)}
                           {--password= : Custom password (auto-generated if not provided)}
                           {--force : Skip confirmation prompts}
                           {--dry-run : Show what would be done without executing}';

    protected $description = 'Add virtual mail user to remote server';

    protected NetServaConfigurationService $configService;

    protected RemoteExecutionService $remoteExecution;

    public function __construct(
        NetServaConfigurationService $configService,
        RemoteExecutionService $remoteExecution
    ) {
        parent::__construct();
        $this->configService = $configService;
        $this->remoteExecution = $remoteExecution;
    }

    public function handle(): int
    {
        $vnode = $this->argument('vnode');
        $email = strtolower($this->argument('email'));
        $customPassword = $this->option('password');
        $isDryRun = $this->option('dry-run');

        // Validate email format
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("❌ Invalid email format: {$email}");

            return self::FAILURE;
        }

        $vhost = substr($email, strpos($email, '@') + 1);
        $vuser = substr($email, 0, strpos($email, '@'));

        if ($isDryRun) {
            $this->info("🔍 DRY RUN - Would add mail user: {$email} on {$vnode}");
        }

        try {
            return $this->addVirtualMailUser($vnode, $vhost, $email, $vuser, $customPassword, $isDryRun);
        } catch (Exception $e) {
            $this->error('❌ Failed to add mail user: '.$e->getMessage());
            Log::error('AddVmail command failed', [
                'vnode' => $vnode,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }

    protected function addVirtualMailUser(
        string $vnode,
        string $vhost,
        string $email,
        string $vuser,
        ?string $customPassword,
        bool $isDryRun
    ): int {
        // Load vhost configuration
        $config = $this->configService->load("{$vnode}/{$vhost}");
        if (empty($config)) {
            $this->error("❌ Configuration not found: {$vnode}/{$vhost}");
            $this->info("💡 Run 'php artisan platform:addvhost {$vnode} {$vhost}' first");

            return self::FAILURE;
        }

        // Generate VHost configuration for paths and database settings
        $vhostConfig = $this->configService->generateVhostConfig($vnode, $vhost);

        // Generate or use provided password
        $password = $customPassword ?? $this->generateSecurePassword();

        if (! $this->option('force')) {
            $this->displayMailUserSummary($email, $vnode, $vhost, $vuser, $password);

            if (! confirm('Create this mail user?', true)) {
                $this->info('⚠️ Operation cancelled');

                return self::SUCCESS;
            }
        }

        if ($isDryRun) {
            $this->showDryRunOperations($email, $vnode, $vhost, $vuser, $vhostConfig);

            return self::SUCCESS;
        }

        return $this->executeMailUserCreation($email, $vnode, $vhost, $vuser, $password, $vhostConfig);
    }

    protected function displayMailUserSummary(
        string $email,
        string $vnode,
        string $vhost,
        string $vuser,
        string $password
    ): void {
        $this->info('📧 Mail User Creation Summary');
        $this->table(['Property', 'Value'], [
            ['Email', $email],
            ['Server', $vnode],
            ['Domain', $vhost],
            ['Username', $vuser],
            ['Password', str_repeat('*', strlen($password))],
        ]);
    }

    protected function showDryRunOperations(
        string $email,
        string $vnode,
        string $vhost,
        string $vuser,
        $vhostConfig
    ): void {
        $this->info('🔍 DRY RUN - Operations that would be performed:');

        $operations = [
            "Validate SSH connection to {$vnode}",
            'Check if vhost exists in database',
            "Check if mail user {$email} already exists",
            'Generate password hash using doveadm',
            'Insert into vmails table',
            'Insert into vmail_log table',
            'Insert into valias table',
            "Create Maildir structure: {$vhostConfig->paths->mpath}/{$vuser}/Maildir",
            "Create sieve directory: {$vhostConfig->paths->mpath}/{$vuser}/sieve",
            'Setup SpamProbe directory if needed',
            'Set proper file permissions',
            'Update local configuration file',
        ];

        foreach ($operations as $operation) {
            $this->line("  • {$operation}");
        }
    }

    protected function executeMailUserCreation(
        string $email,
        string $vnode,
        string $vhost,
        string $vuser,
        string $password,
        $vhostConfig
    ): int {
        $progress = new Progress('Creating mail user...', 8);

        try {
            // Step 1: Validate SSH connection
            $progress->label('Validating SSH connection...');
            if (! $this->validateSshConnection($vnode)) {
                $progress->finish();

                return self::FAILURE;
            }
            $progress->advance();

            // Step 2: Check vhost exists
            $progress->label('Checking vhost in database...');
            $hid = $this->getVhostId($vnode, $vhost, $vhostConfig);
            if (! $hid) {
                $progress->finish();
                $this->error("❌ VHost {$vhost} does not exist in database");

                return self::FAILURE;
            }
            $progress->advance();

            // Step 3: Check if user already exists
            $progress->label('Checking existing mail user...');
            if ($this->mailUserExists($vnode, $email, $vhostConfig)) {
                $progress->finish();
                $this->warn("⚠️ Mail user {$email} already exists");

                return self::SUCCESS;
            }
            $progress->advance();

            // Step 4: Create database entries
            $progress->label('Creating database entries...');
            $this->createDatabaseEntries($vnode, $hid, $email, $vuser, $password, $vhostConfig);
            $progress->advance();

            // Step 5: Create Maildir structure
            $progress->label('Creating Maildir structure...');
            $this->createMaildirStructure($vnode, $vuser, $vhostConfig);
            $progress->advance();

            // Step 6: Setup SpamProbe
            $progress->label('Setting up SpamProbe...');
            $this->setupSpamProbe($vnode, $vuser, $vhostConfig);
            $progress->advance();

            // Step 7: Set permissions
            $progress->label('Setting file permissions...');
            $this->setMailboxPermissions($vnode, $vuser, $vhostConfig);
            $progress->advance();

            // Step 8: Update local config
            $progress->label('Updating local configuration...');
            $this->updateLocalConfiguration($vnode, $vhost, $email, $password);
            $progress->advance();

            $progress->finish();

            $this->info("✅ Mail user {$email} created successfully on {$vnode}");
            $this->info("📫 Maildir: {$vhostConfig->paths->mpath}/{$vuser}");

            return self::SUCCESS;

        } catch (Exception $e) {
            $progress->finish();
            throw $e;
        }
    }

    protected function validateSshConnection(string $vnode): bool
    {
        // Check SSH configuration exists
        $sshConfigPath = env('NSSSH', $_SERVER['HOME'].'/.ns/ssh')."/hosts/{$vnode}";

        if (! file_exists($sshConfigPath)) {
            $this->error("❌ SSH host '{$vnode}' not found in ~/.ns/ssh/hosts/");

            return false;
        }

        // Test SSH connection
        $result = $this->remoteExecution->executeAsRoot($vnode, 'echo "SSH_OK"');

        if (! $result['success'] || trim($result['output']) !== 'SSH_OK') {
            $this->error("❌ SSH connection to {$vnode} failed");

            return false;
        }

        return true;
    }

    protected function getVhostId(string $vnode, string $vhost, $vhostConfig): ?int
    {
        $result = $this->remoteExecution->executeAsRoot($vnode,
            "{$vhostConfig->paths->toArray()['mysql_path']} -e \"SELECT id FROM vhosts WHERE domain = '{$vhost}'\""
        );

        if (! $result['success'] || empty(trim($result['output']))) {
            return null;
        }

        return (int) trim($result['output']);
    }

    protected function mailUserExists(string $vnode, string $email, $vhostConfig): bool
    {
        $sqlPath = $vhostConfig->paths->toArray()['mysql_path'] ?? 'sqlite3';

        $result = $this->remoteExecution->executeAsRoot($vnode,
            "{$sqlPath} -e \"SELECT id FROM vmails WHERE user = '{$email}'\""
        );

        return $result['success'] && ! empty(trim($result['output']));
    }

    protected function createDatabaseEntries(
        string $vnode,
        int $hid,
        string $email,
        string $vuser,
        string $password,
        $vhostConfig
    ): void {
        $mpath = $vhostConfig->paths->mpath."/{$vuser}";
        $date = date('Y-m-d H:i:s');

        // Generate password hash
        $result = $this->remoteExecution->executeAsRoot($vnode, "doveadm pw -s SHA512-CRYPT -p '{$password}'");
        if (! $result['success']) {
            throw new Exception('Failed to generate password hash');
        }
        $passwordHash = trim($result['output']);

        // Insert into vmails table
        $vmailsQuery = "INSERT INTO vmails (hid, uid, gid, active, user, home, password, updated, created) VALUES ({$hid}, {$vhostConfig->U_UID}, {$vhostConfig->U_GID}, 1, '{$email}', '{$mpath}', '{$passwordHash}', '{$date}', '{$date}')";

        $result = $this->remoteExecution->executeAsRoot($vnode,
            "{$vhostConfig->paths->toArray()['mysql_path']} -e \"{$vmailsQuery}\""
        );

        if (! $result['success']) {
            throw new Exception('Failed to insert into vmails table');
        }

        // Get mail ID for logging
        $result = $this->remoteExecution->executeAsRoot($vnode,
            "{$vhostConfig->paths->toArray()['mysql_path']} -e \"SELECT id FROM vmails WHERE user = '{$email}'\""
        );

        if (! $result['success']) {
            throw new Exception('Failed to get mail ID');
        }

        $mid = (int) trim($result['output']);
        $ymd = date('Y-m-d');

        // Insert into vmail_log
        $logQuery = "INSERT INTO vmail_log (mid, ymd) VALUES ({$mid}, '{$ymd}')";
        $this->remoteExecution->executeAsRoot($vnode,
            "{$vhostConfig->paths->toArray()['mysql_path']} -e \"{$logQuery}\""
        );

        // Insert into valias
        $source = ($vuser === 'admin') ? "@{$vhostConfig->VHOST}" : $email;
        $valiasQuery = "INSERT INTO valias (hid, source, target, updated, created) VALUES ({$hid}, '{$source}', '{$email}', '{$date}', '{$date}')";

        $this->remoteExecution->executeAsRoot($vnode,
            "{$vhostConfig->paths->toArray()['mysql_path']} -e \"{$valiasQuery}\""
        );
    }

    protected function createMaildirStructure(string $vnode, string $vuser, $vhostConfig): void
    {
        $mpath = $vhostConfig->paths->mpath."/{$vuser}";

        // Check if Maildir already exists
        $result = $this->remoteExecution->executeAsRoot($vnode, "[[ -d {$mpath}/Maildir ]] && echo 'EXISTS'");

        if ($result['success'] && trim($result['output']) === 'EXISTS') {
            $this->warn("⚠️ {$mpath}/Maildir already exists");

            return;
        }

        // Create Maildir and sieve directories
        $this->remoteExecution->executeAsRoot($vnode, "mkdir -p {$mpath}/{Maildir,sieve}");
    }

    protected function setupSpamProbe(string $vnode, string $vuser, $vhostConfig): void
    {
        $mpath = $vhostConfig->paths->mpath."/{$vuser}";

        // Check if .spamprobe already exists
        $result = $this->remoteExecution->executeAsRoot($vnode, "[[ -d {$mpath}/.spamprobe ]] && echo 'EXISTS'");

        if ($result['success'] && trim($result['output']) === 'EXISTS') {
            $this->info("ℹ️ SpamProbe already configured for {$vuser}");

            return;
        }

        // Check if global spamprobe config exists
        $result = $this->remoteExecution->executeAsRoot($vnode, "[[ -d /etc/spamprobe ]] && echo 'EXISTS'");

        if (! $result['success'] || trim($result['output']) !== 'EXISTS') {
            $this->info('📥 Setting up global SpamProbe configuration...');
            $this->remoteExecution->executeAsRoot($vnode,
                'cd /etc && wget -q https://renta.net/public/_etc_spamprobe.tgz && tar xf _etc_spamprobe.tgz >/dev/null 2>&1'
            );
        }

        // Create user-specific spamprobe
        $this->remoteExecution->executeAsRoot($vnode, "mkdir {$mpath}/.spamprobe && cp -a /etc/spamprobe/* {$mpath}/.spamprobe");
    }

    protected function setMailboxPermissions(string $vnode, string $vuser, $vhostConfig): void
    {
        $mpath = $vhostConfig->paths->mpath."/{$vuser}";
        $hpath = $vhostConfig->paths->upath.'/home';

        // Set ownership to match parent directory
        $this->remoteExecution->executeAsRoot($vnode, "chown \$(stat -c '%u:%g' {$hpath}) -R {$mpath}");

        // Set directory permissions
        $this->remoteExecution->executeAsRoot($vnode, "find {$mpath} -type d -exec chmod 00750 {} +");

        // Set file permissions
        $this->remoteExecution->executeAsRoot($vnode, "find {$mpath} -type f -exec chmod 00640 {} +");
    }

    protected function updateLocalConfiguration(string $vnode, string $vhost, string $email, string $password): void
    {
        $configPath = env('NSVAR', $_SERVER['HOME'].'/.ns/var')."/{$vnode}/{$vhost}.conf";

        if (! file_exists($configPath)) {
            $this->warn("⚠️ Local config file not found: {$configPath}");

            return;
        }

        $configContent = file_get_contents($configPath);

        if (str_contains($configContent, "Mail\n=========")) {
            // Append to existing Mail section
            file_put_contents($configPath, "Username: {$email}\nPassword: {$password}\n\n", FILE_APPEND);
        } else {
            // Add new Mail section
            $mailSection = "\n\nMail\n=========\n\nUsername: {$email}\nPassword: {$password}\n\n";
            file_put_contents($configPath, $mailSection, FILE_APPEND);
        }
    }

    protected function generateSecurePassword(int $length = 12): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $password;
    }
}
