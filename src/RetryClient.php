<?php

declare(strict_types=1);

namespace PhilipRehberger\HttpRetry;

use PhilipRehberger\HttpRetry\Contracts\HttpExecutor;
use PhilipRehberger\HttpRetry\Exceptions\MaxRetriesExceededException;

final class RetryClient
{
    public function __construct(
        private readonly HttpExecutor $executor,
        private readonly RetryPolicy $policy = new RetryPolicy,
    ) {}

    /**
     * Send an HTTP request with automatic retries on failure.
     *
     * @throws MaxRetriesExceededException
     */
    public function send(HttpRequest $request): RetryResult
    {
        $lastResponse = null;
        $attempts = 0;
        $totalDelay = 0;

        for ($attempt = 0; $attempt <= $this->policy->maxRetries; $attempt++) {
            $attempts++;

            try {
                $response = $this->executor->execute($request);
                $lastResponse = $response;

                if (! $this->policy->isRetryable($response->statusCode)) {
                    return new RetryResult(
                        statusCode: $response->statusCode,
                        body: $response->body,
                        headers: $response->headers,
                        attempts: $attempts,
                        totalDelayMs: $totalDelay,
                        wasRetried: $attempt > 0,
                    );
                }

                // Check for Retry-After header on 429
                $delay = $this->getDelay($response, $attempt);
                $totalDelay += $delay;
                usleep($delay * 1000);
            } catch (MaxRetriesExceededException $e) {
                throw $e;
            } catch (\Throwable $e) {
                if ($attempt >= $this->policy->maxRetries) {
                    throw new MaxRetriesExceededException(
                        "Max retries ({$this->policy->maxRetries}) exceeded.",
                        $attempts,
                        $e,
                    );
                }
                $delay = $this->policy->calculateDelay($attempt);
                $totalDelay += $delay;
                usleep($delay * 1000);
            }
        }

        // All retries exhausted with retryable status codes
        if ($lastResponse !== null) {
            throw new MaxRetriesExceededException(
                "Max retries ({$this->policy->maxRetries}) exceeded. Last status: {$lastResponse->statusCode}.",
                $attempts,
            );
        }

        throw new MaxRetriesExceededException(
            "Max retries ({$this->policy->maxRetries}) exceeded.",
            $attempts,
        );
    }

    private function getDelay(HttpResponse $response, int $attempt): int
    {
        // Respect Retry-After header if present (for 429 responses)
        if ($response->statusCode === 429) {
            $retryAfter = $response->header('Retry-After');
            if ($retryAfter !== null && is_numeric($retryAfter)) {
                return min((int) ($retryAfter * 1000), $this->policy->maxDelayMs);
            }
        }

        return $this->policy->calculateDelay($attempt);
    }
}
