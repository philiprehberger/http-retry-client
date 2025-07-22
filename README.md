# PHP HTTP Retry Client

[![Tests](https://github.com/philiprehberger/http-retry-client/actions/workflows/tests.yml/badge.svg)](https://github.com/philiprehberger/http-retry-client/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/philiprehberger/http-retry-client.svg)](https://packagist.org/packages/philiprehberger/http-retry-client)
[![Last updated](https://img.shields.io/github/last-commit/philiprehberger/http-retry-client)](https://github.com/philiprehberger/http-retry-client/commits/main)

HTTP client wrapper with automatic retries, exponential backoff, jitter, circuit breaker, and request logging.

## Requirements

- PHP 8.2+

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

### Jitter Modes

Control the jitter algorithm used for backoff delays:

```php
use PhilipRehberger\HttpRetry\JitterMode;
use PhilipRehberger\HttpRetry\RetryPolicy;

// Full jitter (default): rand(0, delay)
$policy = RetryPolicy::builder()
    ->jitterMode(JitterMode::Full)
    ->build();

// Equal jitter: delay/2 + rand(0, delay/2)
$policy = RetryPolicy::builder()
    ->jitterMode(JitterMode::Equal)
    ->build();

// Decorrelated jitter: rand(base, previous * 3)
$policy = RetryPolicy::builder()
    ->jitterMode(JitterMode::Decorrelated)
    ->build();
```

### Retry Hooks

Register callbacks that run before and after each retry attempt:

```php
use PhilipRehberger\HttpRetry\RetryPolicy;

$policy = RetryPolicy::builder()
    ->beforeRetry(function (int $attempt, ?\Throwable $error): void {
        echo "Retrying attempt {$attempt}...\n";
    })
    ->afterRetry(function (int $attempt, ?\Throwable $error): void {
        if ($error !== null) {
            echo "Attempt {$attempt} failed: {$error->getMessage()}\n";
        }
    })
    ->build();
```

### Custom Retryable Status Codes

```php
$policy = RetryPolicy::builder()
    ->retryOn([408, 429, 500, 502, 503, 504])
    ->build();
```

### Circuit Breaker

Protect downstream services with a circuit breaker that opens after consecutive failures:

```php
use PhilipRehberger\HttpRetry\CircuitBreakerWrapper;
use PhilipRehberger\HttpRetry\Exceptions\CircuitBreakerOpenException;

$breaker = new CircuitBreakerWrapper(failureThreshold: 5, recoveryTimeoutSeconds: 30);

try {
    $result = $breaker->execute(fn () => $client->send($request));
} catch (CircuitBreakerOpenException $e) {
    // Circuit is open, requests are being rejected
}

// Or configure via the builder
$builder = RetryPolicy::builder()
    ->withCircuitBreaker(failureThreshold: 5, recoveryTimeout: 30);
```

The circuit breaker transitions through three states:
- **Closed**: Requests flow normally; failures are counted
- **Open**: Requests are rejected immediately with `CircuitBreakerOpenException`
- **Half-Open**: After the recovery timeout, one request is allowed through to test recovery

### Request Logging

Log HTTP requests, responses, and failures for observability:

```php
use PhilipRehberger\HttpRetry\RequestLogger;

$logger = new RequestLogger(function (array $entry): void {
    // $entry contains: event, method, url, attempt, status_code, duration_ms, error, etc.
    error_log(json_encode($entry));
}, logBodies: true);

$logger->logRequest($request, attempt: 1);
$logger->logResponse($response, attempt: 1, durationMs: 42.5);
$logger->logFailure($exception, attempt: 1);

// Or configure via the builder
$builder = RetryPolicy::builder()
    ->withLogger(fn (array $entry) => error_log(json_encode($entry)), logBodies: false);
```

### Timeout Configuration

Set connection and request timeouts:

```php
$policy = RetryPolicy::builder()
    ->connectionTimeout(5000)  // 5 seconds
    ->requestTimeout(30000)    // 30 seconds
    ->build();

// Timeouts can also be set per-request
$request = new HttpRequest(
    method: 'GET',
    url: 'https://api.example.com/data',
    connectionTimeoutMs: 3000,
    requestTimeoutMs: 15000,
);
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
| `jitterMode` | `JitterMode` | `JitterMode::Full` | Jitter algorithm (`Full`, `Equal`, `Decorrelated`) |
| `beforeRetry` | `?callable` | `null` | Callback invoked before each retry `(int $attempt, ?\Throwable $error)` |
| `afterRetry` | `?callable` | `null` | Callback invoked after each retry `(int $attempt, ?\Throwable $error)` |
| `connectionTimeoutMs` | `?int` | `null` | Default connection timeout in milliseconds |
| `requestTimeoutMs` | `?int` | `null` | Default request timeout in milliseconds |

### `RetryPolicyBuilder`

| Method | Description |
|---|---|
| `maxRetries(int $maxRetries)` | Set max retry attempts |
| `baseDelay(int $ms)` | Set base delay in milliseconds |
| `maxDelay(int $ms)` | Set max delay cap in milliseconds |
| `multiplier(float $multiplier)` | Set backoff multiplier |
| `withJitter(bool $jitter)` | Enable/disable jitter |
| `withoutJitter()` | Disable jitter |
| `retryOn(array $codes)` | Set retryable status codes |
| `jitterMode(JitterMode $mode)` | Set jitter algorithm |
| `beforeRetry(callable $callback)` | Register before-retry callback |
| `afterRetry(callable $callback)` | Register after-retry callback |
| `connectionTimeout(int $ms)` | Set connection timeout in milliseconds |
| `requestTimeout(int $ms)` | Set request timeout in milliseconds |
| `withCircuitBreaker(int $failureThreshold, int $recoveryTimeout)` | Enable circuit breaker |
| `withLogger(callable $logger, bool $logBodies)` | Enable request/response logging |
| `build()` | Build the `RetryPolicy` |

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

### `CircuitBreakerWrapper`

| Method | Description |
|---|---|
| `__construct(int $failureThreshold, int $recoveryTimeoutSeconds)` | Create a circuit breaker |
| `execute(callable $action): mixed` | Execute action through the circuit breaker |
| `isOpen(): bool` | Check if circuit is open |
| `isClosed(): bool` | Check if circuit is closed |
| `state(): string` | Get current state (`closed`, `open`, `half_open`) |
| `reset(): void` | Reset to closed state |
| `failureCount(): int` | Get current failure count |

### `RequestLogger`

| Method | Description |
|---|---|
| `__construct(callable $logger, bool $logBodies)` | Create a logger |
| `logRequest(HttpRequest $request, int $attempt): void` | Log an outgoing request |
| `logResponse(HttpResponse $response, int $attempt, float $durationMs): void` | Log a response |
| `logFailure(\Throwable $exception, int $attempt): void` | Log a failure |

### `MaxRetriesExceededException`

| Property | Type | Description |
|---|---|---|
| `attempts` | `int` | Total number of attempts made |

### `CircuitBreakerOpenException`

Thrown when a request is rejected because the circuit breaker is in the open state.

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

## Support

If you find this project useful:

⭐ [Star the repo](https://github.com/philiprehberger/http-retry-client)

🐛 [Report issues](https://github.com/philiprehberger/http-retry-client/issues?q=is%3Aissue+is%3Aopen+label%3Abug)

💡 [Suggest features](https://github.com/philiprehberger/http-retry-client/issues?q=is%3Aissue+is%3Aopen+label%3Aenhancement)

❤️ [Sponsor development](https://github.com/sponsors/philiprehberger)

🌐 [All Open Source Projects](https://philiprehberger.com/open-source-packages)

💻 [GitHub Profile](https://github.com/philiprehberger)

🔗 [LinkedIn Profile](https://www.linkedin.com/in/philiprehberger)

## License

[MIT](LICENSE)
