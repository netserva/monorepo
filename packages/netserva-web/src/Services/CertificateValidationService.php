<?php

namespace NetServa\Web\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use NetServa\Web\Models\SslCertificate;

class CertificateValidationService
{
    /**
     * Validate certificate configuration
     */
    public function validateConfiguration(array $config): array
    {
        $errors = [];

        if (empty($config['domain'])) {
            $errors[] = 'Domain is required';
        }

        if (! empty($config['domain']) && ! filter_var($config['domain'], FILTER_VALIDATE_DOMAIN)) {
            $errors[] = 'Invalid domain format';
        }

        if (isset($config['key_size']) && ! in_array($config['key_size'], [2048, 3072, 4096])) {
            $errors[] = 'Key size must be 2048, 3072, or 4096';
        }

        return $errors;
    }

    /**
     * Validate certificate expiry
     */
    public function checkExpiry(SslCertificate $certificate): array
    {
        if (! $certificate->expires_at) {
            return [
                'status' => 'unknown',
                'message' => 'No expiry date available',
                'days_remaining' => null,
            ];
        }

        $now = Carbon::now();
        $expiresAt = Carbon::parse($certificate->expires_at);
        $daysRemaining = $now->diffInDays($expiresAt, false);

        if ($daysRemaining < 0) {
            $status = 'expired';
            $message = 'Certificate has expired';
        } elseif ($daysRemaining <= 7) {
            $status = 'critical';
            $message = 'Certificate expires in '.$daysRemaining.' days';
        } elseif ($daysRemaining <= 30) {
            $status = 'warning';
            $message = 'Certificate expires in '.$daysRemaining.' days';
        } else {
            $status = 'valid';
            $message = 'Certificate is valid';
        }

        return [
            'status' => $status,
            'message' => $message,
            'days_remaining' => abs($daysRemaining),
            'expires_at' => $expiresAt->toDateTimeString(),
        ];
    }

    /**
     * Validate certificate chain
     */
    public function validateChain(string $certificateContent): bool
    {
        try {
            $cert = openssl_x509_read($certificateContent);

            return $cert !== false;
        } catch (Exception $e) {
            Log::error('Certificate chain validation failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Extract certificate information
     */
    public function extractInfo(string $certificateContent): array
    {
        try {
            $cert = openssl_x509_read($certificateContent);
            if (! $cert) {
                throw new Exception('Invalid certificate format');
            }

            $certData = openssl_x509_parse($cert);

            return [
                'subject' => $certData['subject']['CN'] ?? null,
                'issuer' => $certData['issuer']['CN'] ?? null,
                'valid_from' => Carbon::createFromTimestamp($certData['validFrom_time_t'])->toDateTimeString(),
                'valid_to' => Carbon::createFromTimestamp($certData['validTo_time_t'])->toDateTimeString(),
                'serial_number' => $certData['serialNumber'] ?? null,
                'signature_algorithm' => $certData['signatureTypeSN'] ?? null,
            ];
        } catch (Exception $e) {
            Log::error('Certificate info extraction failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Validate domain ownership
     */
    public function validateDomainOwnership(string $domain, string $method = 'http'): bool
    {
        try {
            switch ($method) {
                case 'http':
                    return $this->validateHttpChallenge($domain);
                case 'dns':
                    return $this->validateDnsChallenge($domain);
                default:
                    return false;
            }
        } catch (Exception $e) {
            Log::error('Domain ownership validation failed', [
                'domain' => $domain,
                'method' => $method,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Validate HTTP challenge
     */
    private function validateHttpChallenge(string $domain): bool
    {
        // Simplified validation - in real implementation would check ACME challenge
        $url = "http://{$domain}/.well-known/acme-challenge/test";
        $headers = get_headers($url);

        return $headers && strpos($headers[0], '200') !== false;
    }

    /**
     * Validate DNS challenge
     */
    private function validateDnsChallenge(string $domain): bool
    {
        // Simplified validation - in real implementation would check DNS TXT record
        $records = dns_get_record("_acme-challenge.{$domain}", DNS_TXT);

        return ! empty($records);
    }
}
