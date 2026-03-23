<?php

declare(strict_types=1);

namespace PhilipRehberger\HttpRetry;

final class RetryPolicy
{
    private int $lastDecorrelatedDelay = 0;

    /**
     * @param  int  $maxRetries  Maximum number of retry attempts
     * @param  int  $baseDelayMs  Base delay in milliseconds
     * @param  int  $maxDelayMs  Maximum delay cap in milliseconds
     * @param  float  $multiplier  Backoff multiplier
     * @param  bool  $jitter  Whether to add random jitter
     * @param  array<int>  $retryableStatusCodes  HTTP status codes that trigger a retry
     * @param  JitterMode  $jitterMode  Jitter algorithm to use
     * @param  ?callable  $beforeRetry  Callback invoked before each retry attempt
     * @param  ?callable  $afterRetry  Callback invoked after each retry attempt
     */
    public function __construct(
        public readonly int $maxRetries = 3,
        public readonly int $baseDelayMs = 100,
        public readonly int $maxDelayMs = 10000,
        public readonly float $multiplier = 2.0,
        public readonly bool $jitter = true,
        public readonly array $retryableStatusCodes = [429, 500, 502, 503, 504],
        public readonly JitterMode $jitterMode = JitterMode::Full,
        /** @var ?callable */
        private readonly mixed $beforeRetry = null,
        /** @var ?callable */
        private readonly mixed $afterRetry = null,
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
            $delay = $this->applyJitter($delay, $attempt);
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

    /**
     * Invoke the before-retry callback if configured.
     */
    public function invokeBeforeRetry(int $attempt, ?\Throwable $error): void
    {
        if ($this->beforeRetry !== null) {
            ($this->beforeRetry)($attempt, $error);
        }
    }

    /**
     * Invoke the after-retry callback if configured.
     */
    public function invokeAfterRetry(int $attempt, ?\Throwable $error): void
    {
        if ($this->afterRetry !== null) {
            ($this->afterRetry)($attempt, $error);
        }
    }

    /**
     * Apply jitter to the delay based on the configured jitter mode.
     */
    private function applyJitter(int $delay, int $attempt): int
    {
        return match ($this->jitterMode) {
            JitterMode::Full => mt_rand(0, $delay),
            JitterMode::Equal => (int) ($delay / 2) + mt_rand(0, (int) ($delay / 2)),
            JitterMode::Decorrelated => $this->decorrelatedJitter($delay, $attempt),
        };
    }

    /**
     * Calculate decorrelated jitter delay.
     */
    private function decorrelatedJitter(int $delay, int $attempt): int
    {
        $previous = $this->lastDecorrelatedDelay > 0
            ? $this->lastDecorrelatedDelay
            : $this->baseDelayMs;

        $jittered = mt_rand($this->baseDelayMs, (int) ($previous * 3));
        $jittered = min($jittered, $this->maxDelayMs);

        $this->lastDecorrelatedDelay = $jittered;

        return $jittered;
    }
}
