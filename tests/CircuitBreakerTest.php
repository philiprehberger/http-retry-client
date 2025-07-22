<?php

declare(strict_types=1);

namespace PhilipRehberger\HttpRetry\Tests;

use PhilipRehberger\HttpRetry\CircuitBreakerWrapper;
use PhilipRehberger\HttpRetry\Exceptions\CircuitBreakerOpenException;
use PHPUnit\Framework\TestCase;

final class CircuitBreakerTest extends TestCase
{
    public function test_starts_in_closed_state(): void
    {
        $breaker = new CircuitBreakerWrapper;

        $this->assertTrue($breaker->isClosed());
        $this->assertFalse($breaker->isOpen());
        $this->assertSame('closed', $breaker->state());
    }

    public function test_remains_closed_on_success(): void
    {
        $breaker = new CircuitBreakerWrapper(failureThreshold: 3);

        $result = $breaker->execute(fn () => 'ok');

        $this->assertSame('ok', $result);
        $this->assertTrue($breaker->isClosed());
        $this->assertSame(0, $breaker->failureCount());
    }

    public function test_opens_after_reaching_failure_threshold(): void
    {
        $breaker = new CircuitBreakerWrapper(failureThreshold: 3);

        for ($i = 0; $i < 3; $i++) {
            try {
                $breaker->execute(function (): never {
                    throw new \RuntimeException('fail');
                });
            } catch (\RuntimeException) {
                // expected
            }
        }

        $this->assertTrue($breaker->isOpen());
        $this->assertSame('open', $breaker->state());
        $this->assertSame(3, $breaker->failureCount());
    }

    public function test_rejects_requests_when_open(): void
    {
        $breaker = new CircuitBreakerWrapper(failureThreshold: 2, recoveryTimeoutSeconds: 60);

        // Trigger enough failures to open
        for ($i = 0; $i < 2; $i++) {
            try {
                $breaker->execute(function (): never {
                    throw new \RuntimeException('fail');
                });
            } catch (\RuntimeException) {
                // expected
            }
        }

        $this->assertTrue($breaker->isOpen());

        $this->expectException(CircuitBreakerOpenException::class);
        $breaker->execute(fn () => 'should not run');
    }

    public function test_does_not_open_before_threshold(): void
    {
        $breaker = new CircuitBreakerWrapper(failureThreshold: 5);

        for ($i = 0; $i < 4; $i++) {
            try {
                $breaker->execute(function (): never {
                    throw new \RuntimeException('fail');
                });
            } catch (\RuntimeException) {
                // expected
            }
        }

        $this->assertFalse($breaker->isOpen());
        $this->assertSame(4, $breaker->failureCount());
    }

    public function test_success_resets_failure_count_when_closed(): void
    {
        $breaker = new CircuitBreakerWrapper(failureThreshold: 5);

        // Add some failures
        for ($i = 0; $i < 3; $i++) {
            try {
                $breaker->execute(function (): never {
                    throw new \RuntimeException('fail');
                });
            } catch (\RuntimeException) {
                // expected
            }
        }

        $this->assertSame(3, $breaker->failureCount());

        // Success resets count
        $breaker->execute(fn () => 'ok');

        $this->assertSame(0, $breaker->failureCount());
        $this->assertTrue($breaker->isClosed());
    }

    public function test_reset_returns_to_closed_state(): void
    {
        $breaker = new CircuitBreakerWrapper(failureThreshold: 2);

        for ($i = 0; $i < 2; $i++) {
            try {
                $breaker->execute(function (): never {
                    throw new \RuntimeException('fail');
                });
            } catch (\RuntimeException) {
                // expected
            }
        }

        $this->assertTrue($breaker->isOpen());

        $breaker->reset();

        $this->assertTrue($breaker->isClosed());
        $this->assertSame(0, $breaker->failureCount());
    }

    public function test_half_open_transitions_to_closed_on_success(): void
    {
        // Use a recovery timeout of 0 so it transitions immediately
        $breaker = new CircuitBreakerWrapper(failureThreshold: 1, recoveryTimeoutSeconds: 0);

        try {
            $breaker->execute(function (): never {
                throw new \RuntimeException('fail');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertTrue($breaker->isOpen());

        // With 0 second recovery, next call should attempt half-open
        $result = $breaker->execute(fn () => 'recovered');

        $this->assertSame('recovered', $result);
        $this->assertTrue($breaker->isClosed());
        $this->assertSame('closed', $breaker->state());
    }

    public function test_half_open_returns_to_open_on_failure(): void
    {
        $breaker = new CircuitBreakerWrapper(failureThreshold: 1, recoveryTimeoutSeconds: 0);

        try {
            $breaker->execute(function (): never {
                throw new \RuntimeException('fail');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertTrue($breaker->isOpen());

        // Half-open attempt that fails should re-open
        try {
            $breaker->execute(function (): never {
                throw new \RuntimeException('still failing');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertTrue($breaker->isOpen());
    }

    public function test_propagates_action_return_value(): void
    {
        $breaker = new CircuitBreakerWrapper;

        $result = $breaker->execute(fn () => ['key' => 'value']);

        $this->assertSame(['key' => 'value'], $result);
    }

    public function test_propagates_exception_from_action(): void
    {
        $breaker = new CircuitBreakerWrapper(failureThreshold: 10);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('test error');

        $breaker->execute(function (): never {
            throw new \InvalidArgumentException('test error');
        });
    }
}
