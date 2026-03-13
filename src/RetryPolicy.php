<?php

declare(strict_types=1);

namespace PhilipRehberger\HttpRetry;

final class RetryPolicy
{
    /**
     * @param  int  $maxRetries  Maximum number of retry attempts
     * @param  int  $baseDelayMs  Base delay in milliseconds
     * @param  int  $maxDelayMs  Maximum delay cap in milliseconds
     * @param  float  $multiplier  Backoff multiplier
     * @param  bool  $jitter  Whether to add random jitter
     * @param  array<int>  $retryableStatusCodes  HTTP status codes that trigger a retry
     */
    public function __construct(
        public readonly int $maxRetries = 3,
        public readonly int $baseDelayMs = 100,
        public readonly int $maxDelayMs = 10000,
        public readonly float $multiplier = 2.0,
        public readonly bool $jitter = true,
        public readonly array $retryableStatusCodes = [429, 500, 502, 503, 504],
    ) {}

    /**
     * Create a policy builder for fluent construction.
     */
    public static function builder(): RetryPolicyBuilder
    {
        return new RetryPolicyBuilder;
    }

    /**
     * Calculate the delay for a given attempt number (0-based).
     */
    public function calculateDelay(int $attempt): int
    {
        $delay = (int) ($this->baseDelayMs * ($this->multiplier ** $attempt));
        $delay = min($delay, $this->maxDelayMs);

        if ($this->jitter) {
            $delay = mt_rand((int) ($delay * 0.5), $delay);
        }

        return $delay;
    }

    /**
     * Check if a status code is retryable.
     */
    public function isRetryable(int $statusCode): bool
    {
        return in_array($statusCode, $this->retryableStatusCodes, true);
    }
}
