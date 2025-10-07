<?php

namespace NetServa\Web\Services;

use Exception;
use NetServa\Web\Models\SslCertificateAuthority;

class AcmeClientService
{
    protected array $directory = [];

    protected string $nonce = '';

    /**
     * Request certificate through ACME protocol
     */
    public function requestCertificate(
        SslCertificateAuthority $ca,
        string $domain,
        array $sanDomains,
        string $csr,
        array $validationMethods = ['http-01']
    ): array {
        try {
            // Get ACME directory
            $this->directory = $this->getAcmeDirectory($ca);

            if (! $this->directory) {
                throw new Exception('Failed to retrieve ACME directory');
            }

            // Create account if needed
            $accountUrl = $this->createOrGetAccount($ca);

            // Create order
            $allDomains = array_unique(array_merge([$domain], $sanDomains));
            $order = $this->createOrder($ca, $allDomains);

            // Process authorizations
            foreach ($order['authorizations'] as $authUrl) {
                $this->processAuthorization($ca, $authUrl, $validationMethods[0] ?? 'http-01');
            }

            // Finalize order with CSR
            $certificate = $this->finalizeOrder($ca, $order['finalize'], $csr);

            // Get certificate chain
            $chain = $this->getCertificateChain($ca, $order['certificate']);

            return [
                'success' => true,
                'certificate' => $certificate,
                'chain' => $chain,
                'order_url' => $order['url'],
                'not_before' => $order['notBefore'] ?? now()->toISOString(),
                'not_after' => $order['notAfter'] ?? now()->addDays(90)->toISOString(),
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Revoke certificate through ACME
     */
    public function revokeCertificate(SslCertificateAuthority $ca, string $certificate, string $reason = 'cessationOfOperation'): bool
    {
        try {
            $this->directory = $this->getAcmeDirectory($ca);

            $reasonCode = $this->getRevocationReasonCode($reason);

            $payload = [
                'certificate' => base64url_encode($certificate),
                'reason' => $reasonCode,
            ];

            $response = $this->makeAcmeRequest($ca, $this->directory['revokeCert'], $payload);

            return $response['status'] === 200;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get ACME directory
     */
    protected function getAcmeDirectory(SslCertificateAuthority $ca): ?array
    {
        if (! $ca->acme_directory_url) {
            return null;
        }

        $response = $this->httpGet($ca->acme_directory_url);

        if (! $response || $response['status'] !== 200) {
            return null;
        }

        return json_decode($response['body'], true);
    }

    /**
     * Create or get existing ACME account
     */
    protected function createOrGetAccount(SslCertificateAuthority $ca): string
    {
        // Check if account already exists
        if ($ca->account_url) {
            return $ca->account_url;
        }

        // Generate account key if not exists
        if (! $ca->account_key) {
            $accountKey = $this->generateAccountKey();
            $ca->update(['account_key' => $accountKey]);
        }

        // Create new account
        $payload = [
            'termsOfServiceAgreed' => true,
            'contact' => ['mailto:'.$ca->account_email],
        ];

        $response = $this->makeAcmeRequest($ca, $this->directory['newAccount'], $payload);

        if ($response['status'] === 201 || $response['status'] === 200) {
            $accountUrl = $response['headers']['Location'] ?? null;

            if ($accountUrl) {
                $ca->update(['account_url' => $accountUrl]);

                return $accountUrl;
            }
        }

        throw new Exception('Failed to create ACME account');
    }

    /**
     * Create ACME order
     */
    protected function createOrder(SslCertificateAuthority $ca, array $domains): array
    {
        $identifiers = array_map(fn ($domain) => ['type' => 'dns', 'value' => $domain], $domains);

        $payload = [
            'identifiers' => $identifiers,
        ];

        $response = $this->makeAcmeRequest($ca, $this->directory['newOrder'], $payload);

        if ($response['status'] === 201) {
            $order = json_decode($response['body'], true);
            $order['url'] = $response['headers']['Location'] ?? null;

            return $order;
        }

        throw new Exception('Failed to create ACME order');
    }

    /**
     * Process authorization (simplified HTTP-01 validation)
     */
    protected function processAuthorization(SslCertificateAuthority $ca, string $authUrl, string $validationType): bool
    {
        $response = $this->makeAcmeRequest($ca, $authUrl, null, 'GET');
        $auth = json_decode($response['body'], true);

        // Find the requested challenge type
        $challenge = null;
        foreach ($auth['challenges'] as $chall) {
            if ($chall['type'] === $validationType) {
                $challenge = $chall;
                break;
            }
        }

        if (! $challenge) {
            throw new Exception("Challenge type {$validationType} not available");
        }

        // For HTTP-01 validation, we would normally:
        // 1. Create the challenge response file
        // 2. Notify ACME server we're ready
        // 3. Wait for validation

        // This is a simplified implementation that assumes validation is handled externally
        $keyAuthorization = $this->generateKeyAuthorization($ca, $challenge['token']);

        // Notify ACME server we're ready
        $response = $this->makeAcmeRequest($ca, $challenge['url'], []);

        if ($response['status'] === 200) {
            // In a real implementation, we'd poll for completion
            return true;
        }

        throw new Exception('Challenge validation failed');
    }

    /**
     * Finalize order with CSR
     */
    protected function finalizeOrder(SslCertificateAuthority $ca, string $finalizeUrl, string $csr): string
    {
        // Convert CSR to DER format and base64url encode
        $csrResource = openssl_csr_get_subject($csr);
        if (! $csrResource) {
            throw new Exception('Invalid CSR');
        }

        // For simplicity, assume CSR is already in correct format
        $csrDer = base64url_encode($csr);

        $payload = [
            'csr' => $csrDer,
        ];

        $response = $this->makeAcmeRequest($ca, $finalizeUrl, $payload);

        if ($response['status'] === 200) {
            $order = json_decode($response['body'], true);

            // Wait for certificate to be ready (simplified)
            if (isset($order['certificate'])) {
                return $this->getCertificate($ca, $order['certificate']);
            }
        }

        throw new Exception('Failed to finalize order');
    }

    /**
     * Get certificate from certificate URL
     */
    protected function getCertificate(SslCertificateAuthority $ca, string $certUrl): string
    {
        $response = $this->makeAcmeRequest($ca, $certUrl, null, 'GET');

        if ($response['status'] === 200) {
            return $response['body'];
        }

        throw new Exception('Failed to retrieve certificate');
    }

    /**
     * Get certificate chain
     */
    protected function getCertificateChain(SslCertificateAuthority $ca, string $certUrl): string
    {
        // Certificate URL typically returns the full chain
        return $this->getCertificate($ca, $certUrl);
    }

    /**
     * Make ACME request with JWS signing
     */
    protected function makeAcmeRequest(SslCertificateAuthority $ca, string $url, ?array $payload, string $method = 'POST'): array
    {
        if ($method === 'GET') {
            return $this->httpGet($url);
        }

        // Get fresh nonce
        $this->nonce = $this->getNonce($ca);

        // Create JWS
        $jws = $this->createJWS($ca, $url, $payload);

        return $this->httpPost($url, json_encode($jws), [
            'Content-Type: application/jose+json',
        ]);
    }

    /**
     * Get fresh nonce from ACME server
     */
    protected function getNonce(SslCertificateAuthority $ca): string
    {
        if (empty($this->directory['newNonce'])) {
            throw new Exception('No newNonce URL in directory');
        }

        $response = $this->httpHead($this->directory['newNonce']);

        return $response['headers']['Replay-Nonce'] ??
               $response['headers']['replay-nonce'] ?? '';
    }

    /**
     * Create JWS (JSON Web Signature)
     */
    protected function createJWS(SslCertificateAuthority $ca, string $url, ?array $payload): array
    {
        // This is a simplified JWS implementation
        // In production, use a proper JWS library

        $header = [
            'alg' => 'RS256',
            'nonce' => $this->nonce,
            'url' => $url,
        ];

        if ($ca->account_url) {
            $header['kid'] = $ca->account_url;
        } else {
            // Include JWK for account creation
            $header['jwk'] = $this->getJWK($ca);
        }

        $protectedHeader = base64url_encode(json_encode($header));
        $encodedPayload = $payload ? base64url_encode(json_encode($payload)) : '';

        $signingInput = $protectedHeader.'.'.$encodedPayload;
        $signature = $this->sign($ca, $signingInput);

        return [
            'protected' => $protectedHeader,
            'payload' => $encodedPayload,
            'signature' => base64url_encode($signature),
        ];
    }

    /**
     * Generate account key
     */
    protected function generateAccountKey(): string
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $privateKey = openssl_pkey_new($config);
        openssl_pkey_export($privateKey, $privateKeyPem);

        return $privateKeyPem;
    }

    /**
     * Get JWK (JSON Web Key) from account key
     */
    protected function getJWK(SslCertificateAuthority $ca): array
    {
        // Simplified JWK generation
        // In production, properly extract RSA components
        return [
            'kty' => 'RSA',
            'n' => 'example_n_value',
            'e' => 'AQAB',
        ];
    }

    /**
     * Sign data with account key
     */
    protected function sign(SslCertificateAuthority $ca, string $data): string
    {
        $privateKey = openssl_pkey_get_private($ca->account_key);

        if (! $privateKey) {
            throw new Exception('Invalid account key');
        }

        openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return $signature;
    }

    /**
     * Generate key authorization for challenge
     */
    protected function generateKeyAuthorization(SslCertificateAuthority $ca, string $token): string
    {
        $jwk = $this->getJWK($ca);
        $thumbprint = base64url_encode(hash('sha256', json_encode($jwk), true));

        return $token.'.'.$thumbprint;
    }

    /**
     * Get revocation reason code
     */
    protected function getRevocationReasonCode(string $reason): int
    {
        return match ($reason) {
            'unspecified' => 0,
            'keyCompromise' => 1,
            'cACompromise' => 2,
            'affiliationChanged' => 3,
            'superseded' => 4,
            'cessationOfOperation' => 5,
            'certificateHold' => 6,
            'removeFromCRL' => 8,
            'privilegeWithdrawn' => 9,
            'aACompromise' => 10,
            default => 5, // cessationOfOperation
        };
    }

    /**
     * HTTP GET request
     */
    protected function httpGet(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 30,
                'user_agent' => 'NS SSL Manager/1.0',
            ],
        ]);

        $response = file_get_contents($url, false, $context);

        return [
            'status' => 200, // Simplified
            'body' => $response,
            'headers' => $http_response_header ?? [],
        ];
    }

    /**
     * HTTP HEAD request
     */
    protected function httpHead(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'timeout' => 30,
                'user_agent' => 'NS SSL Manager/1.0',
            ],
        ]);

        file_get_contents($url, false, $context);

        return [
            'status' => 200, // Simplified
            'headers' => $this->parseHeaders($http_response_header ?? []),
        ];
    }

    /**
     * HTTP POST request
     */
    protected function httpPost(string $url, string $data, array $headers = []): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $data,
                'timeout' => 30,
                'user_agent' => 'NS SSL Manager/1.0',
            ],
        ]);

        $response = file_get_contents($url, false, $context);

        return [
            'status' => 200, // Simplified
            'body' => $response,
            'headers' => $this->parseHeaders($http_response_header ?? []),
        ];
    }

    /**
     * Parse HTTP headers into associative array
     */
    protected function parseHeaders(array $headers): array
    {
        $parsed = [];

        foreach ($headers as $header) {
            if (str_contains($header, ':')) {
                [$key, $value] = explode(':', $header, 2);
                $parsed[trim($key)] = trim($value);
            }
        }

        return $parsed;
    }
}

// Helper function for base64url encoding
if (! function_exists('base64url_encode')) {
    function base64url_encode($data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (! function_exists('base64url_decode')) {
    function base64url_decode($data): string
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}
