<?php

namespace NetServa\Dns\Exceptions;

use Exception;
use NetServa\Dns\ValueObjects\DnsValidationResult;

/**
 * DNS Validation Exception
 *
 * Thrown when DNS validation fails
 */
class DnsValidationException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?DnsValidationResult $validationResult = null,
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception from validation result
     */
    public static function fromValidationResult(DnsValidationResult $result): self
    {
        $message = "DNS validation failed for {$result->fqdn}";

        if (! empty($result->errors)) {
            $message .= ': '.implode(', ', $result->getErrors());
        }

        return new self($message, $result);
    }

    /**
     * Get validation result
     */
    public function getValidationResult(): ?DnsValidationResult
    {
        return $this->validationResult;
    }
}
