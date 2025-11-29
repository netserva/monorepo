<?php

namespace NetServa\Core\Console\Commands;

use NetServa\Core\Services\VHostResolverService;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVhostCredential;

use function Laravel\Prompts\info;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * Import Credentials from .conf files - NetServa 3.0 Database-First
 *
 * Import email accounts and other credentials from legacy .conf files
 * Supports various formats including postfix virtual mail files
 */
class ImportCredentialsCommand extends BaseNetServaCommand
{
    protected $signature = 'import:credentials {file : Path to .conf file to import}
                           {domain? : Target domain (auto-detected if not provided)}
                           {--format=auto : File format (auto|postfix|dovecot|generic)}
                           {--service=mail : Default service type for imported credentials}
                           {--dry-run : Show what would be imported without actually importing}
                           {--force : Skip confirmation prompts}
                           {--replace : Replace existing credentials with same account name}';

    protected $description = 'Import credentials from .conf files into database (NetServa 3.0 Database-First)';

    protected VHostResolverService $resolver;

    public function __construct(VHostResolverService $resolver)
    {
        parent::__construct();
        $this->resolver = $resolver;
    }

    public function handle(): int
    {
        return $this->executeWithContext(function () {
            $filePath = $this->argument('file');
            $domain = $this->argument('domain');
            $format = $this->option('format');
            $serviceType = $this->option('service');

            // Validate file exists
            if (! file_exists($filePath)) {
                $this->error("❌ File not found: {$filePath}");

                return 1;
            }

            // Read and parse the file
            $this->line("📁 Reading file: {$filePath}");
            $fileContent = file_get_contents($filePath);

            if (empty($fileContent)) {
                $this->error('❌ File is empty or unreadable');

                return 1;
            }

            // Auto-detect domain from filename if not provided
            if (! $domain) {
                $domain = $this->detectDomainFromFile($filePath);
                if ($domain) {
                    $this->line("🔍 Auto-detected domain: <fg=green>{$domain}</fg=green>");
                } else {
                    $domain = text('Enter the target domain for these credentials', required: true);
                }
            }

            // Auto-detect format if needed
            if ($format === 'auto') {
                $format = $this->detectFileFormat($fileContent, $filePath);
                $this->line("🔍 Auto-detected format: <fg=yellow>{$format}</fg=yellow>");
            }

            // Parse credentials based on format
            $credentials = $this->parseCredentials($fileContent, $format, $serviceType);

            if (empty($credentials)) {
                warning('No credentials found in file');

                return 0;
            }

            $this->line('📊 Found <fg=cyan>'.count($credentials).'</fg=cyan> credentials to import');

            // Resolve target VHost
            try {
                $context = $this->resolver->resolveVHost($domain);
                $vhost = FleetVhost::where('domain', $context['vhost'])
                    ->whereHas('vnode', function ($q) use ($context) {
                        $q->where('name', $context['vnode']);
                    })->first();

                if (! $vhost) {
                    $this->error("❌ VHost {$context['vhost']} not found in database");
                    $this->line("   Use 'addvhost' to create the VHost first");

                    return 1;
                }

                $this->line("🔍 Target: <fg=cyan>{$context['vsite']}</fg=cyan>/<fg=yellow>{$context['vnode']}</fg=yellow>/<fg=green>{$context['vhost']}</fg=green>");

                return $this->importCredentials($vhost, $credentials);

            } catch (\Exception $e) {
                $this->error("❌ {$e->getMessage()}");

                return 1;
            }
        });
    }

    /**
     * Detect domain from filename
     */
    protected function detectDomainFromFile(string $filePath): ?string
    {
        $filename = basename($filePath);

        // Remove common extensions
        $filename = preg_replace('/\.(conf|txt|cfg|config)$/', '', $filename);

        // Check if filename looks like a domain
        if (preg_match('/^([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}$/', $filename)) {
            return $filename;
        }

        return null;
    }

    /**
     * Auto-detect file format
     */
    protected function detectFileFormat(string $content, string $filePath): string
    {
        // Check for common postfix virtual patterns (email email password)
        if (preg_match('/^\s*[^@\s]+@[^@\s]+\s+[^@\s]+@[^@\s]+\s+\S+\s*$/m', $content)) {
            return 'postfix';
        }

        // Check for dovecot user patterns
        if (preg_match('/^\s*[^@\s]+@[^@\s]+:\{[A-Z0-9-]+\}/m', $content)) {
            return 'dovecot';
        }

        // Check for key=value patterns
        if (preg_match('/^\s*\w+\s*=\s*.+$/m', $content)) {
            return 'generic';
        }

        // Default to postfix if we see email patterns
        if (preg_match('/^\s*[^@\s]+@[^@\s]+/m', $content)) {
            return 'postfix';
        }

        return 'generic';
    }

    /**
     * Parse credentials from file content
     */
    protected function parseCredentials(string $content, string $format, string $defaultService): array
    {
        switch ($format) {
            case 'postfix':
                return $this->parsePostfixFormat($content, $defaultService);
            case 'dovecot':
                return $this->parseDovecotFormat($content, $defaultService);
            case 'generic':
            default:
                return $this->parseGenericFormat($content, $defaultService);
        }
    }

    /**
     * Parse postfix virtual format: user@domain.com user@domain.com
     */
    protected function parsePostfixFormat(string $content, string $defaultService): array
    {
        $credentials = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineNum => $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Parse email alias format
            if (preg_match('/^([^@\s]+@[^@\s]+)\s+([^@\s]+@[^@\s]+)(?:\s+(.+))?$/', $line, $matches)) {
                $email = $matches[1];
                $target = $matches[2];
                $password = $matches[3] ?? $this->generateSecurePassword();

                $credentials[] = [
                    'service_type' => $defaultService,
                    'account_name' => $email,
                    'username' => $email,
                    'password' => $password,
                    'notes' => 'Imported from line '.($lineNum + 1)." (target: {$target})",
                    'line_number' => $lineNum + 1,
                ];
            }
        }

        return $credentials;
    }

    /**
     * Parse dovecot format: user@domain.com:{HASH}password
     */
    protected function parseDovecotFormat(string $content, string $defaultService): array
    {
        $credentials = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineNum => $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Parse dovecot user format
            if (preg_match('/^([^@\s]+@[^@\s]+):\{([A-Z0-9-]+)\}(.+)$/', $line, $matches)) {
                $email = $matches[1];
                $hashType = $matches[2];
                $hashedPassword = $matches[3];

                $credentials[] = [
                    'service_type' => $defaultService,
                    'account_name' => $email,
                    'username' => $email,
                    'password' => "HASHED:{$hashType}:{$hashedPassword}",
                    'notes' => 'Imported from line '.($lineNum + 1)." (dovecot {$hashType} hash)",
                    'line_number' => $lineNum + 1,
                ];
            }
        }

        return $credentials;
    }

    /**
     * Parse generic key=value format
     */
    protected function parseGenericFormat(string $content, string $defaultService): array
    {
        $credentials = [];
        $lines = explode("\n", $content);
        $currentCredential = [];

        foreach ($lines as $lineNum => $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || str_starts_with($line, '#')) {
                // If we have a partial credential, save it
                if (! empty($currentCredential)) {
                    $credentials[] = $this->finalizeGenericCredential($currentCredential, $lineNum, $defaultService);
                    $currentCredential = [];
                }

                continue;
            }

            // Parse key=value pairs
            if (preg_match('/^(\w+)\s*=\s*(.+)$/', $line, $matches)) {
                $key = strtolower($matches[1]);
                $value = trim($matches[2], '"\'');

                switch ($key) {
                    case 'user':
                    case 'email':
                    case 'account':
                        $currentCredential['account_name'] = $value;
                        $currentCredential['username'] = $value;
                        break;
                    case 'password':
                    case 'pass':
                        $currentCredential['password'] = $value;
                        break;
                    case 'service':
                    case 'type':
                        $currentCredential['service_type'] = $value;
                        break;
                    case 'url':
                        $currentCredential['url'] = $value;
                        break;
                    case 'port':
                        $currentCredential['port'] = (int) $value;
                        break;
                    case 'notes':
                    case 'comment':
                        $currentCredential['notes'] = $value;
                        break;
                }
                $currentCredential['line_number'] = $lineNum + 1;
            }
        }

        // Don't forget the last credential
        if (! empty($currentCredential)) {
            $credentials[] = $this->finalizeGenericCredential($currentCredential, count($lines), $defaultService);
        }

        return $credentials;
    }

    /**
     * Finalize a generic credential with defaults
     */
    protected function finalizeGenericCredential(array $credential, int $lineNum, string $defaultService): array
    {
        return [
            'service_type' => $credential['service_type'] ?? $defaultService,
            'account_name' => $credential['account_name'] ?? "unknown_{$lineNum}",
            'username' => $credential['username'] ?? $credential['account_name'] ?? "unknown_{$lineNum}",
            'password' => $credential['password'] ?? $this->generateSecurePassword(),
            'url' => $credential['url'] ?? null,
            'port' => $credential['port'] ?? null,
            'notes' => ($credential['notes'] ?? '')." (Imported from line {$lineNum})",
            'line_number' => $lineNum,
        ];
    }

    /**
     * Import credentials into database
     */
    protected function importCredentials(FleetVhost $vhost, array $credentials): int
    {
        if ($this->option('dry-run')) {
            $this->displayImportPreview($credentials);

            return 0;
        }

        // Check for existing credentials
        $existingCount = 0;
        $existingAccounts = [];

        foreach ($credentials as $cred) {
            $existing = FleetVhostCredential::where('vhost_id', $vhost->id)
                ->where('service_type', $cred['service_type'])
                ->where('account_name', $cred['account_name'])
                ->first();

            if ($existing) {
                $existingCount++;
                $existingAccounts[] = $cred['account_name'];
            }
        }

        if ($existingCount > 0 && ! $this->option('replace') && ! $this->option('force')) {
            $this->line('');
            $this->line("<fg=yellow>⚠️  Found {$existingCount} existing credentials:</fg=yellow>");
            foreach (array_slice($existingAccounts, 0, 5) as $account) {
                $this->line("   • {$account}");
            }
            if (count($existingAccounts) > 5) {
                $this->line('   • ... and '.(count($existingAccounts) - 5).' more');
            }

            $this->line('');
            $action = select(
                'How should existing credentials be handled?',
                [
                    'skip' => 'Skip existing credentials (import only new ones)',
                    'replace' => 'Replace existing credentials with imported data',
                    'cancel' => 'Cancel import operation',
                ],
                'skip'
            );

            if ($action === 'cancel') {
                info('Import cancelled');

                return 0;
            }

            $replace = ($action === 'replace');
        } else {
            $replace = $this->option('replace');
        }

        // Perform import
        $imported = 0;
        $skipped = 0;
        $errors = 0;

        $this->line('');
        $progress = progress(
            label: 'Importing credentials',
            steps: count($credentials)
        );

        $progress->start();

        foreach ($credentials as $cred) {
            try {
                $existing = FleetVhostCredential::where('vhost_id', $vhost->id)
                    ->where('service_type', $cred['service_type'])
                    ->where('account_name', $cred['account_name'])
                    ->first();

                if ($existing && ! $replace) {
                    $skipped++;
                    $progress->advance();

                    continue;
                }

                // Remove line_number from the data before saving
                unset($cred['line_number']);

                FleetVhostCredential::createOrUpdateCredential(
                    $vhost->id,
                    $cred['service_type'],
                    $cred['account_name'],
                    $cred
                );

                $imported++;
            } catch (\Exception $e) {
                $errors++;
                $this->line('');
                $this->line("<fg=red>❌ Error importing {$cred['account_name']}: {$e->getMessage()}</fg=red>");
            }

            $progress->advance();
        }

        $progress->finish();

        // Summary
        $this->line('');
        $this->info("✅ Import complete for {$vhost->domain}");
        $this->line("   Imported: <fg=green>{$imported}</fg=green>");
        if ($skipped > 0) {
            $this->line("   Skipped: <fg=yellow>{$skipped}</fg=yellow>");
        }
        if ($errors > 0) {
            $this->line("   Errors: <fg=red>{$errors}</fg=red>");
        }

        return $errors > 0 ? 1 : 0;
    }

    /**
     * Display import preview for dry run
     */
    protected function displayImportPreview(array $credentials): void
    {
        $this->line('');
        $this->line('<fg=blue>📋 Import Preview:</fg=blue>');
        $this->line('');

        $serviceGroups = [];
        foreach ($credentials as $cred) {
            $serviceGroups[$cred['service_type']][] = $cred;
        }

        foreach ($serviceGroups as $service => $creds) {
            $serviceLabel = FleetVhostCredential::SERVICE_TYPES[$service] ?? $service;
            $this->line("<fg=yellow>{$serviceLabel} ({$service}):</fg=yellow>");

            foreach (array_slice($creds, 0, 5) as $cred) {
                $this->line("   • {$cred['account_name']} (line {$cred['line_number']})");
            }

            if (count($creds) > 5) {
                $this->line('   • ... and '.(count($creds) - 5).' more');
            }
            $this->line('');
        }

        $this->line('Total credentials to import: <fg=cyan>'.count($credentials).'</fg=cyan>');
    }

    /**
     * Generate secure password
     */
    protected function generateSecurePassword(int $length = 12): string
    {
        $charset = 'ABCDEFGHIJKLMNPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $upperChars = 'ABCDEFGHIJKLMNPQRSTUVWXYZ';
        $lowerChars = 'abcdefghijklmnopqrstuvwxyz';
        $digitChars = '0123456789';

        $upperChar = $upperChars[random_int(0, strlen($upperChars) - 1)];
        $lowerChar = $lowerChars[random_int(0, strlen($lowerChars) - 1)];
        $digitChar = $digitChars[random_int(0, strlen($digitChars) - 1)];

        $remainingLength = $length - 3;
        $rest = '';

        for ($i = 0; $i < $remainingLength; $i++) {
            $rest .= $charset[random_int(0, strlen($charset) - 1)];
        }

        $combined = $upperChar.$lowerChar.$digitChar.$rest;

        return str_shuffle($combined);
    }
}
