<?php

namespace NetServa\Config\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use NetServa\Config\Models\Secret;
use NetServa\Config\Models\SecretAccess;

class SecretsService
{
    /**
     * Create a new encrypted secret
     */
    public function createSecret(array $attributes): Secret
    {
        // Set default slug if not provided
        if (! isset($attributes['slug'])) {
            $attributes['slug'] = Str::slug($attributes['name']);
        }

        // Ensure required fields have defaults
        $attributes['is_active'] = $attributes['is_active'] ?? true;
        $attributes['type'] = $attributes['type'] ?? 'password';

        // Handle the value field - encrypt it and set encrypted_value
        if (isset($attributes['value'])) {
            $attributes['encrypted_value'] = encrypt($attributes['value']);
            $attributes['encryption_method'] = 'laravel-crypt';
            unset($attributes['value']); // Remove plain value
        }

        return Secret::create($attributes);
    }

    /**
     * Get decrypted secret value
     */
    public function getSecretValue(Secret $secret): ?string
    {
        if (empty($secret->encrypted_value)) {
            return null;
        }

        try {
            $decrypted = decrypt($secret->encrypted_value);

            // Log the access
            $secret->logAccess('view');

            return $decrypted;
        } catch (\Exception $e) {
            \Log::error('Failed to decrypt secret', [
                'secret_id' => $secret->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Generate a secure password
     */
    public function generatePassword(array $options = []): string
    {
        $length = $options['length'] ?? 12;
        $includeSymbols = $options['include_symbols'] ?? true;
        $includeNumbers = $options['include_numbers'] ?? true;
        $includeUppercase = $options['include_uppercase'] ?? true;
        $includeLowercase = $options['include_lowercase'] ?? true;

        $characters = '';

        if ($includeLowercase) {
            $characters .= 'abcdefghijklmnopqrstuvwxyz';
        }

        if ($includeUppercase) {
            $characters .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }

        if ($includeNumbers) {
            $characters .= '0123456789';
        }

        if ($includeSymbols) {
            $characters .= '!@#$%^&*()';
        }

        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $password;
    }

    /**
     * Generate an API key with metadata
     */
    public function generateApiKey(array $options = []): Secret
    {
        $apiKey = Str::random(64);

        $secretData = [
            'name' => $options['name'] ?? 'generated_api_key',
            'type' => 'api_key',
            'value' => $apiKey,
            'expires_at' => $options['expires_at'] ?? null,
            'metadata' => [
                'permissions' => $options['permissions'] ?? [],
                'rate_limit' => $options['rate_limit'] ?? null,
                'generated_at' => now()->toISOString(),
            ],
        ];

        return $this->createSecret($secretData);
    }

    /**
     * Log secret access
     */
    public function logAccess(Secret $secret, array $context = []): SecretAccess
    {
        $accessData = [
            'secret_id' => $secret->id,
            'user_id' => $context['user_id'] ?? auth()->id(),
            'access_type' => $context['action'] ?? 'view',
            'ip_address' => $context['ip_address'] ?? request()?->ip(),
            'user_agent' => $context['user_agent'] ?? request()?->userAgent(),
            'source' => $context['source'] ?? 'service',
            'additional_context' => $context,
            'accessed_at' => now(),
        ];

        return SecretAccess::create($accessData);
    }

    /**
     * Rotate a secret by generating a new value
     */
    public function rotateSecret(Secret $secret): Secret
    {
        $newValue = $this->generateSecret($secret->type);

        // Update the secret with new value and increment version
        $secret->update([
            'encrypted_value' => encrypt($newValue),
            'version' => ($secret->version ?? 0) + 1,
            'last_rotated_at' => now(),
        ]);

        return $secret->refresh();
    }

    /**
     * Create a secure share link for temporary access
     */
    public function createShareLink(Secret $secret, array $options = []): array
    {
        $token = Str::random(64);
        $expiresAt = $options['expires_at'] ?? now()->addHours(24);

        // Store share link data (in a real implementation, this would go to a dedicated table)
        $shareData = [
            'token' => $token,
            'secret_id' => $secret->id,
            'expires_at' => $expiresAt,
            'max_accesses' => $options['max_accesses'] ?? null,
            'allowed_ips' => $options['allowed_ips'] ?? [],
            'current_accesses' => 0,
        ];

        // For now, store in secret metadata (in production, use dedicated share_links table)
        $secret->update([
            'metadata' => array_merge($secret->metadata ?? [], [
                'share_links' => array_merge($secret->metadata['share_links'] ?? [], [$shareData]),
            ]),
        ]);

        return [
            'token' => $token,
            'url' => url("/secrets/share/{$token}"),
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Check if user can access secret based on access policy
     */
    public function canAccess(Secret $secret, array $context = []): bool
    {
        $policy = $secret->access_policy ?? [];

        // Check user access
        if (isset($policy['allowed_users'])) {
            $userId = $context['user_id'] ?? auth()->id();
            if (! in_array($userId, $policy['allowed_users'])) {
                return false;
            }
        }

        // Check role access
        if (isset($policy['allowed_roles'])) {
            $userRoles = $context['user_roles'] ?? [];
            if (! array_intersect($policy['allowed_roles'], $userRoles)) {
                return false;
            }
        }

        // Check time restrictions
        if (isset($policy['time_restrictions'])) {
            $currentTime = $context['time'] ?? now();
            $start = Carbon::createFromFormat('H:i', $policy['time_restrictions']['start']);
            $end = Carbon::createFromFormat('H:i', $policy['time_restrictions']['end']);

            if (! $currentTime->between($start, $end)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Backup secrets to encrypted format
     */
    public function backupSecrets(array $options = []): array
    {
        $secrets = Secret::all();
        $includeValues = $options['include_values'] ?? false;
        $encryptionKey = $options['encryption_key'] ?? null;

        $backupData = [];

        foreach ($secrets as $secret) {
            $secretData = $secret->toArray();

            if ($includeValues && $encryptionKey) {
                $secretData['decrypted_value'] = $this->getSecretValue($secret);
            }

            $backupData[] = $secretData;
        }

        $backup = [
            'secrets' => $backupData,
            'metadata' => [
                'created_at' => now()->toISOString(),
                'version' => '1.0',
                'count' => count($backupData),
            ],
            'checksum' => hash('sha256', json_encode($backupData)),
        ];

        return $backup;
    }

    /**
     * Import secrets from external source
     */
    public function importSecrets(array $secrets, array $options = []): array
    {
        $prefix = $options['prefix'] ?? '';
        $overwrite = $options['overwrite'] ?? false;
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($secrets as $secretData) {
            $name = $prefix.$secretData['name'];
            $existing = Secret::where('name', $name)->first();

            if ($existing && ! $overwrite) {
                $skipped++;

                continue;
            }

            $attributes = [
                'name' => $name,
                'type' => $secretData['type'] ?? 'password',
                'value' => $secretData['value'],
                'description' => $secretData['description'] ?? 'Imported from external vault',
                'tags' => array_merge($secretData['tags'] ?? [], ['imported']),
            ];

            if ($existing) {
                $existing->update($attributes);
                $updated++;
            } else {
                $this->createSecret($attributes);
                $created++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    /**
     * Search secrets with filters
     */
    public function searchSecrets(array $filters): Collection
    {
        $query = Secret::query();

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['tags'])) {
            foreach ($filters['tags'] as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        if (isset($filters['name_pattern'])) {
            $pattern = str_replace('*', '%', $filters['name_pattern']);
            $query->where('name', 'like', $pattern);
        }

        if (isset($filters['category_id'])) {
            $query->where('secret_category_id', $filters['category_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->get();
    }

    /**
     * Generate a secret value based on type
     */
    public function generateSecret(string $type, array $options = []): string
    {
        switch ($type) {
            case 'password':
                return $this->generatePassword(array_merge([
                    'length' => $options['length'] ?? 32,
                    'include_symbols' => true,
                    'include_numbers' => true,
                    'include_uppercase' => true,
                    'include_lowercase' => true,
                ], $options));

            case 'api_key':
                return Str::random($options['length'] ?? 64);

            case 'token':
                return Str::random($options['length'] ?? 128);

            case 'ssh_key':
                // For SSH keys, return a placeholder (in practice, you'd generate actual keys)
                return 'ssh-rsa AAAAB3NzaC1yc2E...';

            case 'certificate':
                // For certificates, return a placeholder (in practice, you'd generate actual certs)
                return '-----BEGIN CERTIFICATE-----\n...\n-----END CERTIFICATE-----';

            default:
                return Str::random($options['length'] ?? 32);
        }
    }
}
