<?php

namespace NetServa\Core\Exceptions;

use Exception;

/**
 * Exception thrown when a VHost cannot be found in database or filesystem
 */
class VHostNotFoundException extends Exception
{
    public function __construct(string $message = 'VHost not found', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
