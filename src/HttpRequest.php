<?php

declare(strict_types=1);

namespace PhilipRehberger\HttpRetry;

final class HttpRequest
{
    /**
     * @param  array<string, string>  $headers
     * @param  ?int  $connectionTimeoutMs  Connection timeout in milliseconds
     * @param  ?int  $requestTimeoutMs  Request timeout in milliseconds
     */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly array $headers = [],
        public readonly ?string $body = null,
        public readonly ?int $connectionTimeoutMs = null,
        public readonly ?int $requestTimeoutMs = null,
    ) {}
}
