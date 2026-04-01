<?php

declare(strict_types=1);

namespace PhilipRehberger\HttpRetry;

use PhilipRehberger\HttpRetry\Exceptions\CircuitBreakerOpenException;

final class CircuitBreakerWrapper
{
    private const STATE_CLOSED = 'closed';

    private const STATE_OPEN = 'open';

    private const STATE_HALF_OPEN = 'half_open';

    private string $state = self::STATE_CLOSED;

    private int $failureCount = 0;

    private ?float $lastFailureTime = null;

    /**
     * @param  int  $failureThreshold  Number of consecutive failures before opening the circuit
     * @param  int  $recoveryTimeoutSeconds  Seconds to wait before transitioning from open to half-open
     */
    public function __construct(
        private readonly int $failureThreshold = 5,
        private readonly int $recoveryTimeoutSeconds = 30,
    ) {}

    /**
     * Execute an action through the circuit breaker.
     *
     * @template T
     *
     * @param  callable(): T  $action
     * @return T
     *
     * @throws CircuitBreakerOpenException
     */
    public function execute(callable $action): mixed
    {
        if ($this->state === self::STATE_OPEN) {
            if ($this->shouldAttemptRecovery()) {
                $this->state = self::STATE_HALF_OPEN;
            } else {
                throw new CircuitBreakerOpenException(
                    'Circuit breaker is open. Request rejected.',
                );
            }
        }

        try {
            $result = $action();
            $this->onSuccess();

            return $result;
        } catch (\Throwable $e) {
            $this->onFailure();

            throw $e;
        }
    }

    /**
     * Check if the circuit breaker is in the open state.
     */
    public function isOpen(): bool
    {
        return $this->state === self::STATE_OPEN;
    }

    /**
     * Check if the circuit breaker is in the closed state.
     */
    public function isClosed(): bool
    {
        return $this->state === self::STATE_CLOSED;
    }

    /**
     * Get the current circuit breaker state.
     */
    public function state(): string
    {
        return $this->state;
    }

    /**
     * Reset the circuit breaker to its initial closed state.
     */
    public function reset(): void
    {
        $this->state = self::STATE_CLOSED;
        $this->failureCount = 0;
        $this->lastFailureTime = null;
    }

    /**
     * Get the current failure count.
     */
    public function failureCount(): int
    {
        return $this->failureCount;
    }

    private function onSuccess(): void
    {
        if ($this->state === self::STATE_HALF_OPEN) {
            $this->reset();

            return;
        }

        $this->failureCount = 0;
    }

    private function onFailure(): void
    {
        $this->failureCount++;
        $this->lastFailureTime = microtime(true);

        if ($this->failureCount >= $this->failureThreshold) {
            $this->state = self::STATE_OPEN;
        }
    }

    private function shouldAttemptRecovery(): bool
    {
        if ($this->lastFailureTime === null) {
            return false;
        }

        $elapsed = microtime(true) - $this->lastFailureTime;

        return $elapsed >= $this->recoveryTimeoutSeconds;
    }
}
