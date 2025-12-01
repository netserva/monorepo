<?php

declare(strict_types=1);

namespace NetServa\Fleet\Services;

/**
 * WireGuard Key Generation Service
 *
 * Generates WireGuard keypairs using sodium (libsodium).
 * WireGuard uses Curve25519 for key exchange.
 */
class WireguardKeyService
{
    /**
     * Generate a WireGuard keypair
     *
     * @return array{private: string, public: string}
     */
    public static function generateKeyPair(): array
    {
        // Generate a random 32-byte private key
        $privateKey = random_bytes(SODIUM_CRYPTO_BOX_SECRETKEYBYTES);

        // Clamp the private key for Curve25519
        $privateKey[0] = chr(ord($privateKey[0]) & 248);
        $privateKey[31] = chr((ord($privateKey[31]) & 127) | 64);

        // Derive the public key from the private key
        $publicKey = sodium_crypto_scalarmult_base($privateKey);

        return [
            'private' => base64_encode($privateKey),
            'public' => base64_encode($publicKey),
        ];
    }

    /**
     * Derive public key from a private key
     */
    public static function derivePublicKey(string $privateKeyBase64): string
    {
        $privateKey = base64_decode($privateKeyBase64);
        $publicKey = sodium_crypto_scalarmult_base($privateKey);

        return base64_encode($publicKey);
    }

    /**
     * Generate a preshared key (PSK) for additional security
     */
    public static function generatePresharedKey(): string
    {
        return base64_encode(random_bytes(32));
    }

    /**
     * Validate a WireGuard key format
     */
    public static function isValidKey(string $key): bool
    {
        $decoded = base64_decode($key, true);

        return $decoded !== false && strlen($decoded) === 32;
    }
}
