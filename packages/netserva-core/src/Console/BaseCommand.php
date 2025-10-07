<?php

namespace NetServa\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use NetServa\Core\Services\RemoteConnectionService;

/**
 * NetServa Core Base Console Command
 *
 * Base class for all NetServa console commands with common functionality.
 * Part of the NetServa Core foundation package.
 */
abstract class BaseCommand extends Command
{
    /**
     * The command description text
     */
    protected $description = 'NetServa command';

    /**
     * Default verbosity level
     */
    protected bool $verbose = false;

    /**
     * Default quiet mode
     */
    protected bool $quiet = false;

    /**
     * Dry run mode
     */
    protected bool $dryRun = false;

    /**
     * Remote connection service
     */
    protected ?RemoteConnectionService $remote = null;

    /**
     * Common command options
     */
    protected function getCommonOptions(): array
    {
        return [
            ['verbose', 'v', null, 'Enable verbose output'],
            ['quiet', 'q', null, 'Suppress all output except errors'],
            ['dry-run', null, null, 'Show what would be done without executing'],
            ['timeout', null, 'value', 'Operation timeout in seconds', 300],
        ];
    }

    /**
     * Initialize common command functionality
     */
    protected function initializeCommand(): void
    {
        $this->verbose = $this->option('verbose') ?? false;
        $this->quiet = $this->option('quiet') ?? false;
        $this->dryRun = $this->option('dry-run') ?? false;

        $this->remote = app(RemoteConnectionService::class);

        // Set log level based on verbosity
        if ($this->verbose) {
            Log::getMonolog()->pushHandler(new \Monolog\Handler\StreamHandler('php://stdout', \Monolog\Level::Debug));
        }
    }

    /**
     * Execute the command
     */
    public function handle(): int
    {
        $this->initializeCommand();

        try {
            return $this->executeCommand();
        } catch (\Exception $e) {
            $this->logError('Command failed', ['error' => $e->getMessage()]);
            $this->error("Command failed: {$e->getMessage()}");

            if ($this->verbose) {
                $this->error($e->getTraceAsString());
            }

            return 1;
        }
    }

    /**
     * Main command execution logic - to be implemented by subclasses
     */
    protected function executeCommand(): int
    {
        throw new \RuntimeException('executeCommand() method must be implemented by subclass');
    }

    /**
     * Output information message
     */
    public function info($string, $verbosity = null): void
    {
        if (! $this->quiet) {
            parent::info($string, $verbosity);
            $this->logInfo($string);
        }
    }

    /**
     * Output comment message
     */
    public function comment($string, $verbosity = null): void
    {
        if (! $this->quiet) {
            parent::comment($string, $verbosity);
            $this->logInfo($string);
        }
    }

    /**
     * Output warning message
     */
    public function warn($string, $verbosity = null): void
    {
        if (! $this->quiet) {
            parent::warn($string, $verbosity);
            $this->logWarning($string);
        }
    }

    /**
     * Output error message
     */
    public function error($string, $verbosity = null): void
    {
        parent::error($string, $verbosity);
        $this->logError($string);
    }

    /**
     * Output success message with emoji
     */
    protected function success(string $message): void
    {
        if (! $this->quiet) {
            $this->line("<fg=green>✅ {$message}</>");
            $this->logInfo($message);
        }
    }

    /**
     * Output failure message with emoji
     */
    protected function failure(string $message): void
    {
        $this->line("<fg=red>❌ {$message}</>");
        $this->logError($message);
    }

    /**
     * Output warning message with emoji
     */
    protected function warning(string $message): void
    {
        if (! $this->quiet) {
            $this->line("<fg=yellow>⚠️ {$message}</>");
            $this->logWarning($message);
        }
    }

    /**
     * Execute remote command on host
     */
    protected function executeRemote(string $host, string $command): array
    {
        if ($this->dryRun) {
            $this->comment("DRY RUN: Would execute on {$host}: {$command}");

            return ['output' => "DRY RUN: {$command}", 'exit_code' => 0];
        }

        $this->logInfo('Executing remote command', [
            'host' => $host,
            'command' => $command,
        ]);

        return $this->remote->exec($host, $command);
    }

    /**
     * Transfer file to remote host
     */
    protected function transferFile(string $host, string $localPath, string $remotePath): bool
    {
        if ($this->dryRun) {
            $this->comment("DRY RUN: Would transfer {$localPath} to {$host}:{$remotePath}");

            return true;
        }

        $this->logInfo('Transferring file', [
            'host' => $host,
            'local_path' => $localPath,
            'remote_path' => $remotePath,
        ]);

        return $this->remote->putFile($host, $localPath, $remotePath);
    }

    /**
     * Download file from remote host
     */
    protected function downloadFile(string $host, string $remotePath, string $localPath): bool
    {
        if ($this->dryRun) {
            $this->comment("DRY RUN: Would download {$host}:{$remotePath} to {$localPath}");

            return true;
        }

        $this->logInfo('Downloading file', [
            'host' => $host,
            'remote_path' => $remotePath,
            'local_path' => $localPath,
        ]);

        return $this->remote->getFile($host, $remotePath, $localPath);
    }

    /**
     * Create progress bar
     */
    protected function createProgressBar(int $max = 0): \Symfony\Component\Console\Helper\ProgressBar
    {
        $progressBar = $this->output->createProgressBar($max);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');

        return $progressBar;
    }

    /**
     * Log info message
     */
    protected function logInfo(string $message, array $context = []): void
    {
        Log::info($message, array_merge(['command' => $this->getName()], $context));
    }

    /**
     * Log warning message
     */
    protected function logWarning(string $message, array $context = []): void
    {
        Log::warning($message, array_merge(['command' => $this->getName()], $context));
    }

    /**
     * Log error message
     */
    protected function logError(string $message, array $context = []): void
    {
        Log::error($message, array_merge(['command' => $this->getName()], $context));
    }

    /**
     * Format elapsed time
     */
    protected function formatElapsedTime(float $start): string
    {
        $elapsed = microtime(true) - $start;

        if ($elapsed < 1) {
            return number_format($elapsed * 1000, 0).'ms';
        }

        if ($elapsed < 60) {
            return number_format($elapsed, 1).'s';
        }

        $minutes = floor($elapsed / 60);
        $seconds = $elapsed % 60;

        return $minutes.'m '.number_format($seconds, 1).'s';
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $size, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, $precision).' '.$units[$i];
    }

    /**
     * Validate host accessibility
     */
    protected function validateHost(string $host): bool
    {
        try {
            $connection = $this->remote->getConnection($host);

            return $connection->isConnected();
        } catch (\Exception $e) {
            $this->logError('Host validation failed', [
                'host' => $host,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get confirmation for destructive operations
     */
    protected function confirmDestructive(string $message, bool $default = false): bool
    {
        if ($this->dryRun) {
            return true;
        }

        return $this->confirm($message, $default);
    }

    /**
     * Display table of results
     */
    protected function displayTable(array $headers, array $rows): void
    {
        if (! $this->quiet && ! empty($rows)) {
            $this->table($headers, $rows);
        }
    }
}
