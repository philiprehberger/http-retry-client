<?php

declare(strict_types=1);

namespace PhilipRehberger\HttpRetry;

final class HttpResponse
{
    /**
     * @param  array<string, array<string>>  $headers
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
        public readonly array $headers = [],
    ) {}

    public function successful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function header(string $name): ?string
    {
        $lower = strtolower($name);
        foreach ($this->headers as $key => $values) {
            if (strtolower($key) === $lower) {
                return $values[0] ?? null;
            }
        }

        return null;
    }
}
