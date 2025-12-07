<?php

namespace NetServa\Web\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use NetServa\Web\Models\SslCertificate;
use NetServa\Web\Models\SslCertificateAuthority;

class SslCertificateService
{
    public function __construct(
        private ?AcmeClientService $acmeClient = null,
        private ?CertificateValidationService $validationService = null,
        private ?CertificateDeploymentService $deploymentService = null
    ) {}

    /**
     * Create a new SSL certificate request
     */
    public function createCertificate(array $data): SslCertificate
    {
        $ca = SslCertificateAuthority::find($data['ssl_certificate_authority_id'])
              ?? SslCertificateAuthority::getDefault();

        if (! $ca) {
            throw new Exception('No certificate authority available');
        }

        // Validate CA can issue certificates
        if (! $ca->canIssueCertificate($data['common_name'])) {
            throw new Exception('Certificate authority not available');
        }

        // Determine certificate type
        $certificateType = $this->determineCertificateType($data);

        $certificate = SslCertificate::create([
            'common_name' => $data['common_name'],
            'subject_alternative_names' => $data['subject_alternative_names'] ?? [],
            'certificate_type' => $certificateType,
            'ssl_certificate_authority_id' => $ca->id,
            'key_type' => $data['key_type'] ?? 'rsa',
            'key_size' => $data['key_size'] ?? ($data['key_type'] === 'ecdsa' ? 256 : 2048),
            'auto_renew' => $data['auto_renew'] ?? true,
            'renew_days_before_expiry' => $data['renew_days_before_expiry'] ?? 30,
            'notes' => $data['notes'] ?? null,
            'is_wildcard' => str_starts_with($data['common_name'], '*.'),
            'status' => 'pending',
        ]);

        return $certificate;
    }

    /**
     * Issue a certificate through ACME or other methods
     */
    public function issueCertificate(SslCertificate $certificate): bool
    {
        try {
            $ca = $certificate->certificateAuthority;

            if (! $ca->isHealthy()) {
                throw new Exception('Certificate authority is not healthy');
            }

            switch ($ca->ca_type) {
                case 'letsencrypt':
                case 'buypass':
                case 'zerossl':
                    return $this->issueAcmeCertificate($certificate);

                case 'self_signed':
                    return $this->issueSelfSignedCertificate($certificate);

                default:
                    throw new Exception("Unsupported CA type: {$ca->ca_type}");
            }

        } catch (Exception $e) {
            $certificate->update([
                'status' => 'failed',
            ]);

            return false;
        }
    }

    /**
     * Issue ACME certificate (Let's Encrypt, Buypass, ZeroSSL)
     */
    protected function issueAcmeCertificate(SslCertificate $certificate): bool
    {
        $ca = $certificate->certificateAuthority;

        // Generate key pair
        $keyPair = $this->generateKeyPair($certificate->key_type, $certificate->key_size);

        // Create CSR
        $csr = $this->generateCSRForCertificate($certificate, $keyPair['private_key']);

        // Use ACME client to get certificate
        $acmeResult = $this->acmeClient->requestCertificate(
            $ca,
            $certificate->common_name,
            $certificate->subject_alternative_names ?? [],
            $csr,
            ['http-01'] // Default validation method
        );

        if (! $acmeResult['success']) {
            throw new Exception($acmeResult['error']);
        }

        // Update certificate with results
        $validFrom = Carbon::parse($acmeResult['not_before']);
        $validTo = Carbon::parse($acmeResult['not_after']);

        $certificate->update([
            'certificate_pem' => $acmeResult['certificate'],
            'certificate_chain_pem' => $acmeResult['chain'],
            'private_key_pem' => $this->encryptPrivateKey($keyPair['private_key']),
            'not_valid_before' => $validFrom,
            'not_valid_after' => $validTo,
            'status' => 'active',
        ]);

        // Schedule next renewal
        $renewalDate = $validTo->subDays($certificate->renew_days_before_expiry ?? 30);
        $certificate->scheduleRenewal($renewalDate);

        return true;
    }

    /**
     * Issue self-signed certificate
     */
    protected function issueSelfSignedCertificate(SslCertificate $certificate): bool
    {
        // Generate key pair
        $keyPair = $this->generateKeyPair($certificate->key_type, $certificate->key_size);

        // Create self-signed certificate
        $dn = [
            'CN' => $certificate->common_name,
            'O' => 'NetServa Infrastructure',
            'OU' => 'SSL Certificate',
            'C' => 'AU',
        ];

        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => $certificate->key_size,
            'private_key_type' => $certificate->key_type === 'ecdsa' ? OPENSSL_KEYTYPE_EC : OPENSSL_KEYTYPE_RSA,
        ];

        // Create certificate signing request
        $csr = openssl_csr_new($dn, $keyPair['private_key'], $config);

        if (! $csr) {
            throw new Exception('Failed to create CSR: '.openssl_error_string());
        }

        // Sign the certificate
        $cert = openssl_csr_sign($csr, null, $keyPair['private_key'], $certificate->validity_days, $config);

        if (! $cert) {
            throw new Exception('Failed to create self-signed certificate: '.openssl_error_string());
        }

        // Export certificate
        openssl_x509_export($cert, $certPem);
        openssl_csr_export($csr, $csrPem);

        $validFrom = now();
        $validTo = now()->addDays($certificate->validity_days);

        $certificate->update([
            'certificate_pem' => $certPem,
            'private_key_pem' => $this->encryptPrivateKey($keyPair['private_key']),
            'not_valid_before' => $validFrom,
            'not_valid_after' => $validTo,
            'status' => 'active',
        ]);

        return true;
    }

    /**
     * Renew an existing certificate
     */
    public function renewCertificate(SslCertificate $certificate): bool
    {
        if (! $certificate->needsRenewal()) {
            return false;
        }

        $certificate->update(['status' => 'renewing']);

        try {
            $success = $this->issueCertificate($certificate);

            if ($success) {
                return true;
            }

            return false;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Process certificate renewals
     */
    public function processRenewals(int $limit = 10): array
    {
        $certificates = SslCertificate::needsRenewal()
            ->with('certificateAuthority')
            ->limit($limit)
            ->get();

        $results = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($certificates as $certificate) {
            $results['processed']++;

            try {
                if ($this->renewCertificate($certificate)) {
                    $results['successful']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to renew {$certificate->common_name}";
                }
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Error renewing {$certificate->common_name}: ".$e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Get certificates expiring soon
     */
    public function getExpiringCertificates(int $days = 30): Collection
    {
        return SslCertificate::expiringSoon($days)
            ->with(['certificateAuthority', 'deployments'])
            ->get();
    }

    /**
     * Generate key pair
     */
    protected function generateKeyPair(string $keyType, int $keySize): array
    {
        $config = [
            'private_key_bits' => $keySize,
            'private_key_type' => $keyType === 'ecdsa' ? OPENSSL_KEYTYPE_EC : OPENSSL_KEYTYPE_RSA,
        ];

        if ($keyType === 'ecdsa') {
            $config['curve_name'] = match ($keySize) {
                256 => 'secp256r1',
                384 => 'secp384r1',
                default => 'secp256r1'
            };
        }

        $privateKey = openssl_pkey_new($config);

        if (! $privateKey) {
            throw new Exception('Failed to generate private key: '.openssl_error_string());
        }

        openssl_pkey_export($privateKey, $privateKeyPem);

        return [
            'private_key' => $privateKeyPem,
            'public_key' => openssl_pkey_get_details($privateKey)['key'],
        ];
    }

    /**
     * Generate Certificate Signing Request
     */
    protected function generateCSRForCertificate(SslCertificate $certificate, string $privateKey): string
    {
        $dn = [
            'CN' => $certificate->common_name,
            'O' => 'NetServa Infrastructure',
            'OU' => 'SSL Certificate',
            'C' => 'AU',
        ];

        $config = [
            'digest_alg' => 'sha256',
        ];

        // Add SAN extension if needed
        if (! empty($certificate->subject_alternative_names)) {
            $sans = array_merge([$certificate->common_name], $certificate->subject_alternative_names);
            $sanString = 'DNS:'.implode(',DNS:', array_unique($sans));

            $config['req_extensions'] = 'v3_req';
            $config['v3_req'] = [
                'subjectAltName' => $sanString,
            ];
        }

        $csr = openssl_csr_new($dn, $privateKey, $config);

        if (! $csr) {
            throw new Exception('Failed to create CSR: '.openssl_error_string());
        }

        openssl_csr_export($csr, $csrPem);

        return $csrPem;
    }

    /**
     * Encrypt private key for storage
     */
    protected function encryptPrivateKey(string $privateKey): string
    {
        // In a real implementation, encrypt with application key
        // For now, return as-is but mark as encrypted in production
        return $privateKey;
    }

    /**
     * Determine certificate type from domains
     */
    protected function determineCertificateType(array $data): string
    {
        if (str_starts_with($data['common_name'], '*.')) {
            return 'wildcard';
        }

        if (! empty($data['subject_alternative_names']) && count($data['subject_alternative_names']) > 0) {
            return 'multi_domain';
        }

        return 'domain';
    }

    /**
     * Update certificate status based on expiry
     */
    public function updateCertificateStatuses(): int
    {
        $updated = 0;

        // Update expired certificates
        $expiredCount = SslCertificate::where('status', 'active')
            ->where('not_valid_after', '<', now())
            ->update(['status' => 'expired']);
        $updated += $expiredCount;

        // Update certificates that are no longer expired (edge case)
        $reactivatedCount = SslCertificate::where('status', 'expired')
            ->where('not_valid_after', '>', now())
            ->update(['status' => 'active']);
        $updated += $reactivatedCount;

        return $updated;
    }

    /**
     * Get SSL certificate statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_certificates' => SslCertificate::count(),
            'active_certificates' => SslCertificate::active()->count(),
            'expired_certificates' => SslCertificate::expired()->count(),
            'expiring_soon' => SslCertificate::expiringSoon(30)->count(),
            'pending_renewal' => SslCertificate::needsRenewal()->count(),
            'wildcard_certificates' => SslCertificate::wildcard()->count(),
        ];
    }

    /**
     * Generate self-signed certificate (public method for tests)
     */
    public function generateSelfSignedCertificate(string $domain, int $validityDays = 365): SslCertificate
    {
        $ca = SslCertificateAuthority::firstOrCreate([
            'name' => 'Self-Signed CA',
            'ca_type' => 'self_signed',
            'is_active' => true,
        ]);

        $certificate = SslCertificate::create([
            'common_name' => $domain,
            'certificate_type' => 'domain',
            'ssl_certificate_authority_id' => $ca->id,
            'key_type' => 'rsa',
            'key_size' => 2048,
            'auto_renew' => false,
            'is_wildcard' => str_starts_with($domain, '*.'),
            'status' => 'pending',
        ]);

        $this->issueSelfSignedCertificate($certificate);

        return $certificate->fresh();
    }
}
