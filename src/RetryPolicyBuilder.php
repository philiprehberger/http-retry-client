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

    public function maxRetries(int $maxRetries): self
    {
        $this->maxRetries = $maxRetries;

        return $this;
    }

    public function baseDelay(int $ms): self
    {
        $this->baseDelayMs = $ms;

        return $this;
    }

    public function maxDelay(int $ms): self
    {
        $this->maxDelayMs = $ms;

        return $this;
    }

    public function multiplier(float $multiplier): self
    {
        $this->multiplier = $multiplier;

        return $this;
    }

    public function withJitter(bool $jitter = true): self
    {
        $this->jitter = $jitter;

        return $this;
    }

    public function withoutJitter(): self
    {
        $this->jitter = false;

        return $this;
    }

    /**
     * @param  array<int>  $codes
     */
    public function retryOn(array $codes): self
    {
        $this->retryableStatusCodes = $codes;

        return $this;
    }

    public function build(): RetryPolicy
    {
        return new RetryPolicy(
            maxRetries: $this->maxRetries,
            baseDelayMs: $this->baseDelayMs,
            maxDelayMs: $this->maxDelayMs,
            multiplier: $this->multiplier,
            jitter: $this->jitter,
            retryableStatusCodes: $this->retryableStatusCodes,
        );
    }
}
