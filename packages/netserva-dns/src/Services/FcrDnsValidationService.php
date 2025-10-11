<?php

namespace NetServa\Dns\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use NetServa\Dns\Exceptions\DnsValidationException;
use NetServa\Dns\ValueObjects\DnsValidationResult;

/**
 * Forward-Confirmed Reverse DNS (FCrDNS) Validation Service
 *
 * Validates that:
 * 1. Forward DNS (A/AAAA record) exists: domain → IP
 * 2. Reverse DNS (PTR record) exists: IP → domain
 * 3. Both match (FCrDNS validation)
 *
 * This is a MANDATORY requirement for:
 * - Email server deliverability (RFC 1912 recommendation)
 * - SSL certificate issuance (Let's Encrypt DNS validation)
 * - Production server identification
 *
 * NetServa 3.0 Policy: DNS must be configured BEFORE server initialization
 */
class FcrDnsValidationService
{
    /**
     * Validate full FCrDNS (Forward + Reverse + Match)
     *
     * @throws DnsValidationException
     */
    public function validate(string $fqdn, string $ip): DnsValidationResult
    {
        Log::info('Starting FCrDNS validation', [
            'fqdn' => $fqdn,
            'ip' => $ip,
        ]);

        $result = new DnsValidationResult(
            fqdn: $fqdn,
            ip: $ip,
            hasForwardDns: false,
            hasReverseDns: false,
            hasFcrDns: false,
            forwardIp: null,
            reverseFqdn: null,
            errors: [],
            warnings: []
        );

        // Step 1: Validate forward DNS (A/AAAA record)
        $this->validateForwardDns($result);

        // Step 2: Validate reverse DNS (PTR record)
        $this->validateReverseDns($result);

        // Step 3: Validate FCrDNS (forward and reverse match)
        $this->validateFcrDnsMatch($result);

        Log::info('FCrDNS validation complete', [
            'fqdn' => $fqdn,
            'ip' => $ip,
            'forward_dns' => $result->hasForwardDns,
            'reverse_dns' => $result->hasReverseDns,
            'fcrdns' => $result->hasFcrDns,
            'errors' => $result->errors,
        ]);

        return $result;
    }

    /**
     * Validate forward DNS (A/AAAA record exists)
     */
    protected function validateForwardDns(DnsValidationResult $result): void
    {
        try {
            // Use gethostbyname for IPv4
            $forwardIp = gethostbyname($result->fqdn);

            // gethostbyname returns the hostname if lookup fails
            if ($forwardIp === $result->fqdn) {
                $result->addError('forward_dns', "No A record found for {$result->fqdn}");

                return;
            }

            $result->forwardIp = $forwardIp;
            $result->hasForwardDns = true;

            // Check if forward IP matches expected IP
            if ($forwardIp !== $result->ip) {
                $result->addWarning('forward_dns_mismatch',
                    "Forward DNS resolves to {$forwardIp} but expected {$result->ip}. Using detected IP."
                );
            }

            Log::debug('Forward DNS lookup successful', [
                'fqdn' => $result->fqdn,
                'resolved_ip' => $forwardIp,
                'expected_ip' => $result->ip,
            ]);

        } catch (Exception $e) {
            $result->addError('forward_dns_exception', "Forward DNS lookup failed: {$e->getMessage()}");
            Log::error('Forward DNS lookup exception', [
                'fqdn' => $result->fqdn,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Validate reverse DNS (PTR record exists)
     */
    protected function validateReverseDns(DnsValidationResult $result): void
    {
        try {
            // Use gethostbyaddr for reverse lookup
            $reverseFqdn = gethostbyaddr($result->ip);

            // gethostbyaddr returns the IP if lookup fails
            if ($reverseFqdn === $result->ip) {
                $result->addError('reverse_dns', "No PTR record found for {$result->ip}");

                return;
            }

            $result->reverseFqdn = strtolower(trim($reverseFqdn));
            $result->hasReverseDns = true;

            Log::debug('Reverse DNS lookup successful', [
                'ip' => $result->ip,
                'resolved_fqdn' => $result->reverseFqdn,
            ]);

        } catch (Exception $e) {
            $result->addError('reverse_dns_exception', "Reverse DNS lookup failed: {$e->getMessage()}");
            Log::error('Reverse DNS lookup exception', [
                'ip' => $result->ip,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Validate FCrDNS (forward and reverse DNS match)
     */
    protected function validateFcrDnsMatch(DnsValidationResult $result): void
    {
        if (! $result->hasForwardDns || ! $result->hasReverseDns) {
            $result->addError('fcrdns', 'FCrDNS validation failed: Missing forward or reverse DNS');

            return;
        }

        // Normalize both FQDNs (lowercase, trim)
        $expectedFqdn = strtolower(trim($result->fqdn));
        $reverseFqdn = strtolower(trim($result->reverseFqdn));

        // Check if they match
        if ($expectedFqdn !== $reverseFqdn) {
            $result->addError('fcrdns_mismatch',
                "FCrDNS validation failed: Forward DNS points to {$expectedFqdn} but PTR points to {$reverseFqdn}"
            );

            return;
        }

        // FCrDNS validation passed!
        $result->hasFcrDns = true;

        Log::info('FCrDNS validation PASSED', [
            'fqdn' => $result->fqdn,
            'ip' => $result->ip,
        ]);
    }

    /**
     * Quick check: Does FCrDNS pass?
     */
    public function passes(string $fqdn, string $ip): bool
    {
        try {
            $result = $this->validate($fqdn, $ip);

            return $result->hasFcrDns;
        } catch (Exception $e) {
            Log::error('FCrDNS validation exception', [
                'fqdn' => $fqdn,
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Validate forward DNS only (for web servers without mail)
     */
    public function validateForwardDnsOnly(string $fqdn): bool
    {
        try {
            $forwardIp = gethostbyname($fqdn);

            return $forwardIp !== $fqdn;
        } catch (Exception $e) {
            Log::error('Forward DNS validation failed', [
                'fqdn' => $fqdn,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Wait for DNS propagation (useful after creating records)
     */
    public function waitForPropagation(string $fqdn, string $ip, int $maxWaitSeconds = 30, int $intervalSeconds = 2): bool
    {
        $startTime = time();

        Log::info('Waiting for DNS propagation', [
            'fqdn' => $fqdn,
            'ip' => $ip,
            'max_wait' => $maxWaitSeconds,
        ]);

        while ((time() - $startTime) < $maxWaitSeconds) {
            if ($this->passes($fqdn, $ip)) {
                $elapsed = time() - $startTime;
                Log::info('DNS propagation complete', [
                    'fqdn' => $fqdn,
                    'elapsed_seconds' => $elapsed,
                ]);

                return true;
            }

            sleep($intervalSeconds);
        }

        Log::warning('DNS propagation timeout', [
            'fqdn' => $fqdn,
            'waited_seconds' => $maxWaitSeconds,
        ]);

        return false;
    }

    /**
     * Get detailed DNS information for troubleshooting
     */
    public function getDnsDebugInfo(string $fqdn, string $ip): array
    {
        $info = [
            'fqdn' => $fqdn,
            'ip' => $ip,
            'checks' => [],
        ];

        // Check forward DNS
        try {
            $forwardIp = gethostbyname($fqdn);
            $info['checks']['forward_dns'] = [
                'success' => ($forwardIp !== $fqdn),
                'resolved_ip' => ($forwardIp !== $fqdn) ? $forwardIp : null,
                'matches_expected' => ($forwardIp === $ip),
            ];
        } catch (Exception $e) {
            $info['checks']['forward_dns'] = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        // Check reverse DNS
        try {
            $reverseFqdn = gethostbyaddr($ip);
            $info['checks']['reverse_dns'] = [
                'success' => ($reverseFqdn !== $ip),
                'resolved_fqdn' => ($reverseFqdn !== $ip) ? $reverseFqdn : null,
                'matches_expected' => (strtolower($reverseFqdn) === strtolower($fqdn)),
            ];
        } catch (Exception $e) {
            $info['checks']['reverse_dns'] = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        // FCrDNS status
        $info['fcrdns_valid'] = (
            isset($info['checks']['forward_dns']['matches_expected']) &&
            isset($info['checks']['reverse_dns']['matches_expected']) &&
            $info['checks']['forward_dns']['matches_expected'] &&
            $info['checks']['reverse_dns']['matches_expected']
        );

        return $info;
    }
}
