<?php

namespace NetServa\Dns\ValueObjects;

/**
 * DNS Validation Result
 *
 * Immutable value object representing the result of FCrDNS validation
 */
class DnsValidationResult
{
    public function __construct(
        public readonly string $fqdn,
        public readonly string $ip,
        public bool $hasForwardDns,
        public bool $hasReverseDns,
        public bool $hasFcrDns,
        public ?string $forwardIp,
        public ?string $reverseFqdn,
        public array $errors,
        public array $warnings
    ) {}

    /**
     * Add validation error
     */
    public function addError(string $code, string $message): void
    {
        $this->errors[$code] = $message;
    }

    /**
     * Add validation warning
     */
    public function addWarning(string $code, string $message): void
    {
        $this->warnings[$code] = $message;
    }

    /**
     * Check if validation passed (FCrDNS valid)
     */
    public function passed(): bool
    {
        return $this->hasFcrDns && empty($this->errors);
    }

    /**
     * Check if validation failed
     */
    public function failed(): bool
    {
        return ! $this->passed();
    }

    /**
     * Get all error messages
     */
    public function getErrors(): array
    {
        return array_values($this->errors);
    }

    /**
     * Get all warning messages
     */
    public function getWarnings(): array
    {
        return array_values($this->warnings);
    }

    /**
     * Get first error message
     */
    public function getFirstError(): ?string
    {
        return ! empty($this->errors) ? reset($this->errors) : null;
    }

    /**
     * Get summary of validation results
     */
    public function getSummary(): array
    {
        return [
            'fqdn' => $this->fqdn,
            'ip' => $this->ip,
            'forward_dns' => $this->hasForwardDns,
            'reverse_dns' => $this->hasReverseDns,
            'fcrdns' => $this->hasFcrDns,
            'passed' => $this->passed(),
            'error_count' => count($this->errors),
            'warning_count' => count($this->warnings),
        ];
    }

    /**
     * Get detailed output for CLI display
     */
    public function toCliOutput(): array
    {
        $output = [];

        // Forward DNS status
        $output[] = sprintf(
            '   Forward DNS (A):    %s %s',
            $this->hasForwardDns ? '✅' : '❌',
            $this->hasForwardDns ? "{$this->fqdn} → {$this->forwardIp}" : 'No A record found'
        );

        // Reverse DNS status
        $output[] = sprintf(
            '   Reverse DNS (PTR):  %s %s',
            $this->hasReverseDns ? '✅' : '❌',
            $this->hasReverseDns ? "{$this->ip} → {$this->reverseFqdn}" : 'No PTR record found'
        );

        // FCrDNS status
        $output[] = sprintf(
            '   FCrDNS:             %s %s',
            $this->hasFcrDns ? '✅' : '❌',
            $this->hasFcrDns ? 'Validation PASSED' : 'Validation FAILED'
        );

        // Add errors if any
        if (! empty($this->errors)) {
            $output[] = '';
            $output[] = '❌ Errors:';
            foreach ($this->errors as $error) {
                $output[] = "   • {$error}";
            }
        }

        // Add warnings if any
        if (! empty($this->warnings)) {
            $output[] = '';
            $output[] = '⚠️  Warnings:';
            foreach ($this->warnings as $warning) {
                $output[] = "   • {$warning}";
            }
        }

        return $output;
    }

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'fqdn' => $this->fqdn,
            'ip' => $this->ip,
            'validation' => [
                'forward_dns' => $this->hasForwardDns,
                'reverse_dns' => $this->hasReverseDns,
                'fcrdns' => $this->hasFcrDns,
            ],
            'resolved' => [
                'forward_ip' => $this->forwardIp,
                'reverse_fqdn' => $this->reverseFqdn,
            ],
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'passed' => $this->passed(),
        ];
    }
}
