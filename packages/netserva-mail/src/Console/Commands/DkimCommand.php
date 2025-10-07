<?php

namespace NetServa\Mail\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Laravel\Prompts\Progress;
use NetServa\Cli\Services\NetServaConfigurationService;
use NetServa\Cli\Services\RemoteExecutionService;

/**
 * DKIM Management Command
 *
 * Laravel Artisan port of the bash dkim command.
 * Manages DKIM keys for email authentication on remote and local servers.
 */
class DkimCommand extends Command
{
    protected $signature = 'mail:dkim
                           {action : Action to perform (add, del, list, show)}
                           {domain? : Domain name for DKIM operations}
                           {--selector=mail : DKIM selector (default: mail)}
                           {--keylen=2048 : Key length in bits (default: 2048)}
                           {--vnode= : Remote server identifier for SSH execution}
                           {--force : Skip confirmation prompts}
                           {--format=table : Output format (table, json, dns)}
                           {--dry-run : Show what would be done without executing}';

    protected $description = 'Manage DKIM keys for email authentication';

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
        $action = $this->argument('action');
        $domain = $this->argument('domain');
        $selector = $this->option('selector');
        $keylen = (int) $this->option('keylen');
        $vnode = $this->option('vnode');
        $isDryRun = $this->option('dry-run');
        $format = $this->option('format');

        // Validate action
        if (! in_array($action, ['add', 'del', 'list', 'show'])) {
            $this->error("❌ Invalid action: {$action}");
            $this->info('Valid actions: add, del, list, show');

            return self::FAILURE;
        }

        // Validate domain requirement for certain actions
        if (in_array($action, ['add', 'del']) && empty($domain)) {
            $this->error("❌ Domain required for {$action} operation");

            return self::FAILURE;
        }

        // Validate domain format if provided
        if ($domain && ! $this->isValidDomain($domain)) {
            $this->error("❌ Invalid domain format: {$domain}");

            return self::FAILURE;
        }

        if ($isDryRun) {
            $this->info("🔍 DRY RUN - Would perform DKIM {$action}".($domain ? " for {$domain}" : ''));
        }

        try {
            return $this->performDkimAction($action, $domain, $selector, $keylen, $vnode, $isDryRun, $format);
        } catch (Exception $e) {
            $this->error('❌ DKIM operation failed: '.$e->getMessage());
            Log::error('DKIM command failed', [
                'action' => $action,
                'domain' => $domain,
                'vnode' => $vnode,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }

    protected function performDkimAction(
        string $action,
        ?string $domain,
        string $selector,
        int $keylen,
        ?string $vnode,
        bool $isDryRun,
        string $format
    ): int {
        if ($vnode) {
            // Remote execution
            return $this->performRemoteDkimAction($action, $domain, $selector, $keylen, $vnode, $isDryRun, $format);
        } else {
            // Local execution
            $this->warn('⚠️ Local DKIM execution not implemented yet - use --vnode for remote execution');

            return self::FAILURE;
        }
    }

    protected function performRemoteDkimAction(
        string $action,
        ?string $domain,
        string $selector,
        int $keylen,
        string $vnode,
        bool $isDryRun,
        string $format
    ): int {
        // Validate SSH connection
        if (! $this->validateSshConnection($vnode)) {
            return self::FAILURE;
        }

        // Load vhost configuration if domain specified
        if ($domain) {
            $config = $this->configService->load("{$vnode}/{$domain}");
            if (empty($config)) {
                $this->warn("⚠️ No vhost configuration found for {$domain} on {$vnode}");
            }
        }

        if (! $this->option('force') && in_array($action, ['add', 'del'])) {
            $this->displayDkimOperationSummary($action, $domain, $selector, $keylen, $vnode);

            $confirmMessage = match ($action) {
                'add' => "Create DKIM key for {$domain}?",
                'del' => "⚠️ Delete DKIM key for {$domain}? This cannot be undone.",
                default => 'Continue with DKIM operation?'
            };

            if (! confirm($confirmMessage, $action === 'add')) {
                $this->info('⚠️ Operation cancelled');

                return self::SUCCESS;
            }
        }

        if ($isDryRun) {
            $this->showDryRunOperations($action, $domain, $selector, $keylen, $vnode);

            return self::SUCCESS;
        }

        return match ($action) {
            'add' => $this->addDkimKey($domain, $selector, $keylen, $vnode),
            'del' => $this->deleteDkimKey($domain, $selector, $vnode),
            'list' => $this->listDkimKeys($vnode, $format),
            'show' => $this->showDkimRecords($domain, $selector, $vnode, $format),
            default => self::FAILURE
        };
    }

    protected function validateSshConnection(string $vnode): bool
    {
        $sshConfigPath = env('NSSSH', $_SERVER['HOME'].'/.ns/ssh')."/hosts/{$vnode}";

        if (! file_exists($sshConfigPath)) {
            $this->error("❌ SSH host '{$vnode}' not found in ~/.ns/ssh/hosts/");

            return false;
        }

        $result = $this->remoteExecution->executeAsRoot($vnode, 'echo "SSH_OK"');

        if (! $result['success'] || trim($result['output']) !== 'SSH_OK') {
            $this->error("❌ SSH connection to {$vnode} failed");

            return false;
        }

        return true;
    }

    protected function displayDkimOperationSummary(
        string $action,
        ?string $domain,
        string $selector,
        int $keylen,
        string $vnode
    ): void {
        $actionEmoji = match ($action) {
            'add' => '🔐',
            'del' => '🗑️',
            default => '📧'
        };

        $this->info("{$actionEmoji} DKIM {$action} Operation Summary");

        $data = [
            ['Property', 'Value'],
            ['Action', ucfirst($action)],
            ['Server', $vnode],
        ];

        if ($domain) {
            $data[] = ['Domain', $domain];
            $data[] = ['Selector', $selector];

            if ($action === 'add') {
                $data[] = ['Key Length', "{$keylen} bits"];
            }
        }

        $this->table(array_shift($data), $data);
    }

    protected function showDryRunOperations(
        string $action,
        ?string $domain,
        string $selector,
        int $keylen,
        string $vnode
    ): void {
        $this->info('🔍 DRY RUN - Operations that would be performed:');

        $operations = match ($action) {
            'add' => [
                "Validate SSH connection to {$vnode}",
                'Initialize OpenDKIM directory structure if needed',
                "Create domain key directory: /etc/opendkim/keys/{$domain}",
                "Generate DKIM key with selector '{$selector}' and {$keylen}-bit length",
                'Update KeyTable configuration',
                'Update SigningTable configuration',
                'Update TrustedHosts configuration',
                'Restart OpenDKIM service',
                'Display DNS record for domain setup',
            ],
            'del' => [
                "Validate SSH connection to {$vnode}",
                "Remove key directory: /etc/opendkim/keys/{$domain}",
                'Remove from KeyTable configuration',
                'Remove from SigningTable configuration',
                'Remove from TrustedHosts configuration',
                'Restart OpenDKIM service',
            ],
            'list' => [
                "Validate SSH connection to {$vnode}",
                'List all DKIM domains in /etc/opendkim/keys',
            ],
            'show' => [
                "Validate SSH connection to {$vnode}",
                $domain ? "Display DNS record for {$domain}" : 'Display all DNS records',
            ],
            default => ["Unknown operation: {$action}"]
        };

        foreach ($operations as $operation) {
            $this->line("  • {$operation}");
        }
    }

    protected function addDkimKey(string $domain, string $selector, int $keylen, string $vnode): int
    {
        $progress = new Progress('Adding DKIM key...', 7);

        try {
            // Step 1: Initialize OpenDKIM structure
            $progress->label('Initializing OpenDKIM structure...');
            $this->initializeOpenDkim($vnode);
            $progress->advance();

            // Step 2: Check if key already exists
            $progress->label('Checking existing keys...');
            if ($this->dkimKeyExists($domain, $selector, $vnode)) {
                $progress->finish();
                $this->warn("⚠️ DKIM key for {$domain} already exists");

                return self::SUCCESS;
            }
            $progress->advance();

            // Step 3: Create domain directory
            $progress->label('Creating domain directory...');
            $this->createDomainDirectory($domain, $vnode);
            $progress->advance();

            // Step 4: Generate DKIM key
            $progress->label('Generating DKIM key...');
            $this->generateDkimKey($domain, $selector, $keylen, $vnode);
            $progress->advance();

            // Step 5: Update configuration files
            $progress->label('Updating configuration files...');
            $this->updateDkimConfiguration($domain, $selector, 'add', $vnode);
            $progress->advance();

            // Step 6: Set permissions
            $progress->label('Setting permissions...');
            $this->setDkimPermissions($domain, $vnode);
            $progress->advance();

            // Step 7: Restart OpenDKIM
            $progress->label('Restarting OpenDKIM...');
            $this->restartOpenDkim($vnode);
            $progress->advance();

            $progress->finish();

            $this->info("✅ DKIM key added for {$domain} using selector '{$selector}' and {$keylen}-bit key");
            $this->info('📝 Add this DNS record:');
            $this->showDnsRecord($domain, $selector, $vnode);

            return self::SUCCESS;

        } catch (Exception $e) {
            $progress->finish();
            throw $e;
        }
    }

    protected function deleteDkimKey(string $domain, string $selector, string $vnode): int
    {
        $progress = new Progress('Deleting DKIM key...', 5);

        try {
            // Step 1: Check if key exists
            $progress->label('Checking key exists...');
            if (! $this->dkimKeyExists($domain, $selector, $vnode)) {
                $progress->finish();
                $this->warn("⚠️ DKIM key for {$domain} does not exist");

                return self::SUCCESS;
            }
            $progress->advance();

            // Step 2: Remove key directory
            $progress->label('Removing key files...');
            $this->removeDkimKeyDirectory($domain, $vnode);
            $progress->advance();

            // Step 3: Update configuration files
            $progress->label('Updating configuration files...');
            $this->updateDkimConfiguration($domain, $selector, 'del', $vnode);
            $progress->advance();

            // Step 4: Clean up TrustedHosts
            $progress->label('Cleaning TrustedHosts...');
            $this->cleanupTrustedHosts($domain, $vnode);
            $progress->advance();

            // Step 5: Restart OpenDKIM
            $progress->label('Restarting OpenDKIM...');
            $this->restartOpenDkim($vnode);
            $progress->advance();

            $progress->finish();

            $this->info("✅ DKIM key removed for {$domain}");

            return self::SUCCESS;

        } catch (Exception $e) {
            $progress->finish();
            throw $e;
        }
    }

    protected function listDkimKeys(string $vnode, string $format): int
    {
        $this->info("📝 DKIM domains on {$vnode}:");

        $result = $this->remoteExecution->executeAsRoot($vnode,
            'if [[ -d /etc/opendkim/keys ]]; then find /etc/opendkim/keys -mindepth 1 -maxdepth 1 -type d -exec basename {} \\; | sort; else echo "No DKIM keys found"; fi'
        );

        if (! $result['success']) {
            throw new Exception('Failed to list DKIM keys');
        }

        $output = trim($result['output']);
        if ($output === 'No DKIM keys found') {
            $this->warn('⚠️ No DKIM keys found');

            return self::SUCCESS;
        }

        $domains = array_filter(explode("\n", $output));

        if ($format === 'json') {
            $this->line(json_encode($domains, JSON_PRETTY_PRINT));
        } else {
            foreach ($domains as $domain) {
                $this->line("  • {$domain}");
            }
        }

        return self::SUCCESS;
    }

    protected function showDkimRecords(string $domain, string $selector, string $vnode, string $format): int
    {
        if ($domain) {
            // Show specific domain
            $this->info("📝 DKIM DNS record for {$domain}:");
            $this->showDnsRecord($domain, $selector, $vnode, $format);
        } else {
            // Show all domains
            $this->info("📝 All DKIM DNS records on {$vnode}:");
            $this->showAllDnsRecords($selector, $vnode, $format);
        }

        return self::SUCCESS;
    }

    protected function initializeOpenDkim(string $vnode): void
    {
        $initScript = "
            if [[ ! -d /etc/opendkim ]]; then
                echo 'Initializing OpenDKIM directory structure'
                mkdir -p /etc/opendkim/keys
                touch /etc/opendkim/KeyTable
                touch /etc/opendkim/SigningTable
                echo -e '127.0.0.1\nlocalhost' >/etc/opendkim/TrustedHosts
                chown -R opendkim:opendkim /etc/opendkim
                find /etc/opendkim -type d -exec chmod 750 {} +
                find /etc/opendkim -type f -exec chmod 640 {} +
                echo 'OpenDKIM structure initialized'
            fi
        ";

        $result = $this->remoteExecution->executeAsRoot($vnode, $initScript);

        if (! $result['success']) {
            throw new Exception('Failed to initialize OpenDKIM structure');
        }
    }

    protected function dkimKeyExists(string $domain, string $selector, string $vnode): bool
    {
        $keyfile = "/etc/opendkim/keys/{$domain}/{$selector}.private";
        $result = $this->remoteExecution->executeAsRoot($vnode, "[[ -f '{$keyfile}' ]] && echo 'EXISTS'");

        return $result['success'] && trim($result['output']) === 'EXISTS';
    }

    protected function createDomainDirectory(string $domain, string $vnode): void
    {
        $result = $this->remoteExecution->executeAsRoot($vnode, "mkdir -p /etc/opendkim/keys/{$domain}");

        if (! $result['success']) {
            throw new Exception('Failed to create domain directory');
        }
    }

    protected function generateDkimKey(string $domain, string $selector, int $keylen, string $vnode): void
    {
        $keydir = "/etc/opendkim/keys/{$domain}";

        $generateScript = "
            cd {$keydir}
            opendkim-genkey -b {$keylen} -s '{$selector}' -d '{$domain}' -D '{$keydir}'
            if [[ \$? -gt 0 ]]; then
                echo 'Failed to generate DKIM key'
                exit 1
            fi

            # Move files to expected names
            [[ -f '{$selector}.private' ]] && mv '{$selector}.private' '{$selector}.private'
            [[ -f '{$selector}.txt' ]] && mv '{$selector}.txt' '{$selector}.txt'
        ";

        $result = $this->remoteExecution->executeAsRoot($vnode, $generateScript);

        if (! $result['success']) {
            throw new Exception('Failed to generate DKIM key: '.$result['output']);
        }
    }

    protected function updateDkimConfiguration(string $domain, string $selector, string $action, string $vnode): void
    {
        $keyfile = "/etc/opendkim/keys/{$domain}/{$selector}.private";

        if ($action === 'add') {
            $updateScript = "
                # Update KeyTable
                echo '{$selector}._domainkey.{$domain} {$domain}:{$selector}:{$keyfile}' >>/etc/opendkim/KeyTable

                # Update SigningTable
                echo '*@{$domain} {$selector}._domainkey.{$domain}' >>/etc/opendkim/SigningTable

                # Update TrustedHosts
                echo '{$domain}' >>/etc/opendkim/TrustedHosts
                echo '*.{$domain}' >>/etc/opendkim/TrustedHosts

                # Remove duplicates from TrustedHosts
                sort -u /etc/opendkim/TrustedHosts -o /etc/opendkim/TrustedHosts
            ";
        } else { // delete
            $updateScript = "
                # Remove from KeyTable
                sed -i '/{$selector}._domainkey.{$domain}/d' /etc/opendkim/KeyTable

                # Remove from SigningTable
                sed -i '/*@{$domain}/d' /etc/opendkim/SigningTable
            ";
        }

        $result = $this->remoteExecution->executeAsRoot($vnode, $updateScript);

        if (! $result['success']) {
            throw new Exception('Failed to update DKIM configuration');
        }
    }

    protected function setDkimPermissions(string $domain, string $vnode): void
    {
        $keydir = "/etc/opendkim/keys/{$domain}";

        $result = $this->remoteExecution->executeAsRoot($vnode, "chown -R opendkim:opendkim {$keydir} && chmod 640 {$keydir}/*");

        if (! $result['success']) {
            throw new Exception('Failed to set DKIM permissions');
        }
    }

    protected function restartOpenDkim(string $vnode): void
    {
        $result = $this->remoteExecution->executeAsRoot($vnode, 'systemctl restart opendkim');

        if (! $result['success']) {
            $this->warn('⚠️ Failed to restart OpenDKIM service - may need manual restart');
        }
    }

    protected function removeDkimKeyDirectory(string $domain, string $vnode): void
    {
        $result = $this->remoteExecution->executeAsRoot($vnode, "rm -rf /etc/opendkim/keys/{$domain}");

        if (! $result['success']) {
            throw new Exception('Failed to remove DKIM key directory');
        }
    }

    protected function cleanupTrustedHosts(string $domain, string $vnode): void
    {
        $cleanupScript = "
            sed -i '/{$domain}$/d' /etc/opendkim/TrustedHosts
            sed -i '/\\*.{$domain}$/d' /etc/opendkim/TrustedHosts
        ";

        $result = $this->remoteExecution->executeAsRoot($vnode, $cleanupScript);

        if (! $result['success']) {
            throw new Exception('Failed to cleanup TrustedHosts');
        }
    }

    protected function showDnsRecord(string $domain, string $selector, string $vnode, string $format = 'dns'): void
    {
        $txtfile = "/etc/opendkim/keys/{$domain}/{$selector}.txt";

        $result = $this->remoteExecution->executeAsRoot($vnode, "cat {$txtfile} 2>/dev/null");

        if (! $result['success'] || empty(trim($result['output']))) {
            $this->warn("⚠️ No DKIM DNS record found for {$domain}");

            return;
        }

        $dnsRecord = trim($result['output']);

        if ($format === 'json') {
            $this->line(json_encode(['domain' => $domain, 'selector' => $selector, 'dns_record' => $dnsRecord], JSON_PRETTY_PRINT));
        } else {
            $this->line($dnsRecord);
        }
    }

    protected function showAllDnsRecords(string $selector, string $vnode, string $format): void
    {
        $result = $this->remoteExecution->executeAsRoot($vnode,
            'if [[ -d /etc/opendkim/keys ]]; then
                for domain_dir in /etc/opendkim/keys/*/; do
                    if [[ -d "$domain_dir" ]]; then
                        domain=$(basename "$domain_dir")
                        txtfile="$domain_dir/'.$selector.'.txt"
                        if [[ -f "$txtfile" ]]; then
                            echo "### $domain:"
                            cat "$txtfile"
                            echo ""
                        fi
                    fi
                done
            fi'
        );

        if (! $result['success']) {
            throw new Exception('Failed to retrieve DNS records');
        }

        $output = trim($result['output']);
        if (empty($output)) {
            $this->warn('⚠️ No DKIM DNS records found');

            return;
        }

        if ($format === 'json') {
            // Parse output into structured format for JSON
            $records = [];
            $lines = explode("\n", $output);
            $currentDomain = null;
            $currentRecord = '';

            foreach ($lines as $line) {
                if (str_starts_with($line, '### ') && str_ends_with($line, ':')) {
                    if ($currentDomain && $currentRecord) {
                        $records[] = ['domain' => $currentDomain, 'dns_record' => trim($currentRecord)];
                    }
                    $currentDomain = substr($line, 4, -1);
                    $currentRecord = '';
                } elseif (! empty(trim($line))) {
                    $currentRecord .= $line."\n";
                }
            }

            if ($currentDomain && $currentRecord) {
                $records[] = ['domain' => $currentDomain, 'dns_record' => trim($currentRecord)];
            }

            $this->line(json_encode($records, JSON_PRETTY_PRINT));
        } else {
            $this->line($output);
        }
    }

    protected function isValidDomain(string $domain): bool
    {
        return filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }
}
