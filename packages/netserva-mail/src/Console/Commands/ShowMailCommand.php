<?php

namespace NetServa\Mail\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Laravel\Prompts\table;
use NetServa\Core\Services\NetServaConfigurationService;
use NetServa\Core\Services\RemoteExecutionService;

/**
 * Show Mail Analysis Command
 *
 * Laravel Artisan port of the bash shmail command.
 * Analyzes mailboxes on remote servers with detailed statistics.
 */
class ShowMailCommand extends Command
{
    protected $signature = 'mail:show
                           {vnode : SSH host/server identifier}
                           {search : Email address, domain, or search pattern}
                           {--detailed : Show detailed analysis for single mailbox}
                           {--format=table : Output format (table, json)}';

    protected $description = 'Show mail analysis for users or domains';

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
        $search = strtolower($this->argument('search'));
        $detailed = $this->option('detailed');
        $format = $this->option('format');

        try {
            return $this->analyzeMailboxes($vnode, $search, $detailed, $format);
        } catch (Exception $e) {
            $this->error('❌ Failed to analyze mailboxes: '.$e->getMessage());
            Log::error('ShowMail command failed', [
                'vnode' => $vnode,
                'search' => $search,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }

    protected function analyzeMailboxes(string $vnode, string $search, bool $detailed, string $format): int
    {
        // Validate SSH connection
        if (! $this->validateSshConnection($vnode)) {
            return self::FAILURE;
        }

        // Get database configuration from any available vhost config
        $vhostConfig = $this->getVhostConfiguration($vnode);
        if (! $vhostConfig) {
            $this->error("❌ No vhost configuration found for {$vnode}");

            return self::FAILURE;
        }

        // Find mailboxes matching search criteria
        $mailboxes = $this->findMailboxes($vnode, $search, $vhostConfig);

        if (empty($mailboxes)) {
            $this->warn("⚠️ No mailboxes found matching '{$search}'");

            return self::SUCCESS;
        }

        $this->info("📬 Mailbox analysis for '{$search}' on {$vnode}");

        if (count($mailboxes) === 1 && filter_var($search, FILTER_VALIDATE_EMAIL)) {
            // Single mailbox detailed analysis
            return $this->showDetailedAnalysis($vnode, $mailboxes[0], $format);
        } else {
            // Multiple mailboxes summary
            return $this->showMailboxSummary($vnode, $mailboxes, $format);
        }
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

    protected function getVhostConfiguration(string $vnode): ?object
    {
        $nsvar = env('NSVAR', $_SERVER['HOME'].'/.ns/var');
        $vnodeDir = "{$nsvar}/{$vnode}";

        if (! is_dir($vnodeDir)) {
            return null;
        }

        // Find first available vhost config (excluding .conf files)
        $files = glob("{$vnodeDir}/*");
        foreach ($files as $file) {
            if (is_file($file) && ! str_ends_with($file, '.conf')) {
                $config = $this->configService->load("{$vnode}/".basename($file));
                if (! empty($config)) {
                    return $this->configService->generateVhostConfig($vnode, basename($file));
                }
            }
        }

        return null;
    }

    protected function findMailboxes(string $vnode, string $search, object $vhostConfig): array
    {
        // Determine search pattern for database query
        if (str_contains($search, '@')) {
            // Full email address
            $pattern = $search;
        } elseif (str_contains($search, '.')) {
            // Domain pattern
            $pattern = "%@{$search}";
        } else {
            // Partial email pattern
            $pattern = "{$search}%";
        }

        // Query database for matching mailboxes
        $query = "SELECT user FROM vmails WHERE user LIKE '{$pattern}' AND active = 1 ORDER BY user";
        $sqlPath = $vhostConfig->paths->toArray()['mysql_path'] ?? 'sqlite3';

        $result = $this->remoteExecution->executeAsRoot($vnode, "echo \"{$query}\" | {$sqlPath}");

        if (! $result['success']) {
            throw new Exception('Failed to query mailbox database');
        }

        $output = trim($result['output']);
        if (empty($output)) {
            return [];
        }

        // Parse output and filter out headers
        $lines = explode("\n", $output);
        $mailboxes = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (! empty($line) && filter_var($line, FILTER_VALIDATE_EMAIL)) {
                $mailboxes[] = $line;
            }
        }

        return $mailboxes;
    }

    protected function showDetailedAnalysis(string $vnode, string $email, string $format): int
    {
        $vhost = substr($email, strpos($email, '@') + 1);
        $vuser = substr($email, 0, strpos($email, '@'));
        $maildirPath = "/srv/{$vhost}/msg/{$vuser}/Maildir";

        $this->info("📬 Detailed mailbox analysis: {$email}");
        $this->info("   Path: {$maildirPath}");

        // Check if Maildir exists
        $result = $this->remoteExecution->executeAsRoot($vnode, "[[ -d '{$maildirPath}' ]] && echo 'EXISTS'");
        if (! $result['success'] || trim($result['output']) !== 'EXISTS') {
            $this->error("❌ Maildir not found: {$maildirPath}");

            return self::FAILURE;
        }

        // Get detailed folder analysis
        $folderAnalysis = $this->getDetailedFolderAnalysis($vnode, $maildirPath);

        if ($format === 'json') {
            $this->line(json_encode($folderAnalysis, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        // Display table format
        $this->displayDetailedTable($folderAnalysis);

        // Check for additional information
        $this->showAdditionalInfo($vnode, $vhost, $vuser, $maildirPath);

        return self::SUCCESS;
    }

    protected function getDetailedFolderAnalysis(string $vnode, string $maildirPath): array
    {
        $script = "
            cd '{$maildirPath}' || exit 1

            find . -maxdepth 2 -type d -name 'new' -o -name 'cur' | sort -f | while read dir; do
                [[ -z \$dir ]] && continue
                cd \"\$dir\" || continue

                size=\$(du -s . | cut -f1)
                files=\$(find . -type f | wc -l)

                cd - >/dev/null

                # Format size
                if [[ \$size -lt 5 ]]; then
                    size_str=' '
                elif [[ \$size -lt 1000000 ]]; then
                    size_str=\$(awk \"BEGIN { printf \\\"%'3.2fM\\\", \$size/1000 }\")
                else
                    size_str=\$(awk \"BEGIN { printf \\\"%'3.2fG\\\", \$size/1000000 }\")
                fi

                [[ \$files -eq 0 ]] && files_str=' ' || files_str=\$files

                folder=\$(echo \$dir | cut -c 3-)
                echo \"\$folder|\$files_str|\$size_str\"
            done

            # Total summary
            total_size=\$(du -s '{$maildirPath}' | cut -f1)
            total_files=\$(find '{$maildirPath}' -type f | wc -l)

            if [[ \$total_size -lt 5 ]]; then
                total_size_str=' '
            elif [[ \$total_size -lt 1000000 ]]; then
                total_size_str=\$(awk \"BEGIN { printf \\\"%'3.2fM\\\", \$total_size/1000 }\")
            else
                total_size_str=\$(awk \"BEGIN { printf \\\"%'3.2fG\\\", \$total_size/1000000 }\")
            fi

            echo \"TOTAL|\$total_files|\$total_size_str\"
        ";

        $result = $this->remoteExecution->executeAsRoot($vnode, $script);

        if (! $result['success']) {
            throw new Exception('Failed to analyze folder structure');
        }

        $analysis = [];
        $lines = explode("\n", trim($result['output']));

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            $parts = explode('|', $line);
            if (count($parts) === 3) {
                $analysis[] = [
                    'folder' => $parts[0],
                    'files' => $parts[1],
                    'size' => $parts[2],
                ];
            }
        }

        return $analysis;
    }

    protected function displayDetailedTable(array $analysis): void
    {
        $rows = [];
        $totalRow = null;

        foreach ($analysis as $item) {
            if ($item['folder'] === 'TOTAL') {
                $totalRow = $item;
            } else {
                $rows[] = [$item['folder'], $item['files'], $item['size']];
            }
        }

        if (! empty($rows)) {
            table(['Folder', 'Files', 'Size'], $rows);
        }

        if ($totalRow) {
            $this->line('---------------------------------------------------------------');
            $this->info(sprintf('%-45s %7s %9s', 'Total', $totalRow['files'], $totalRow['size']));
            $this->line('---------------------------------------------------------------');
        }
    }

    protected function showAdditionalInfo(string $vnode, string $vhost, string $vuser, string $maildirPath): void
    {
        // Check for broken symlinks
        $result = $this->remoteExecution->executeAsRoot($vnode,
            "cd '{$maildirPath}' && find . -type l -exec test ! -e {} \\; -print 2>/dev/null | head -5"
        );

        if ($result['success'] && ! empty(trim($result['output']))) {
            $this->warn('Broken symlinks found:');
            $this->line(trim($result['output']));
        }

        // Check for SpamProbe data
        $spamprobePath = "/srv/{$vhost}/msg/{$vuser}/.spamprobe";
        $result = $this->remoteExecution->executeAsRoot($vnode,
            "[[ -d '{$spamprobePath}' ]] && du -h '{$spamprobePath}' | awk '{printf \"%s %s\", \$1, \$2}'"
        );

        if ($result['success'] && ! empty(trim($result['output']))) {
            $this->info('SpamProbe data: '.trim($result['output']));
        }
    }

    protected function showMailboxSummary(string $vnode, array $mailboxes, string $format): int
    {
        $this->info('📮 Found '.count($mailboxes).' mailboxes:');

        $summaryData = [];

        foreach ($mailboxes as $email) {
            $vhost = substr($email, strpos($email, '@') + 1);
            $vuser = substr($email, 0, strpos($email, '@'));
            $maildirPath = "/srv/{$vhost}/msg/{$vuser}/Maildir";

            // Check if Maildir exists
            $exists = $this->remoteExecution->executeAsRoot($vnode, "[[ -d '{$maildirPath}' ]] && echo 'EXISTS'");
            if (! $exists['success'] || trim($exists['output']) !== 'EXISTS') {
                continue;
            }

            // Get size and file count
            $sizeResult = $this->remoteExecution->executeAsRoot($vnode, "du -s '{$maildirPath}' 2>/dev/null | cut -f1");
            $filesResult = $this->remoteExecution->executeAsRoot($vnode, "find '{$maildirPath}' -type f 2>/dev/null | wc -l");

            $size = (int) trim($sizeResult['output'] ?? '0');
            $files = (int) trim($filesResult['output'] ?? '0');

            // Format size
            if ($size < 5) {
                $sizeStr = ' ';
            } elseif ($size < 1000000) {
                $sizeStr = sprintf("%'3.2fM", $size / 1000);
            } else {
                $sizeStr = sprintf("%'3.2fG", $size / 1000000);
            }

            $summaryData[] = [
                'email' => $email,
                'files' => $files,
                'size' => $sizeStr,
                'size_bytes' => $size,
            ];
        }

        if ($format === 'json') {
            $this->line(json_encode($summaryData, JSON_PRETTY_PRINT));
        } else {
            // Sort by email for consistent display
            usort($summaryData, fn ($a, $b) => strcmp($a['email'], $b['email']));

            $rows = [];
            foreach ($summaryData as $item) {
                $rows[] = [$item['email'], $item['files'], $item['size']];
            }

            table(['Email', 'Files', 'Size'], $rows);
        }

        return self::SUCCESS;
    }
}
