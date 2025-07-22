<?php

declare(strict_types=1);

namespace PhilipRehberger\HttpRetry\Tests;

use PhilipRehberger\HttpRetry\HttpRequest;
use PhilipRehberger\HttpRetry\RetryPolicy;
use PHPUnit\Framework\TestCase;

final class TimeoutTest extends TestCase
{
    public function test_policy_stores_connection_timeout(): void
    {
        $policy = RetryPolicy::builder()
            ->connectionTimeout(5000)
            ->build();

        $this->assertSame(5000, $policy->connectionTimeoutMs);
    }

    public function test_policy_stores_request_timeout(): void
    {
        $policy = RetryPolicy::builder()
            ->requestTimeout(30000)
            ->build();

        $this->assertSame(30000, $policy->requestTimeoutMs);
    }

    public function test_policy_stores_both_timeouts(): void
    {
        $policy = RetryPolicy::builder()
            ->connectionTimeout(3000)
            ->requestTimeout(15000)
            ->build();

        $this->assertSame(3000, $policy->connectionTimeoutMs);
        $this->assertSame(15000, $policy->requestTimeoutMs);
    }

    public function test_policy_timeouts_default_to_null(): void
    {
        $policy = new RetryPolicy;

        $this->assertNull($policy->connectionTimeoutMs);
        $this->assertNull($policy->requestTimeoutMs);
    }

    public function test_policy_constructor_accepts_timeouts(): void
    {
        $policy = new RetryPolicy(
            connectionTimeoutMs: 2000,
            requestTimeoutMs: 10000,
        );

        $this->assertSame(2000, $policy->connectionTimeoutMs);
        $this->assertSame(10000, $policy->requestTimeoutMs);
    }

    public function test_request_stores_connection_timeout(): void
    {
        $request = new HttpRequest(
            method: 'GET',
            url: 'https://api.example.com',
            connectionTimeoutMs: 5000,
        );

        $this->assertSame(5000, $request->connectionTimeoutMs);
    }

    public function test_request_stores_request_timeout(): void
    {
        $request = new HttpRequest(
            method: 'GET',
            url: 'https://api.example.com',
            requestTimeoutMs: 30000,
        );

        $this->assertSame(30000, $request->requestTimeoutMs);
    }

    public function test_request_timeouts_default_to_null(): void
    {
        $request = new HttpRequest('GET', 'https://api.example.com');

        $this->assertNull($request->connectionTimeoutMs);
        $this->assertNull($request->requestTimeoutMs);
    }

    public function test_request_stores_both_timeouts(): void
    {
        $request = new HttpRequest(
            method: 'POST',
            url: 'https://api.example.com/data',
            connectionTimeoutMs: 3000,
            requestTimeoutMs: 15000,
        );

        $this->assertSame(3000, $request->connectionTimeoutMs);
        $this->assertSame(15000, $request->requestTimeoutMs);
    }

    public function test_timeouts_combined_with_other_builder_options(): void
    {
        $policy = RetryPolicy::builder()
            ->maxRetries(5)
            ->baseDelay(200)
            ->connectionTimeout(5000)
            ->requestTimeout(30000)
            ->withoutJitter()
            ->build();

        $this->assertSame(5, $policy->maxRetries);
        $this->assertSame(200, $policy->baseDelayMs);
        $this->assertSame(5000, $policy->connectionTimeoutMs);
        $this->assertSame(30000, $policy->requestTimeoutMs);
        $this->assertFalse($policy->jitter);
    }
}
