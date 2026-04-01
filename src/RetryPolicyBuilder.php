<?php

declare(strict_types=1);

namespace PhilipRehberger\HttpRetry;

final class RetryPolicyBuilder
{
    private int $maxRetries = 3;

    private int $baseDelayMs = 100;

    private int $maxDelayMs = 10000;

    private float $multiplier = 2.0;

    private bool $jitter = true;

    /** @var array<int> */
    private array $retryableStatusCodes = [429, 500, 502, 503, 504];

    private JitterMode $jitterMode = JitterMode::Full;

    /** @var ?callable */
    private mixed $beforeRetry = null;

    /** @var ?callable */
    private mixed $afterRetry = null;

    private ?int $connectionTimeoutMs = null;

    private ?int $requestTimeoutMs = null;

    private ?CircuitBreakerWrapper $circuitBreaker = null;

    private ?RequestLogger $requestLogger = null;

    /**
     * Set the maximum number of retry attempts.
     */
    public function maxRetries(int $maxRetries): self
    {
        $this->maxRetries = $maxRetries;

        return $this;
    }

    /**
     * Set the base delay in milliseconds.
     */
    public function baseDelay(int $ms): self
    {
        $this->baseDelayMs = $ms;

        return $this;
    }

    /**
     * Set the maximum delay cap in milliseconds.
     */
    public function maxDelay(int $ms): self
    {
        $this->maxDelayMs = $ms;

        return $this;
    }

    /**
     * Set the backoff multiplier.
     */
    public function multiplier(float $multiplier): self
    {
        $this->multiplier = $multiplier;

        return $this;
    }

    /**
     * Enable or disable jitter for backoff delays.
     */
    public function withJitter(bool $jitter = true): self
    {
        $this->jitter = $jitter;

        return $this;
    }

    /**
     * Disable jitter for backoff delays.
     */
    public function withoutJitter(): self
    {
        $this->jitter = false;

        return $this;
    }

    /**
     * Set the HTTP status codes that trigger a retry.
     *
     * @param  array<int>  $codes
     */
    public function retryOn(array $codes): self
    {
        $this->retryableStatusCodes = $codes;

        return $this;
    }

    /**
     * Set the jitter algorithm to use.
     */
    public function jitterMode(JitterMode $mode): self
    {
        $this->jitterMode = $mode;

        return $this;
    }

    /**
     * Register a callback invoked before each retry attempt.
     *
     * @param  callable(int, ?\Throwable): void  $callback
     */
    public function beforeRetry(callable $callback): self
    {
        $this->beforeRetry = $callback;

        return $this;
    }

    /**
     * Register a callback invoked after each retry attempt.
     *
     * @param  callable(int, ?\Throwable): void  $callback
     */
    public function afterRetry(callable $callback): self
    {
        $this->afterRetry = $callback;

        return $this;
    }

    /**
     * Set the connection timeout in milliseconds.
     */
    public function connectionTimeout(int $ms): self
    {
        $this->connectionTimeoutMs = $ms;

        return $this;
    }

    /**
     * Set the request timeout in milliseconds.
     */
    public function requestTimeout(int $ms): self
    {
        $this->requestTimeoutMs = $ms;

        return $this;
    }

    /**
     * Enable circuit breaker protection.
     *
     * @param  int  $failureThreshold  Number of consecutive failures before opening the circuit
     * @param  int  $recoveryTimeout  Seconds to wait before attempting recovery
     */
    public function withCircuitBreaker(int $failureThreshold = 5, int $recoveryTimeout = 30): self
    {
        $this->circuitBreaker = new CircuitBreakerWrapper($failureThreshold, $recoveryTimeout);

        return $this;
    }

    /**
     * Enable request/response logging.
     *
     * @param  callable(array<string, mixed>): void  $logger  Callback that receives log entry arrays
     * @param  bool  $logBodies  Whether to include request/response bodies in log entries
     */
    public function withLogger(callable $logger, bool $logBodies = false): self
    {
        $this->requestLogger = new RequestLogger($logger, $logBodies);

        return $this;
    }

    /**
     * Build the retry policy.
     */
    public function build(): RetryPolicy
    {
        return new RetryPolicy(
            maxRetries: $this->maxRetries,
            baseDelayMs: $this->baseDelayMs,
            maxDelayMs: $this->maxDelayMs,
            multiplier: $this->multiplier,
            jitter: $this->jitter,
            retryableStatusCodes: $this->retryableStatusCodes,
            jitterMode: $this->jitterMode,
            beforeRetry: $this->beforeRetry,
            afterRetry: $this->afterRetry,
            connectionTimeoutMs: $this->connectionTimeoutMs,
            requestTimeoutMs: $this->requestTimeoutMs,
        );
    }

    /**
     * Get the configured circuit breaker, if any.
     */
    public function getCircuitBreaker(): ?CircuitBreakerWrapper
    {
        return $this->circuitBreaker;
    }

    /**
     * Get the configured request logger, if any.
     */
    public function getRequestLogger(): ?RequestLogger
    {
        return $this->requestLogger;
    }
}
