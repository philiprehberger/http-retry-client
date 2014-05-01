<?php

declare(strict_types=1);

namespace PhilipRehberger\HttpRetry;

final class HttpRequest
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly array $headers = [],
        public readonly ?string $body = null,
    ) {}
}
