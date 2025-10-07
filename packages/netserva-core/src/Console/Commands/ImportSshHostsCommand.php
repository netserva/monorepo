<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use NetServa\Core\Models\SshHost;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

/**
 * Import SSH Host Configuration Files
 *
 * Reads ~/.ssh/hosts/* files and imports them into the ssh_hosts table
 */
class ImportSshHostsCommand extends Command
{
    protected $signature = 'ssh:import
                            {--dir= : Directory containing SSH host files (default: ~/.ssh/hosts)}
                            {--dry-run : Show what would be imported without saving}
                            {--force : Overwrite existing entries}';

    protected $description = 'Import SSH host configuration files from ~/.ssh/hosts/* into database';

    public function handle(): int
    {
        $sshHostsDir = $this->option('dir') ?? $_SERVER['HOME'].'/.ssh/hosts';

        if (! File::isDirectory($sshHostsDir)) {
            error("SSH hosts directory not found: {$sshHostsDir}");

            return self::FAILURE;
        }

        info("Importing SSH hosts from: {$sshHostsDir}");

        $files = File::files($sshHostsDir);
        $imported = 0;
        $skipped = 0;
        $updated = 0;
        $errors = [];

        if (empty($files)) {
            warning('No SSH host files found');

            return self::SUCCESS;
        }

        info('Found '.count($files).' SSH host configuration files');

        $progressBar = progress(label: 'Processing SSH host files', steps: count($files));
        $progressBar->start();

        foreach ($files as $file) {
            $progressBar->advance();

            $hostAlias = $file->getFilenameWithoutExtension();

            // Skip special files
            if (in_array($hostAlias, ['config', 'known_hosts', 'authorized_keys', '.', '..'])) {
                continue;
            }

            try {
                $config = $this->parseSshConfigFile($file->getPathname());

                if (! $config) {
                    $errors[] = "Failed to parse: {$hostAlias}";
                    $skipped++;

                    continue;
                }

                // Check if host already exists
                $existingSshHost = SshHost::where('host', $hostAlias)->first();

                if ($existingSshHost && ! $this->option('force')) {
                    $skipped++;

                    continue;
                }

                if ($this->option('dry-run')) {
                    $this->line("Would import: {$hostAlias} -> {$config['hostname']}");

                    continue;
                }

                if ($existingSshHost) {
                    $existingSshHost->update($config);
                    $updated++;
                } else {
                    SshHost::create(array_merge(['host' => $hostAlias], $config));
                    $imported++;
                }
            } catch (\Exception $e) {
                $errors[] = "{$hostAlias}: {$e->getMessage()}";
                $skipped++;
            }
        }

        $progressBar->finish();

        // Show summary
        info("\n✅ SSH Host Import Complete:");
        info("  Imported: {$imported}");
        info("  Updated: {$updated}");
        info("  Skipped: {$skipped}");

        if (! empty($errors)) {
            warning("\n⚠️  Errors encountered:");
            foreach ($errors as $err) {
                $this->line("  • {$err}");
            }
        }

        // Show next steps
        if ($imported > 0 || $updated > 0) {
            info("\n🔧 Next steps:");
            info('  1. Run: php artisan fleet:infra-discovery analyze');
            info('  2. Run: php artisan fleet:infra-discovery classify');
        }

        return self::SUCCESS;
    }

    /**
     * Parse SSH config file and extract connection details
     */
    protected function parseSshConfigFile(string $filePath): ?array
    {
        $content = File::get($filePath);
        $lines = explode("\n", $content);

        $config = [
            'hostname' => null,
            'port' => 22,
            'user' => 'root',
            'identity_file' => null,
            'proxy_command' => null,
            'jump_host' => null,
            'custom_options' => [],
            'is_active' => true,
        ];

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and empty lines
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Parse key-value pairs
            if (preg_match('/^(\w+)\s+(.+)$/', $line, $matches)) {
                $key = strtolower($matches[1]);
                $value = trim($matches[2]);

                switch ($key) {
                    case 'hostname':
                        $config['hostname'] = $value;
                        break;
                    case 'port':
                        $config['port'] = (int) $value;
                        break;
                    case 'user':
                        $config['user'] = $value;
                        break;
                    case 'identityfile':
                        $config['identity_file'] = $value;
                        break;
                    case 'proxycommand':
                        $config['proxy_command'] = $value;
                        break;
                    case 'proxyjump':
                        $config['jump_host'] = $value;
                        break;
                    default:
                        // Store other options
                        $config['custom_options'][$key] = $value;
                        break;
                }
            }
        }

        // Hostname is required
        if (! $config['hostname']) {
            return null;
        }

        return $config;
    }
}
