<?php

declare(strict_types=1);

namespace PhilipRehberger\HttpRetry\Tests;

use PhilipRehberger\HttpRetry\HttpRequest;
use PhilipRehberger\HttpRetry\HttpResponse;
use PhilipRehberger\HttpRetry\RequestLogger;
use PHPUnit\Framework\TestCase;

final class RequestLoggerTest extends TestCase
{
    public function test_logs_request_event(): void
    {
        $entries = [];
        $logger = new RequestLogger(function (array $entry) use (&$entries): void {
            $entries[] = $entry;
        });

        $request = new HttpRequest('POST', 'https://api.example.com/data', body: '{"key":"value"}');
        $logger->logRequest($request, 1);

        $this->assertCount(1, $entries);
        $this->assertSame('request', $entries[0]['event']);
        $this->assertSame('POST', $entries[0]['method']);
        $this->assertSame('https://api.example.com/data', $entries[0]['url']);
        $this->assertSame(1, $entries[0]['attempt']);
        $this->assertArrayNotHasKey('body', $entries[0]);
    }

    public function test_logs_request_body_when_enabled(): void
    {
        $entries = [];
        $logger = new RequestLogger(function (array $entry) use (&$entries): void {
            $entries[] = $entry;
        }, logBodies: true);

        $request = new HttpRequest('POST', 'https://api.example.com/data', body: '{"key":"value"}');
        $logger->logRequest($request, 1);

        $this->assertCount(1, $entries);
        $this->assertSame('{"key":"value"}', $entries[0]['body']);
    }

    public function test_logs_request_without_body_when_null(): void
    {
        $entries = [];
        $logger = new RequestLogger(function (array $entry) use (&$entries): void {
            $entries[] = $entry;
        }, logBodies: true);

        $request = new HttpRequest('GET', 'https://api.example.com/data');
        $logger->logRequest($request, 1);

        $this->assertCount(1, $entries);
        $this->assertArrayNotHasKey('body', $entries[0]);
    }

    public function test_logs_response_event(): void
    {
        $entries = [];
        $logger = new RequestLogger(function (array $entry) use (&$entries): void {
            $entries[] = $entry;
        });

        $response = new HttpResponse(200, '{"ok":true}');
        $logger->logResponse($response, 1, 42.5);

        $this->assertCount(1, $entries);
        $this->assertSame('response', $entries[0]['event']);
        $this->assertSame(200, $entries[0]['status_code']);
        $this->assertSame(1, $entries[0]['attempt']);
        $this->assertSame(42.5, $entries[0]['duration_ms']);
        $this->assertArrayNotHasKey('body', $entries[0]);
    }

    public function test_logs_response_body_when_enabled(): void
    {
        $entries = [];
        $logger = new RequestLogger(function (array $entry) use (&$entries): void {
            $entries[] = $entry;
        }, logBodies: true);

        $response = new HttpResponse(200, '{"ok":true}');
        $logger->logResponse($response, 2, 100.0);

        $this->assertCount(1, $entries);
        $this->assertSame('{"ok":true}', $entries[0]['body']);
    }

    public function test_logs_failure_event(): void
    {
        $entries = [];
        $logger = new RequestLogger(function (array $entry) use (&$entries): void {
            $entries[] = $entry;
        });

        $exception = new \RuntimeException('Connection refused');
        $logger->logFailure($exception, 3);

        $this->assertCount(1, $entries);
        $this->assertSame('failure', $entries[0]['event']);
        $this->assertSame(3, $entries[0]['attempt']);
        $this->assertSame('Connection refused', $entries[0]['error']);
        $this->assertSame('RuntimeException', $entries[0]['exception_class']);
    }

    public function test_logs_multiple_events_in_sequence(): void
    {
        $entries = [];
        $logger = new RequestLogger(function (array $entry) use (&$entries): void {
            $entries[] = $entry;
        });

        $request = new HttpRequest('GET', 'https://api.example.com/data');
        $response = new HttpResponse(500, 'error');

        $logger->logRequest($request, 1);
        $logger->logResponse($response, 1, 150.0);
        $logger->logRequest($request, 2);
        $logger->logResponse(new HttpResponse(200, 'ok'), 2, 50.0);

        $this->assertCount(4, $entries);
        $this->assertSame('request', $entries[0]['event']);
        $this->assertSame('response', $entries[1]['event']);
        $this->assertSame(500, $entries[1]['status_code']);
        $this->assertSame('request', $entries[2]['event']);
        $this->assertSame('response', $entries[3]['event']);
        $this->assertSame(200, $entries[3]['status_code']);
    }

    public function test_failure_log_includes_nested_exception_class(): void
    {
        $entries = [];
        $logger = new RequestLogger(function (array $entry) use (&$entries): void {
            $entries[] = $entry;
        });

        $exception = new \InvalidArgumentException('Bad input');
        $logger->logFailure($exception, 1);

        $this->assertSame('InvalidArgumentException', $entries[0]['exception_class']);
    }
}
