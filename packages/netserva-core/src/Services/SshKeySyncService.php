<?php

declare(strict_types=1);

namespace NetServa\Core\Services;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use NetServa\Core\Models\SshKey;

/**
 * SSH Key Sync Service
 *
 * Manages synchronization between database SshKey records
 * and filesystem SSH key files in ~/.ssh/keys/
 *
 * Also provides key generation functionality.
 */
class SshKeySyncService
{
    protected string $keysDir;

    protected string $sshDir;

    public function __construct()
    {
        $home = env('HOME', '/home/'.get_current_user());
        $this->sshDir = "{$home}/.ssh";
        $this->keysDir = "{$this->sshDir}/keys";
    }

    /**
     * Sync a single key to filesystem
     */
    public function syncKey(SshKey $key): bool
    {
        if (! $key->is_active) {
            // Remove key files if inactive
            return $this->deleteKeyFiles($key->name);
        }

        if (! $key->private_key || ! $key->public_key) {
            Log::warning('Cannot sync SSH key without content', [
                'name' => $key->name,
            ]);

            return false;
        }

        try {
            $this->ensureDirectoryExists();

            $privateKeyPath = "{$this->keysDir}/{$key->name}";
            $publicKeyPath = "{$this->keysDir}/{$key->name}.pub";

            // Write private key with strict permissions
            File::put($privateKeyPath, $key->private_key);
            chmod($privateKeyPath, 0600);

            // Write public key
            File::put($publicKeyPath, $key->public_key);
            chmod($publicKeyPath, 0644);

            Log::info('SSH key synced to filesystem', [
                'name' => $key->name,
                'path' => $privateKeyPath,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to sync SSH key', [
                'name' => $key->name,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Delete key files from filesystem
     */
    public function deleteKeyFiles(string $keyName): bool
    {
        $privateKeyPath = "{$this->keysDir}/{$keyName}";
        $publicKeyPath = "{$this->keysDir}/{$keyName}.pub";

        try {
            if (File::exists($privateKeyPath)) {
                File::delete($privateKeyPath);
            }
            if (File::exists($publicKeyPath)) {
                File::delete($publicKeyPath);
            }

            Log::info('SSH key files deleted from filesystem', [
                'name' => $keyName,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to delete SSH key files', [
                'name' => $keyName,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Sync all active keys to filesystem
     */
    public function syncAllKeys(): array
    {
        $results = ['synced' => 0, 'failed' => 0, 'errors' => []];

        $keys = SshKey::where('is_active', true)->get();

        foreach ($keys as $key) {
            if ($this->syncKey($key)) {
                $results['synced']++;
            } else {
                $results['failed']++;
                $results['errors'][] = $key->name;
            }
        }

        Log::info('SSH keys sync completed', $results);

        return $results;
    }

    /**
     * Import keys from filesystem into database
     */
    public function importFromFilesystem(): array
    {
        $results = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        if (! File::isDirectory($this->keysDir)) {
            return $results;
        }

        $files = File::files($this->keysDir);

        // Get unique key names (without .pub extension)
        $keyNames = collect($files)
            ->map(fn ($file) => $file->getFilename())
            ->filter(fn ($name) => ! str_starts_with($name, '.'))
            ->map(fn ($name) => str_ends_with($name, '.pub') ? substr($name, 0, -4) : $name)
            ->unique()
            ->values();

        foreach ($keyNames as $keyName) {
            // Check if key already exists
            if (SshKey::where('name', $keyName)->exists()) {
                $results['skipped']++;

                continue;
            }

            $privateKeyPath = "{$this->keysDir}/{$keyName}";
            $publicKeyPath = "{$this->keysDir}/{$keyName}.pub";

            // Must have at least private key
            if (! File::exists($privateKeyPath)) {
                continue;
            }

            try {
                $privateKey = trim(File::get($privateKeyPath));
                $publicKey = File::exists($publicKeyPath) ? trim(File::get($publicKeyPath)) : null;

                $keyData = [
                    'name' => $keyName,
                    'private_key' => $privateKey,
                    'public_key' => $publicKey,
                    'type' => $this->detectKeyType($privateKey),
                    'is_active' => true,
                ];

                // Extract comment from public key
                if ($publicKey) {
                    $keyData['comment'] = $this->extractCommentFromPublicKey($publicKey);
                    $keyData['fingerprint'] = $this->getFingerprint($publicKeyPath);
                }

                SshKey::create($keyData);
                $results['imported']++;

                Log::info('SSH key imported from filesystem', [
                    'name' => $keyName,
                ]);
            } catch (Exception $e) {
                $results['errors'][] = "{$keyName}: {$e->getMessage()}";
            }
        }

        return $results;
    }

    /**
     * Generate a new SSH key pair
     */
    public function generateKeyPair(
        string $name,
        string $type = 'ed25519',
        string $comment = '',
        string $passphrase = '',
        ?int $keySize = null
    ): ?SshKey {
        $this->ensureDirectoryExists();

        // Validate type
        $validTypes = ['ed25519', 'rsa', 'ecdsa'];
        if (! in_array($type, $validTypes)) {
            throw new Exception("Invalid key type: {$type}. Must be one of: ".implode(', ', $validTypes));
        }

        // Build ssh-keygen command
        $keyPath = "{$this->keysDir}/{$name}";

        // Check if key already exists
        if (File::exists($keyPath)) {
            throw new Exception("Key already exists: {$name}");
        }

        $command = [
            'ssh-keygen',
            '-t', $type,
            '-f', $keyPath,
            '-C', $comment ?: "{$name}@netserva",
            '-N', $passphrase,
        ];

        // Add key size for RSA
        if ($type === 'rsa') {
            $command[] = '-b';
            $command[] = (string) ($keySize ?? 4096);
        }

        // Add modern key format option
        if ($type !== 'dsa') {
            $command[] = '-o';
            $command[] = '-a';
            $command[] = '100';
        }

        $process = Process::run(implode(' ', array_map('escapeshellarg', $command)));

        if (! $process->successful()) {
            throw new Exception('Failed to generate SSH key: '.$process->errorOutput());
        }

        // Read generated keys
        $privateKey = trim(File::get($keyPath));
        $publicKey = trim(File::get("{$keyPath}.pub"));

        // Create database record
        $key = SshKey::create([
            'name' => $name,
            'type' => $type,
            'key_size' => $keySize,
            'private_key' => $privateKey,
            'public_key' => $publicKey,
            'comment' => $comment ?: "{$name}@netserva",
            'fingerprint' => $this->getFingerprint("{$keyPath}.pub"),
            'has_passphrase' => ! empty($passphrase),
            'is_active' => true,
        ]);

        Log::info('SSH key pair generated', [
            'name' => $name,
            'type' => $type,
        ]);

        return $key;
    }

    /**
     * Detect key type from private key content
     */
    protected function detectKeyType(string $privateKey): string
    {
        if (str_contains($privateKey, 'OPENSSH PRIVATE KEY')) {
            // Parse the key to detect type
            if (preg_match('/ssh-(ed25519|rsa|ecdsa|dsa)/', $privateKey, $matches)) {
                return $matches[1];
            }

            // For newer OpenSSH format, we need to check the decoded content
            // Default to ed25519 as it's most common for modern keys
            return 'ed25519';
        }

        if (str_contains($privateKey, 'RSA PRIVATE KEY')) {
            return 'rsa';
        }

        if (str_contains($privateKey, 'EC PRIVATE KEY')) {
            return 'ecdsa';
        }

        if (str_contains($privateKey, 'DSA PRIVATE KEY')) {
            return 'dsa';
        }

        return 'unknown';
    }

    /**
     * Extract comment from public key content
     */
    protected function extractCommentFromPublicKey(string $publicKey): ?string
    {
        $parts = explode(' ', trim($publicKey));

        // Format: type key comment
        if (count($parts) >= 3) {
            return $parts[2];
        }

        return null;
    }

    /**
     * Get fingerprint for a public key file
     */
    protected function getFingerprint(string $publicKeyPath): ?string
    {
        if (! File::exists($publicKeyPath)) {
            return null;
        }

        $process = Process::run('ssh-keygen -lf '.escapeshellarg($publicKeyPath));

        if ($process->successful()) {
            // Output format: 256 SHA256:xxx comment (TYPE)
            $output = trim($process->output());
            if (preg_match('/SHA256:[^\s]+/', $output, $matches)) {
                return $matches[0];
            }
        }

        return null;
    }

    /**
     * Ensure the keys directory exists with correct permissions
     */
    protected function ensureDirectoryExists(): void
    {
        if (! File::isDirectory($this->sshDir)) {
            File::makeDirectory($this->sshDir, 0700, true);
        }

        if (! File::isDirectory($this->keysDir)) {
            File::makeDirectory($this->keysDir, 0700, true);
        }
    }

    /**
     * Get keys directory path
     */
    public function getKeysDir(): string
    {
        return $this->keysDir;
    }

    /**
     * Update fingerprint for a key
     */
    public function updateFingerprint(SshKey $key): bool
    {
        if (! $key->public_key) {
            return false;
        }

        try {
            // Write temp file
            $tempFile = tempnam(sys_get_temp_dir(), 'ssh_key_');
            File::put($tempFile, $key->public_key);

            $fingerprint = $this->getFingerprint($tempFile);
            unlink($tempFile);

            if ($fingerprint) {
                $key->update(['fingerprint' => $fingerprint]);

                return true;
            }
        } catch (Exception $e) {
            Log::error('Failed to update key fingerprint', [
                'name' => $key->name,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }
}
