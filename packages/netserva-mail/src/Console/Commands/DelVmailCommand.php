<?php

namespace NetServa\Mail\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Laravel\Prompts\Progress;
use NetServa\Cli\Services\NetServaConfigurationService;
use NetServa\Cli\Services\RemoteExecutionService;

/**
 * Delete Virtual Mail User Command
 *
 * Laravel Artisan port of the bash delvmail command.
 * Removes virtual mail users from remote servers with proper backup.
 */
class DelVmailCommand extends Command
{
    protected $signature = 'mail:deluser
                           {vnode : SSH host/server identifier}
                           {email : Email address (user@domain)}
                           {--force : Skip confirmation prompts}
                           {--no-backup : Skip creating backup before deletion}
                           {--dry-run : Show what would be done without executing}';

    protected $description = 'Delete virtual mail user from remote server';

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
        $isDryRun = $this->option('dry-run');
        $noBackup = $this->option('no-backup');

        // Validate email format
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("❌ Invalid email format: {$email}");

            return self::FAILURE;
        }

        $vhost = substr($email, strpos($email, '@') + 1);
        $vuser = substr($email, 0, strpos($email, '@'));

        if ($isDryRun) {
            $this->info("🔍 DRY RUN - Would delete mail user: {$email} from {$vnode}");
        }

        try {
            return $this->deleteVirtualMailUser($vnode, $vhost, $email, $vuser, $isDryRun, $noBackup);
        } catch (Exception $e) {
            $this->error('❌ Failed to delete mail user: '.$e->getMessage());
            Log::error('DelVmail command failed', [
                'vnode' => $vnode,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }

    protected function deleteVirtualMailUser(
        string $vnode,
        string $vhost,
        string $email,
        string $vuser,
        bool $isDryRun,
        bool $noBackup
    ): int {
        // Load vhost configuration
        $config = $this->configService->load("{$vnode}/{$vhost}");
        if (empty($config)) {
            $this->error("❌ Configuration not found: {$vnode}/{$vhost}");

            return self::FAILURE;
        }

        // Generate VHost configuration for paths and database settings
        $vhostConfig = $this->configService->generateVhostConfig($vnode, $vhost);

        if (! $this->option('force')) {
            $this->displayMailUserDeletionSummary($email, $vnode, $vhost, $vuser, $noBackup);

            if (! confirm("⚠️ This will permanently delete {$email} and all mail data. Continue?", false)) {
                $this->info('⚠️ Operation cancelled');

                return self::SUCCESS;
            }
        }

        if ($isDryRun) {
            $this->showDryRunOperations($email, $vnode, $vhost, $vuser, $vhostConfig, $noBackup);

            return self::SUCCESS;
        }

        return $this->executeMailUserDeletion($email, $vnode, $vhost, $vuser, $vhostConfig, $noBackup);
    }

    protected function displayMailUserDeletionSummary(
        string $email,
        string $vnode,
        string $vhost,
        string $vuser,
        bool $noBackup
    ): void {
        $this->error('⚠️  Mail User Deletion Summary');
        $this->table(['Property', 'Value'], [
            ['Email', $email],
            ['Server', $vnode],
            ['Domain', $vhost],
            ['Username', $vuser],
            ['Backup', $noBackup ? '❌ No backup' : '✅ Create backup'],
        ]);

        $this->warn('⚠️  This operation will:');
        $this->line("  • Delete all mail data for {$email}");
        $this->line('  • Remove database entries (vmails, valias, vmail_log)');
        $this->line('  • Remove Maildir and all stored messages');
        if (! $noBackup) {
            $this->line('  • Create backup before deletion');
        }
    }

    protected function showDryRunOperations(
        string $email,
        string $vnode,
        string $vhost,
        string $vuser,
        $vhostConfig,
        bool $noBackup
    ): void {
        $this->info('🔍 DRY RUN - Operations that would be performed:');

        $operations = [
            "Validate SSH connection to {$vnode}",
            "Check if mail user {$email} exists",
            "Verify Maildir exists: {$vhostConfig->paths->mpath}/{$vuser}",
        ];

        if (! $noBackup) {
            $operations[] = 'Create backup: $BPATH/'.date('Ymd')."_{$vuser}.tgz";
        }

        $operations = array_merge($operations, [
            'Generate user details for backup',
            'Remove from local configuration file',
            "Delete Maildir: {$vhostConfig->paths->mpath}/{$vuser}",
            'Remove from vmails table',
            'Remove from valias table',
            'Remove from vmail_log table',
        ]);

        foreach ($operations as $operation) {
            $this->line("  • {$operation}");
        }
    }

    protected function executeMailUserDeletion(
        string $email,
        string $vnode,
        string $vhost,
        string $vuser,
        $vhostConfig,
        bool $noBackup
    ): int {
        $totalSteps = $noBackup ? 6 : 8;
        $progress = new Progress('Deleting mail user...', $totalSteps);

        try {
            // Step 1: Validate SSH connection
            $progress->label('Validating SSH connection...');
            if (! $this->validateSshConnection($vnode)) {
                $progress->finish();

                return self::FAILURE;
            }
            $progress->advance();

            // Step 2: Check if user exists
            $progress->label('Checking mail user exists...');
            $mailId = $this->getMailUserId($vnode, $email, $vhostConfig);
            if (! $mailId) {
                $progress->finish();
                $this->warn("⚠️ Mail user {$email} does not exist in database");

                return self::SUCCESS;
            }
            $progress->advance();

            // Step 3: Check Maildir exists
            $progress->label('Checking Maildir exists...');
            $mpath = $vhostConfig->paths->mpath."/{$vuser}";
            if (! $this->maildirExists($vnode, $mpath)) {
                $progress->finish();
                $this->error("❌ Maildir {$mpath} does not exist");

                return self::FAILURE;
            }
            $progress->advance();

            if (! $noBackup) {
                // Step 4: Create backup
                $progress->label('Creating backup...');
                $this->createMaildirBackup($vnode, $email, $vuser, $mpath, $vhostConfig);
                $progress->advance();

                // Step 5: Generate user details
                $progress->label('Generating user details...');
                $this->generateUserDetails($vnode, $email, $mpath);
                $progress->advance();
            }

            // Step: Remove from local config
            $progress->label('Updating local configuration...');
            $this->removeFromLocalConfiguration($vnode, $vhost, $email);
            $progress->advance();

            // Step: Delete Maildir
            $progress->label('Deleting Maildir...');
            $this->deleteMaildir($vnode, $mpath);
            $progress->advance();

            // Step: Remove database entries
            $progress->label('Removing database entries...');
            $this->removeDatabaseEntries($vnode, $email, $mailId, $vhostConfig);
            $progress->advance();

            $progress->finish();

            $this->info("✅ Mail user {$email} deleted successfully from {$vnode}");
            if (! $noBackup) {
                $backupFile = date('Ymd').'_'.str_replace('@', '_', $email).'.tgz';
                $this->info("💾 Backup created: \$BPATH/{$backupFile}");
            }

            return self::SUCCESS;

        } catch (Exception $e) {
            $progress->finish();
            throw $e;
        }
    }

    protected function validateSshConnection(string $vnode): bool
    {
        // Check SSH configuration exists (using new NSSSH path structure)
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

    protected function getMailUserId(string $vnode, string $email, $vhostConfig): ?int
    {
        $sqlPath = $vhostConfig->paths->toArray()['mysql_path'] ?? 'sqlite3';

        $result = $this->remoteExecution->executeAsRoot($vnode,
            "{$sqlPath} -e \"SELECT id FROM vmails WHERE user = '{$email}'\""
        );

        if (! $result['success'] || empty(trim($result['output']))) {
            return null;
        }

        return (int) trim($result['output']);
    }

    protected function maildirExists(string $vnode, string $mpath): bool
    {
        $result = $this->remoteExecution->executeAsRoot($vnode, "[[ -d {$mpath} ]] && echo 'EXISTS'");

        return $result['success'] && trim($result['output']) === 'EXISTS';
    }

    protected function createMaildirBackup(
        string $vnode,
        string $email,
        string $vuser,
        string $mpath,
        $vhostConfig
    ): void {
        $bpath = $vhostConfig->paths->bpath;
        $backupFile = date('Ymd').'_'.str_replace('@', '_', $email).'.tgz';
        $fullBackupPath = "{$bpath}/{$backupFile}";

        // Create backup directory if needed
        $this->remoteExecution->executeAsRoot($vnode, "[[ ! -d {$bpath} ]] && mkdir -p {$bpath}");

        // Remove any existing same-day backup
        $this->remoteExecution->executeAsRoot($vnode, "[[ -f {$fullBackupPath} ]] && rm {$fullBackupPath}");

        // Create backup
        $result = $this->remoteExecution->executeAsRoot($vnode, "tar czf {$fullBackupPath} {$mpath} >/dev/null 2>&1");

        if (! $result['success']) {
            throw new Exception("Failed to create backup of {$mpath}");
        }

        $this->info("💾 Backup created: {$fullBackupPath}");
    }

    protected function generateUserDetails(string $vnode, string $email, string $mpath): void
    {
        // Generate user details for backup (like original shuser command)
        $this->remoteExecution->executeAsRoot($vnode,
            "source ~/.sh/shrc.sh && shuser {$email} >{$mpath}/shuser_details"
        );
    }

    protected function removeFromLocalConfiguration(string $vnode, string $vhost, string $email): void
    {
        $configPath = env('NSVAR', $_SERVER['HOME'].'/.ns/var')."/{$vnode}/{$vhost}.conf";

        if (! file_exists($configPath)) {
            $this->warn("⚠️ Local config file not found: {$configPath}");

            return;
        }

        $configContent = file_get_contents($configPath);
        $lines = explode("\n", $configContent);
        $filteredLines = [];
        $skip = false;

        foreach ($lines as $line) {
            // Start skipping when we find the username line
            if (trim($line) === "Username: {$email}") {
                $skip = true;

                continue;
            }

            // Stop skipping after password line or empty line
            if ($skip && (str_starts_with(trim($line), 'Password:') || trim($line) === '')) {
                $skip = false;

                continue;
            }

            // Add line if not skipping
            if (! $skip) {
                $filteredLines[] = $line;
            }
        }

        file_put_contents($configPath, implode("\n", $filteredLines));
        $this->info("📝 Removed {$email} from local configuration");
    }

    protected function deleteMaildir(string $vnode, string $mpath): void
    {
        $result = $this->remoteExecution->executeAsRoot($vnode, "rm -rf {$mpath}");

        if (! $result['success']) {
            throw new Exception("Failed to delete Maildir: {$mpath}");
        }

        $this->info("🗑️  Deleted Maildir: {$mpath}");
    }

    protected function removeDatabaseEntries(
        string $vnode,
        string $email,
        int $mailId,
        $vhostConfig
    ): void {
        $sqlPath = $vhostConfig->paths->toArray()['mysql_path'] ?? 'sqlite3';

        // Remove from vmails table
        $result = $this->remoteExecution->executeAsRoot($vnode,
            "{$sqlPath} -e \"DELETE FROM vmails WHERE id = {$mailId}\""
        );

        if (! $result['success']) {
            $this->warn("⚠️ Failed to remove {$email} from vmails table");
        } else {
            $this->info("🗑️  Removed {$email} from vmails table");
        }

        // Remove from valias table
        $result = $this->remoteExecution->executeAsRoot($vnode,
            "{$sqlPath} -e \"DELETE FROM valias WHERE target = '{$email}'\""
        );

        if (! $result['success']) {
            $this->warn("⚠️ Failed to remove {$email} from valias table");
        } else {
            $this->info("🗑️  Removed {$email} from valias table");
        }

        // Remove from vmail_log table
        $result = $this->remoteExecution->executeAsRoot($vnode,
            "{$sqlPath} -e \"DELETE FROM vmail_log WHERE mid = {$mailId}\""
        );

        if (! $result['success']) {
            $this->warn("⚠️ Failed to remove {$email} from vmail_log table");
        } else {
            $this->info("🗑️  Removed {$email} from vmail_log table");
        }
    }
}
