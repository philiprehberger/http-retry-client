<?php

declare(strict_types=1);

namespace PhilipRehberger\HttpRetry;

final class RequestLogger
{
    /** @var callable(array<string, mixed>): void */
    private $logger;

    /**
     * @param  callable(array<string, mixed>): void  $logger  Callback that receives log entry arrays
     * @param  bool  $logBodies  Whether to include request/response bodies in log entries
     */
    public function __construct(
        callable $logger,
        private readonly bool $logBodies = false,
    ) {
        $this->logger = $logger;
    }

    /**
     * Log an outgoing HTTP request.
     */
    public function logRequest(HttpRequest $request, int $attempt): void
    {
        $entry = [
            'event' => 'request',
            'method' => $request->method,
            'url' => $request->url,
            'attempt' => $attempt,
        ];

        if ($this->logBodies && $request->body !== null) {
            $entry['body'] = $request->body;
        }

        ($this->logger)($entry);
    }

    /**
     * Log an HTTP response received.
     */
    public function logResponse(HttpResponse $response, int $attempt, float $durationMs): void
    {
        $entry = [
            'event' => 'response',
            'status_code' => $response->statusCode,
            'attempt' => $attempt,
            'duration_ms' => $durationMs,
        ];

        if ($this->logBodies) {
            $entry['body'] = $response->body;
        }

        ($this->logger)($entry);
    }

    /**
     * Log a failed request attempt.
     */
    public function logFailure(\Throwable $exception, int $attempt): void
    {
        ($this->logger)([
            'event' => 'failure',
            'attempt' => $attempt,
            'error' => $exception->getMessage(),
            'exception_class' => get_class($exception),
        ]);
    }
}
