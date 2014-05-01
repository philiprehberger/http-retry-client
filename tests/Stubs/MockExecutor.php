<?php

declare(strict_types=1);

namespace PhilipRehberger\HttpRetry\Tests\Stubs;

use PhilipRehberger\HttpRetry\Contracts\HttpExecutor;
use PhilipRehberger\HttpRetry\HttpRequest;
use PhilipRehberger\HttpRetry\HttpResponse;

final class MockExecutor implements HttpExecutor
{
    private int $callIndex = 0;

    /** @var array<int, HttpResponse|\Throwable> */
    private array $responses = [];

    /**
     * @param  array<int, HttpResponse|\Throwable>  $responses
     */
    public function __construct(array $responses = [])
    {
        $this->responses = $responses;
    }

    public function execute(HttpRequest $request): HttpResponse
    {
        $response = $this->responses[$this->callIndex] ?? throw new \RuntimeException('No more mock responses configured.');
        $this->callIndex++;

        if ($response instanceof \Throwable) {
            throw $response;
        }

        return $response;
    }

    public function callCount(): int
    {
        return $this->callIndex;
    }
}
