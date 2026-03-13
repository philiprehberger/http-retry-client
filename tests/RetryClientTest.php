<?php

declare(strict_types=1);

namespace PhilipRehberger\HttpRetry\Tests;

use PhilipRehberger\HttpRetry\Exceptions\MaxRetriesExceededException;
use PhilipRehberger\HttpRetry\HttpRequest;
use PhilipRehberger\HttpRetry\HttpResponse;
use PhilipRehberger\HttpRetry\RetryClient;
use PhilipRehberger\HttpRetry\RetryPolicy;
use PhilipRehberger\HttpRetry\Tests\Stubs\MockExecutor;
use PHPUnit\Framework\TestCase;

final class RetryClientTest extends TestCase
{
    private function makeRequest(): HttpRequest
    {
        return new HttpRequest('GET', 'https://example.com/api');
    }

    private function makePolicy(): RetryPolicy
    {
        return new RetryPolicy(
            maxRetries: 3,
            baseDelayMs: 1,
            maxDelayMs: 10,
            jitter: false,
        );
    }

    public function test_successful_request_no_retry(): void
    {
        $executor = new MockExecutor([
            new HttpResponse(200, '{"ok":true}'),
        ]);

        $client = new RetryClient($executor, $this->makePolicy());
        $result = $client->send($this->makeRequest());

        $this->assertSame(200, $result->statusCode);
        $this->assertSame('{"ok":true}', $result->body);
        $this->assertSame(1, $result->attempts);
        $this->assertFalse($result->wasRetried);
    }

    public function test_retry_on_500_succeeds_second_attempt(): void
    {
        $executor = new MockExecutor([
            new HttpResponse(500, 'error'),
            new HttpResponse(200, 'ok'),
        ]);

        $client = new RetryClient($executor, $this->makePolicy());
        $result = $client->send($this->makeRequest());

        $this->assertSame(200, $result->statusCode);
        $this->assertSame('ok', $result->body);
        $this->assertSame(2, $result->attempts);
        $this->assertTrue($result->wasRetried);
    }

    public function test_retry_on_503_succeeds_third_attempt(): void
    {
        $executor = new MockExecutor([
            new HttpResponse(503, 'unavailable'),
            new HttpResponse(503, 'unavailable'),
            new HttpResponse(200, 'recovered'),
        ]);

        $client = new RetryClient($executor, $this->makePolicy());
        $result = $client->send($this->makeRequest());

        $this->assertSame(200, $result->statusCode);
        $this->assertSame('recovered', $result->body);
        $this->assertSame(3, $result->attempts);
        $this->assertTrue($result->wasRetried);
    }

    public function test_max_retries_exceeded_throws(): void
    {
        $executor = new MockExecutor([
            new HttpResponse(500, 'error'),
            new HttpResponse(500, 'error'),
            new HttpResponse(500, 'error'),
            new HttpResponse(500, 'error'),
        ]);

        $client = new RetryClient($executor, $this->makePolicy());

        $this->expectException(MaxRetriesExceededException::class);
        $this->expectExceptionMessage('Max retries (3) exceeded');

        $client->send($this->makeRequest());
    }

    public function test_non_retryable_status_passes_through(): void
    {
        $executor = new MockExecutor([
            new HttpResponse(400, 'bad request'),
        ]);

        $client = new RetryClient($executor, $this->makePolicy());
        $result = $client->send($this->makeRequest());

        $this->assertSame(400, $result->statusCode);
        $this->assertSame('bad request', $result->body);
        $this->assertSame(1, $result->attempts);
        $this->assertFalse($result->wasRetried);
    }

    public function test_result_tracks_attempt_count(): void
    {
        $executor = new MockExecutor([
            new HttpResponse(502, 'bad gateway'),
            new HttpResponse(502, 'bad gateway'),
            new HttpResponse(200, 'ok'),
        ]);

        $client = new RetryClient($executor, $this->makePolicy());
        $result = $client->send($this->makeRequest());

        $this->assertSame(3, $result->attempts);
    }

    public function test_result_was_retried_flag(): void
    {
        $executor = new MockExecutor([
            new HttpResponse(200, 'immediate'),
        ]);

        $client = new RetryClient($executor, $this->makePolicy());
        $result = $client->send($this->makeRequest());

        $this->assertFalse($result->wasRetried);

        $executor2 = new MockExecutor([
            new HttpResponse(500, 'error'),
            new HttpResponse(200, 'ok'),
        ]);

        $client2 = new RetryClient($executor2, $this->makePolicy());
        $result2 = $client2->send($this->makeRequest());

        $this->assertTrue($result2->wasRetried);
    }

    public function test_custom_retryable_status_codes(): void
    {
        $policy = new RetryPolicy(
            maxRetries: 2,
            baseDelayMs: 1,
            maxDelayMs: 10,
            jitter: false,
            retryableStatusCodes: [418],
        );

        $executor = new MockExecutor([
            new HttpResponse(418, "I'm a teapot"),
            new HttpResponse(200, 'ok'),
        ]);

        $client = new RetryClient($executor, $policy);
        $result = $client->send($this->makeRequest());

        $this->assertSame(200, $result->statusCode);
        $this->assertSame(2, $result->attempts);
    }

    public function test_delay_calculation_exponential_backoff(): void
    {
        $policy = new RetryPolicy(
            baseDelayMs: 100,
            maxDelayMs: 10000,
            multiplier: 2.0,
            jitter: false,
        );

        $this->assertSame(100, $policy->calculateDelay(0));
        $this->assertSame(200, $policy->calculateDelay(1));
        $this->assertSame(400, $policy->calculateDelay(2));
        $this->assertSame(800, $policy->calculateDelay(3));
    }

    public function test_delay_capped_at_max_delay(): void
    {
        $policy = new RetryPolicy(
            baseDelayMs: 1000,
            maxDelayMs: 5000,
            multiplier: 10.0,
            jitter: false,
        );

        // Attempt 0: 1000, Attempt 1: 10000 -> capped at 5000
        $this->assertSame(1000, $policy->calculateDelay(0));
        $this->assertSame(5000, $policy->calculateDelay(1));
        $this->assertSame(5000, $policy->calculateDelay(2));
    }

    public function test_policy_builder_fluent_api(): void
    {
        $policy = RetryPolicy::builder()
            ->maxRetries(5)
            ->baseDelay(200)
            ->maxDelay(20000)
            ->multiplier(3.0)
            ->withoutJitter()
            ->retryOn([500, 502])
            ->build();

        $this->assertSame(5, $policy->maxRetries);
        $this->assertSame(200, $policy->baseDelayMs);
        $this->assertSame(20000, $policy->maxDelayMs);
        $this->assertSame(3.0, $policy->multiplier);
        $this->assertFalse($policy->jitter);
        $this->assertSame([500, 502], $policy->retryableStatusCodes);
    }
}
