<?php

declare(strict_types=1);

namespace PhilipRehberger\HttpRetry\Exceptions;

use RuntimeException;

class MaxRetriesExceededException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $attempts,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
