<?php

namespace NetServa\Cli\Services;

/**
 * Dovecot Password Service
 *
 * Generates Dovecot-compatible SHA512-CRYPT password hashes
 * Compatible with: doveadm pw -s SHA512-CRYPT
 *
 * NetServa 3.0 Security Architecture:
 * - Uses PHP's native crypt() with SHA512-CRYPT ($6$ scheme)
 * - No need for remote doveadm pw execution
 * - Same format as Dovecot's default SHA512-CRYPT
 */
class DovecotPasswordService
{
    /**
     * Generate SHA512-CRYPT hash (Dovecot compatible)
     *
     * Format: $6$rounds=5000$salt$hash
     * - $6$ = SHA512-CRYPT scheme
     * - rounds=5000 = iteration count (Dovecot default)
     * - salt = 16-char random salt
     * - hash = resulting hash
     *
     * @param  string  $password  Cleartext password
     * @param  int  $rounds  Number of rounds (default 5000 = Dovecot default)
     * @return string SHA512-CRYPT hash starting with $6$
     */
    public function generateHash(string $password, int $rounds = 5000): string
    {
        // Generate cryptographically secure 16-byte random salt
        $salt = bin2hex(random_bytes(8)); // 16 hex chars

        // Create salt string with rounds parameter
        $saltString = sprintf('$6$rounds=%d$%s$', $rounds, $salt);

        // Generate hash using PHP's crypt() - compatible with Dovecot
        $hash = crypt($password, $saltString);

        if ($hash === false || ! str_starts_with($hash, '$6$')) {
            throw new \RuntimeException('SHA512-CRYPT hashing failed');
        }

        return $hash;
    }

    /**
     * Verify password against SHA512-CRYPT hash
     *
     * @param  string  $password  Cleartext password to verify
     * @param  string  $hash  SHA512-CRYPT hash to verify against
     * @return bool True if password matches hash
     */
    public function verify(string $password, string $hash): bool
    {
        if (! str_starts_with($hash, '$6$')) {
            return false; // Not SHA512-CRYPT format
        }

        // crypt() with existing hash as salt will regenerate same hash if password matches
        return crypt($password, $hash) === $hash;
    }

    /**
     * Check if hash is SHA512-CRYPT format
     *
     * @param  string  $hash  Hash to check
     * @return bool True if SHA512-CRYPT format
     */
    public function isValidFormat(string $hash): bool
    {
        return str_starts_with($hash, '$6$');
    }

    /**
     * Get hash scheme identifier (for Dovecot password-query)
     *
     * @return string '{SHA512-CRYPT}' prefix for Dovecot
     */
    public function getSchemePrefix(): string
    {
        return '{SHA512-CRYPT}';
    }

    /**
     * Format hash with Dovecot scheme prefix (optional)
     *
     * Some Dovecot configurations expect: {SHA512-CRYPT}$6$...
     * Others just use: $6$...
     *
     * @param  string  $hash  SHA512-CRYPT hash
     * @param  bool  $includePrefix  Whether to include {SHA512-CRYPT} prefix
     * @return string Formatted hash
     */
    public function formatForDovecot(string $hash, bool $includePrefix = false): string
    {
        if ($includePrefix && ! str_starts_with($hash, '{')) {
            return $this->getSchemePrefix().$hash;
        }

        return $hash;
    }

    /**
     * Extract iteration count from existing hash
     *
     * @param  string  $hash  SHA512-CRYPT hash
     * @return int|null Iteration count or null if not found
     */
    public function extractRounds(string $hash): ?int
    {
        if (preg_match('/\$6\$rounds=(\d+)\$/', $hash, $matches)) {
            return (int) $matches[1];
        }

        return null; // Default rounds not explicitly set
    }

    /**
     * Generate hash matching doveadm pw output format
     *
     * Equivalent to: doveadm pw -s SHA512-CRYPT -p "$password"
     *
     * @param  string  $password  Cleartext password
     * @return string Hash in doveadm pw format
     */
    public function generateDoveadmCompatible(string $password): string
    {
        // Dovecot's doveadm pw uses 5000 rounds by default
        return $this->generateHash($password, 5000);
    }
}
