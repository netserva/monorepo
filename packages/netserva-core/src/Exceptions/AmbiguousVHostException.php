<?php

namespace NetServa\Core\Exceptions;

use Exception;

/**
 * Exception thrown when multiple VHosts match the search criteria
 */
class AmbiguousVHostException extends Exception
{
    protected array $matches = [];

    public function __construct(string $message = 'Multiple VHosts found', array $matches = [], int $code = 0, ?Exception $previous = null)
    {
        $this->matches = $matches;
        parent::__construct($message, $code, $previous);
    }

    public function getMatches(): array
    {
        return $this->matches;
    }
}
