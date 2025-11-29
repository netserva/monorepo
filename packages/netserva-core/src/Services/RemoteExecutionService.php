<?php

namespace NetServa\Core\Services;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Remote Execution Service
 *
 * Extends RemoteConnectionService with additional functionality for:
 * - Root privilege execution
 * - Shell environment synchronization
 * - VHost environment sourcing
 * - Command environment setup
 */
class RemoteExecutionService extends RemoteConnectionService
{
    protected VhostConfigService $vhostConfig;

    protected string $shellPath;

    public function __construct(VhostConfigService $vhostConfig)
    {
        parent::__construct();
        $this->vhostConfig = $vhostConfig;
        $this->shellPath = config('netserva-cli.remote.shell_env_path', '~/.sh');
    }

    /**
     * Execute command as root on remote host
     */
    public function executeAsRoot(string $host, string $command, bool $useSudo = true): array
    {
        try {
            // Try to use the database-based SSH configuration first
            $user = $this->getConnectionUser($host);

            if ($user === 'root' || ! $useSudo) {
                return $this->exec($host, $command);
            }

            $sudoCommand = "sudo -n {$command}";

            Log::info('Executing command as root via sudo', [
                'host' => $host,
                'user' => $user,
                'command' => $command,
            ]);

            return $this->exec($host, $sudoCommand);

        } catch (Exception $e) {
            // Fallback to direct SSH for testing/development
            Log::info('Database SSH config not found, falling back to direct SSH', [
                'host' => $host,
                'error' => $e->getMessage(),
            ]);

            return $this->executeDirectSsh($host, $command, $useSudo);
        }
    }

    /**
     * Fallback method for direct SSH execution (testing/development)
     */
    protected function executeDirectSsh(string $host, string $command, bool $useSudo = true): array
    {
        // Build the SSH command using printf %q for proper quoting
        // This ensures complex commands with quotes, pipes, etc. work correctly
        $quotedCommand = escapeshellarg(
            sprintf('bash -c %s', escapeshellarg($command))
        );

        if ($useSudo) {
            $sshCommand = sprintf("ssh %s 'sudo -n bash -c %s'",
                escapeshellarg($host),
                escapeshellarg($command)
            );
        } else {
            $sshCommand = sprintf('ssh %s %s',
                escapeshellarg($host),
                $quotedCommand
            );
        }

        // Execute the command
        $output = [];
        $returnCode = 0;

        exec($sshCommand, $output, $returnCode);

        $success = $returnCode === 0;
        $outputString = implode("\n", $output);

        Log::info('Direct SSH execution', [
            'host' => $host,
            'command' => $command,
            'success' => $success,
            'return_code' => $returnCode,
        ]);

        return [
            'success' => $success,
            'output' => $outputString,
            'error' => $success ? null : "Command failed with return code: {$returnCode}",
            'return_code' => $returnCode,
        ];
    }

    /**
     * Sync shell environment to remote host
     */
    public function syncShellEnvironment(string $host): bool
    {
        if (! config('netserva-cli.remote.sync_shell_env', true)) {
            return true;
        }

        $localShellPath = str_replace('~', env('HOME'), $this->shellPath);

        if (! is_dir($localShellPath)) {
            Log::warning('Local shell environment not found', ['path' => $localShellPath]);

            return false;
        }

        try {
            // Use rsync to sync the shell environment
            // Personal config (~/.myrc) is outside ~/.rc/ so no exclusions needed
            $rsyncCommand = sprintf(
                'rsync -av --delete --exclude=.git %s/ %s:%s/',
                escapeshellarg($localShellPath),
                escapeshellarg($host),
                escapeshellarg($this->shellPath)
            );

            exec($rsyncCommand, $output, $returnCode);

            if ($returnCode === 0) {
                Log::info('Shell environment synced', [
                    'host' => $host,
                    'path' => $this->shellPath,
                ]);

                return true;
            } else {
                Log::error('Failed to sync shell environment', [
                    'host' => $host,
                    'command' => $rsyncCommand,
                    'output' => implode("\n", $output),
                    'return_code' => $returnCode,
                ]);

                return false;
            }

        } catch (Exception $e) {
            Log::error('Exception during shell environment sync', [
                'host' => $host,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Execute command with VHost environment sourced
     */
    public function executeWithVhostEnv(string $host, string $shost, string $vhost, string $command): array
    {
        try {
            // Load VHost configuration
            $vhostEnv = $this->vhostConfig->loadVhostConfig($shost, $vhost);

            // Build environment variables string
            $envVars = [];
            foreach ($vhostEnv as $key => $value) {
                $envVars[] = sprintf('export %s=%s', $key, escapeshellarg($value));
            }

            // Build compound command with environment setup
            $fullCommand = $this->buildEnvironmentCommand($envVars, $command);

            Log::info('Executing command with VHost environment', [
                'host' => $host,
                'shost' => $shost,
                'vhost' => $vhost,
                'command' => $command,
            ]);

            return $this->executeAsRoot($host, $fullCommand);

        } catch (Exception $e) {
            Log::error('Failed to execute command with VHost environment', [
                'host' => $host,
                'shost' => $shost,
                'vhost' => $vhost,
                'error' => $e->getMessage(),
            ]);

            return [
                'output' => "Error: {$e->getMessage()}",
                'exit_code' => 1,
                'success' => false,
            ];
        }
    }

    /**
     * Execute command with full NetServa environment
     */
    public function executeWithNsEnvironment(string $host, string $shost, string $vhost, string $command): array
    {
        // First sync shell environment if needed
        if (! $this->syncShellEnvironment($host)) {
            Log::warning('Shell environment sync failed, proceeding anyway', ['host' => $host]);
        }

        // Source shell functions and VHost environment
        $sourceCommands = [];

        if (config('netserva-cli.remote.source_shrc', true)) {
            $sourceCommands[] = "source {$this->shellPath}/_shrc";
        }

        // Add VHost environment
        try {
            $vhostEnv = $this->vhostConfig->loadVhostConfig($shost, $vhost);
            foreach ($vhostEnv as $key => $value) {
                $sourceCommands[] = sprintf('export %s=%s', $key, escapeshellarg($value));
            }
        } catch (Exception $e) {
            Log::warning('Could not load VHost environment', [
                'shost' => $shost,
                'vhost' => $vhost,
                'error' => $e->getMessage(),
            ]);
        }

        // Build compound command
        $fullCommand = $this->buildEnvironmentCommand($sourceCommands, $command);

        Log::info('Executing command with full NS environment', [
            'host' => $host,
            'shost' => $shost,
            'vhost' => $vhost,
            'command' => $command,
        ]);

        return $this->executeAsRoot($host, $fullCommand);
    }

    /**
     * Test if host supports root execution
     */
    public function testRootAccess(string $host): bool
    {
        $result = $this->executeAsRoot($host, 'whoami', false);

        if ($result['success']) {
            $user = trim($result['output']);
            Log::info('Root access test', ['host' => $host, 'user' => $user]);

            return $user === 'root';
        }

        // Try with sudo
        $result = $this->executeAsRoot($host, 'whoami', true);

        if ($result['success']) {
            $user = trim($result['output']);
            Log::info('Root access test via sudo', ['host' => $host, 'user' => $user]);

            return $user === 'root';
        }

        Log::warning('Root access test failed', [
            'host' => $host,
            'output' => $result['output'],
        ]);

        return false;
    }

    /**
     * Get file content from remote host
     */
    public function getRemoteFileContent(string $host, string $remotePath): ?string
    {
        $result = $this->executeAsRoot($host, "cat {$remotePath}");

        if ($result['success']) {
            return $result['output'];
        }

        Log::error('Failed to get remote file content', [
            'host' => $host,
            'path' => $remotePath,
            'output' => $result['output'],
        ]);

        return null;
    }

    /**
     * Put file content to remote host
     */
    public function putRemoteFileContent(string $host, string $remotePath, string $content): bool
    {
        // Use base64 encoding to safely transfer content
        $encodedContent = base64_encode($content);

        $command = sprintf(
            'echo %s | base64 -d > %s',
            escapeshellarg($encodedContent),
            escapeshellarg($remotePath)
        );

        $result = $this->executeAsRoot($host, $command);

        if ($result['success']) {
            Log::info('File content written to remote host', [
                'host' => $host,
                'path' => $remotePath,
                'size' => strlen($content),
            ]);

            return true;
        }

        Log::error('Failed to write file content to remote host', [
            'host' => $host,
            'path' => $remotePath,
            'output' => $result['output'],
        ]);

        return false;
    }

    /**
     * Create directory on remote host with proper permissions
     */
    public function createRemoteDirectory(string $host, string $path, string $mode = '755', ?string $owner = null): bool
    {
        $commands = [
            "mkdir -p {$path}",
            "chmod {$mode} {$path}",
        ];

        if ($owner) {
            $commands[] = "chown {$owner} {$path}";
        }

        foreach ($commands as $command) {
            $result = $this->executeAsRoot($host, $command);

            if (! $result['success']) {
                Log::error('Failed to create remote directory', [
                    'host' => $host,
                    'path' => $path,
                    'command' => $command,
                    'output' => $result['output'],
                ]);

                return false;
            }
        }

        Log::info('Remote directory created', [
            'host' => $host,
            'path' => $path,
            'mode' => $mode,
            'owner' => $owner,
        ]);

        return true;
    }

    /**
     * Check if remote path exists
     */
    public function remotePathExists(string $host, string $path): bool
    {
        $result = $this->executeAsRoot($host, "test -e {$path}");

        return $result['success'];
    }

    /**
     * Get remote file permissions
     */
    public function getRemotePermissions(string $host, string $path): ?string
    {
        $result = $this->executeAsRoot($host, "stat -c '%a' {$path}");

        if ($result['success']) {
            return trim($result['output']);
        }

        return null;
    }

    // Protected helper methods

    protected function buildEnvironmentCommand(array $sourceCommands, string $command): string
    {
        if (empty($sourceCommands)) {
            return $command;
        }

        // Join all source commands and the main command with &&
        $allCommands = array_merge($sourceCommands, [$command]);

        return implode(' && ', $allCommands);
    }

    protected function getConnectionUser(string $host): string
    {
        // Try to get user from SSH host configuration
        try {
            $ssh = $this->getConnection($host);

            // This would require access to the SSH2 connection internals
            // For now, return a default based on configuration
            return config('netserva-cli.ssh.default_user', 'root');
        } catch (Exception $e) {
            return 'root';
        }
    }

    /**
     * Execute multiple commands in sequence
     */
    public function executeSequence(string $host, array $commands, bool $stopOnError = true): array
    {
        $results = [];
        $allSuccess = true;

        foreach ($commands as $index => $command) {
            $result = $this->executeAsRoot($host, $command);
            $results[] = $result;

            if (! $result['success']) {
                $allSuccess = false;

                if ($stopOnError) {
                    Log::warning('Command sequence stopped due to error', [
                        'host' => $host,
                        'command_index' => $index,
                        'command' => $command,
                        'output' => $result['output'],
                    ]);
                    break;
                }
            }
        }

        return [
            'results' => $results,
            'success' => $allSuccess,
            'completed_commands' => count($results),
            'total_commands' => count($commands),
        ];
    }

    /**
     * Execute bash script via heredoc (RECOMMENDED METHOD)
     *
     * This is the preferred method for complex operations. Uses heredoc with quoted
     * delimiter to prevent local variable expansion and handle complex quoting.
     *
     * @param  string  $host  Host identifier
     * @param  string  $script  Bash script content
     * @param  array  $args  Arguments to pass to script ($1, $2, etc.)
     * @param  bool  $asRoot  Execute with root privileges
     * @param  bool  $dryRun  Show what would execute without running
     * @return array ['success' => bool, 'output' => string, 'return_code' => int]
     *
     * @example
     * $service->executeScript(
     *     host: 'markc',
     *     script: <<<'BASH'
     *         #!/bin/bash
     *         set -euo pipefail
     *         domain=$1
     *         path=$2
     *         echo "Processing: $domain at $path"
     *         BASH,
     *     args: ['example.com', '/srv/example.com'],
     *     asRoot: true
     * );
     */
    public function executeScript(
        string $host,
        string $script,
        array $args = [],
        bool $asRoot = true,
        bool $dryRun = false,
        bool $strictMode = true
    ): array {
        // Build script with set -euo pipefail for safety (unless disabled for interactive use)
        $safeScript = $strictMode ? $this->wrapScriptWithSafety($script) : $script;

        // Escape arguments for shell
        $escapedArgs = array_map('escapeshellarg', $args);
        $argsString = implode(' ', $escapedArgs);

        // Build heredoc command
        $sudoPrefix = $asRoot ? 'sudo -n ' : '';
        $heredocCommand = sprintf(
            "%sbash -s %s << 'NETSERVA_SCRIPT_EOF'\n%s\nNETSERVA_SCRIPT_EOF",
            $sudoPrefix,
            $argsString,
            $safeScript
        );

        if ($dryRun) {
            Log::info('DRY RUN: Would execute script on remote host', [
                'host' => $host,
                'script' => $safeScript,
                'args' => $args,
                'as_root' => $asRoot,
            ]);

            return [
                'success' => true,
                'output' => '[DRY RUN] Script would execute with args: '.implode(', ', $args),
                'return_code' => 0,
                'dry_run' => true,
            ];
        }

        // Execute via SSH using heredoc
        try {
            $connection = $this->getConnection($host);

            Log::info('Executing script on remote host via heredoc', [
                'host' => $host,
                'args' => $args,
                'as_root' => $asRoot,
                'script_lines' => substr_count($safeScript, "\n") + 1,
            ]);

            // Build heredoc command string (phpseclib stdin via file handle is unreliable)
            $escapedScript = addcslashes($safeScript, '\\$`');
            $heredocCmd = "$sudoPrefix bash -s $argsString <<'NETSERVA_EOF'\n{$safeScript}\nNETSERVA_EOF";

            // Execute script via heredoc
            $output = $connection->exec($heredocCmd);
            $exitCode = $connection->getExitStatus();

            // Get stderr separately if needed
            $stderr = $connection->getStdError();

            // Handle case where getExitStatus() returns false (connection issue)
            if ($exitCode === false || $exitCode === null) {
                $exitCode = 255;
                $success = false;
                $errorMsg = 'Script execution failed - no exit code returned (possible connection issue)';
            } else {
                $success = $exitCode === 0;
                $errorMsg = $success ? null : "Script failed with exit code: $exitCode";
            }

            Log::info('Script execution completed', [
                'host' => $host,
                'success' => $success,
                'exit_code' => $exitCode,
                'output_length' => strlen($output),
                'output_preview' => substr($output, 0, 200),
                'stderr' => $stderr ? substr($stderr, 0, 200) : null,
            ]);

            return [
                'success' => $success,
                'output' => $output,
                'return_code' => $exitCode,
                'error' => $errorMsg,
            ];

        } catch (Exception $e) {
            Log::error('Script execution failed', [
                'host' => $host,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'output' => '',
                'return_code' => 255,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute script with FleetVhost environment variables
     *
     * Automatically injects all environment variables from FleetVhost model
     * into the script execution context.
     *
     * @param  string  $host  Host identifier
     * @param  \NetServa\Fleet\Models\FleetVhost  $vhost  VHost model with environment vars
     * @param  string  $script  Bash script content
     * @param  bool  $asRoot  Execute with root privileges
     * @return array Execution result
     */
    public function executeScriptWithVhost(
        string $host,
        $vhost,  // FleetVhost model
        string $script,
        bool $asRoot = true
    ): array {
        // Build environment variable exports
        $envExports = [];
        if ($vhost->environment_vars) {
            foreach ($vhost->environment_vars as $key => $value) {
                $envExports[] = sprintf('export %s=%s', $key, escapeshellarg($value));
            }
        }

        // Prepend environment exports to script
        $fullScript = implode("\n", $envExports)."\n\n".$script;

        Log::info('Executing script with VHost environment', [
            'host' => $host,
            'vhost' => $vhost->domain,
            'env_vars_count' => count($vhost->environment_vars ?? []),
        ]);

        return $this->executeScript(
            host: $host,
            script: $fullScript,
            args: [],
            asRoot: $asRoot
        );
    }

    /**
     * Wrap script with safety directives
     */
    protected function wrapScriptWithSafety(string $script): string
    {
        // Check if script already has shebang
        $hasShebang = str_starts_with(trim($script), '#!');

        // Check if script already has set -euo pipefail
        $hasSafetySet = str_contains($script, 'set -euo pipefail') ||
                        str_contains($script, 'set -e');

        if ($hasShebang && $hasSafetySet) {
            return $script;  // Script already properly configured
        }

        $safety = [];

        if (! $hasShebang) {
            $safety[] = '#!/bin/bash';
        }

        if (! $hasSafetySet) {
            $safety[] = 'set -euo pipefail';
        }

        if (empty($safety)) {
            return $script;
        }

        return implode("\n", $safety)."\n\n".$script;
    }

    /**
     * Detect OS information from remote /etc/os-release
     *
     * Returns parsed OS information including ID, VERSION_ID, VERSION_CODENAME, etc.
     *
     * @param  string  $host  Host identifier
     * @return array|null OS info array or null if detection failed
     *
     * @example
     * $osInfo = $service->detectRemoteOs('markc');
     * // Returns: ['ID' => 'debian', 'VERSION_ID' => '13', 'VERSION_CODENAME' => 'trixie', ...]
     */
    public function detectRemoteOs(string $host): ?array
    {
        $result = $this->executeAsRoot($host, 'cat /etc/os-release', false);

        if (! $result['success']) {
            Log::warning('Failed to read /etc/os-release from remote host', [
                'host' => $host,
                'error' => $result['error'] ?? 'Unknown error',
            ]);

            return null;
        }

        // Parse os-release format (KEY="VALUE" or KEY=VALUE)
        $osInfo = [];
        $lines = explode("\n", $result['output']);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Parse KEY=VALUE or KEY="VALUE"
            if (preg_match('/^([A-Z_]+)=(.*)$/', $line, $matches)) {
                $key = $matches[1];
                $value = $matches[2];

                // Remove quotes if present
                $value = trim($value, '"\'');

                $osInfo[$key] = $value;
            }
        }

        Log::info('Detected OS information from remote host', [
            'host' => $host,
            'os_id' => $osInfo['ID'] ?? 'unknown',
            'os_version' => $osInfo['VERSION_ID'] ?? 'unknown',
            'os_codename' => $osInfo['VERSION_CODENAME'] ?? 'unknown',
        ]);

        return $osInfo;
    }

    /**
     * Get NetServa-compatible OS variables from /etc/os-release
     *
     * Maps os-release data to OSTYP, OSREL, OSMIR variables
     *
     * @param  string  $host  Host identifier
     * @return array ['OSTYP' => 'debian', 'OSREL' => 'trixie', 'OSMIR' => 'deb.debian.org']
     */
    public function getOsVariables(string $host): array
    {
        $osInfo = $this->detectRemoteOs($host);

        if (! $osInfo) {
            return [
                'OSTYP' => 'unknown',
                'OSREL' => 'unknown',
                'OSMIR' => 'unknown',
            ];
        }

        $osId = $osInfo['ID'] ?? 'unknown';
        $osCodename = $osInfo['VERSION_CODENAME'] ?? ($osInfo['VERSION_ID'] ?? 'unknown');

        // Map OS ID to OSTYP and OSMIR
        $osTyp = $osId;
        $osRel = $osCodename;
        $osMir = $this->getDefaultMirrorForOs($osId);

        return [
            'OSTYP' => $osTyp,
            'OSREL' => $osRel,
            'OSMIR' => $osMir,
        ];
    }

    /**
     * Get default mirror for OS type
     */
    protected function getDefaultMirrorForOs(string $osType): string
    {
        return match ($osType) {
            'debian' => 'deb.debian.org',
            'ubuntu' => 'archive.ubuntu.com',
            'alpine' => 'dl-cdn.alpinelinux.org',
            'arch' => 'archlinux.org',
            'cachyos' => 'archlinux.cachyos.org',
            'manjaro' => 'manjaro.moson.eu',
            default => 'unknown',
        };
    }
}
