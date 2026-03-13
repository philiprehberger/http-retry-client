<?php

declare(strict_types=1);

namespace PhilipRehberger\HttpRetry;

final class RetryResult
{
    /**
     * @param  array<string, array<string>>  $headers
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
        public readonly array $headers,
        public readonly int $attempts,
        public readonly int $totalDelayMs,
        public readonly bool $wasRetried,
    ) {}

    public function successful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
}
