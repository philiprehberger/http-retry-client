# PHP HTTP Retry Client

[![Tests](https://github.com/philiprehberger/http-retry-client/actions/workflows/tests.yml/badge.svg)](https://github.com/philiprehberger/http-retry-client/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/philiprehberger/http-retry-client.svg)](https://packagist.org/packages/philiprehberger/http-retry-client)
[![License](https://img.shields.io/github/license/philiprehberger/http-retry-client)](LICENSE)

HTTP client wrapper with automatic retries, exponential backoff, and jitter.

## Requirements

- PHP ^8.2

## Installation

```bash
composer require philiprehberger/http-retry-client
```

## Usage

### Basic Usage

Implement the `HttpExecutor` interface to wrap your preferred HTTP client:

```php
use PhilipRehberger\HttpRetry\Contracts\HttpExecutor;
use PhilipRehberger\HttpRetry\HttpRequest;
use PhilipRehberger\HttpRetry\HttpResponse;
use PhilipRehberger\HttpRetry\RetryClient;

class CurlExecutor implements HttpExecutor
{
    public function execute(HttpRequest $request): HttpResponse
    {
        $ch = curl_init($request->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request->method);

        if ($request->body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request->body);
        }

        $body = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return new HttpResponse($statusCode, $body);
    }
}

$client = new RetryClient(new CurlExecutor());
$result = $client->send(new HttpRequest('GET', 'https://api.example.com/data'));

if ($result->successful()) {
    echo $result->body;
}
```

### Custom Retry Policy

```php
use PhilipRehberger\HttpRetry\RetryClient;
use PhilipRehberger\HttpRetry\RetryPolicy;

$policy = new RetryPolicy(
    maxRetries: 5,
    baseDelayMs: 200,
    maxDelayMs: 30000,
    multiplier: 2.0,
    jitter: true,
    retryableStatusCodes: [429, 500, 502, 503, 504],
);

$client = new RetryClient($executor, $policy);
```

### Fluent Builder

```php
use PhilipRehberger\HttpRetry\RetryPolicy;

$policy = RetryPolicy::builder()
    ->maxRetries(5)
    ->baseDelay(200)
    ->maxDelay(30000)
    ->multiplier(3.0)
    ->withoutJitter()
    ->retryOn([500, 502, 503])
    ->build();
```

### Handling Retry Results

```php
use PhilipRehberger\HttpRetry\Exceptions\MaxRetriesExceededException;

try {
    $result = $client->send($request);

    echo "Status: {$result->statusCode}\n";
    echo "Attempts: {$result->attempts}\n";
    echo "Total delay: {$result->totalDelayMs}ms\n";
    echo "Was retried: " . ($result->wasRetried ? 'yes' : 'no') . "\n";
} catch (MaxRetriesExceededException $e) {
    echo "Failed after {$e->attempts} attempts: {$e->getMessage()}\n";
}
```

### Custom Retryable Status Codes

```php
$policy = RetryPolicy::builder()
    ->retryOn([408, 429, 500, 502, 503, 504])
    ->build();
```

## API

### `RetryPolicy`

| Parameter | Type | Default | Description |
|---|---|---|---|
| `maxRetries` | `int` | `3` | Maximum number of retry attempts |
| `baseDelayMs` | `int` | `100` | Base delay in milliseconds |
| `maxDelayMs` | `int` | `10000` | Maximum delay cap in milliseconds |
| `multiplier` | `float` | `2.0` | Backoff multiplier |
| `jitter` | `bool` | `true` | Whether to add random jitter |
| `retryableStatusCodes` | `array<int>` | `[429, 500, 502, 503, 504]` | Status codes that trigger a retry |

### `RetryClient`

| Method | Description |
|---|---|
| `send(HttpRequest $request): RetryResult` | Send a request with automatic retries |

### `RetryResult`

| Property | Type | Description |
|---|---|---|
| `statusCode` | `int` | HTTP status code of the final response |
| `body` | `string` | Response body |
| `headers` | `array` | Response headers |
| `attempts` | `int` | Total number of attempts made |
| `totalDelayMs` | `int` | Total delay spent waiting (ms) |
| `wasRetried` | `bool` | Whether the request was retried |
| `successful()` | `bool` | Whether the status code is 2xx |

### `HttpExecutor` (Interface)

| Method | Description |
|---|---|
| `execute(HttpRequest $request): HttpResponse` | Execute an HTTP request |

### `MaxRetriesExceededException`

| Property | Type | Description |
|---|---|---|
| `attempts` | `int` | Total number of attempts made |

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

## License

MIT
