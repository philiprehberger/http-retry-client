<?php

declare(strict_types=1);

namespace PhilipRehberger\HttpRetry\Exceptions;

use RuntimeException;

class CircuitBreakerOpenException extends RuntimeException
{
    public function __construct(
        string $message = 'Circuit breaker is open.',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
