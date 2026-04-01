# Changelog

All notable changes to `http-retry-client` will be documented in this file.

## [Unreleased]

## [1.2.0] - 2026-04-01

### Added
- Circuit breaker integration via `CircuitBreakerWrapper` with closed/open/half-open states
- `withCircuitBreaker()` method on `RetryPolicyBuilder` for fluent configuration
- `CircuitBreakerOpenException` thrown when circuit is open and requests are rejected
- Request/response logging via `RequestLogger` with configurable body logging
- `withLogger()` method on `RetryPolicyBuilder` for fluent configuration
- Timeout configuration via `connectionTimeout()` and `requestTimeout()` on `RetryPolicyBuilder`
- `connectionTimeoutMs` and `requestTimeoutMs` properties on `RetryPolicy` and `HttpRequest`

## [1.1.1] - 2026-03-31

### Changed
- Standardize README to 3-badge format with emoji Support section
- Update CI checkout action to v5 for Node.js 24 compatibility
- Add GitHub issue templates, dependabot config, and PR template

## [1.1.0] - 2026-03-22

### Added
- `JitterMode` backed string enum with `Full`, `Equal`, and `Decorrelated` modes
- `jitterMode(JitterMode $mode)` method on `RetryPolicyBuilder`
- `beforeRetry(callable $callback)` hook on `RetryPolicyBuilder`
- `afterRetry(callable $callback)` hook on `RetryPolicyBuilder`
- Jitter mode integration into delay calculation with per-mode algorithms
- Retry hooks are invoked during the retry loop with attempt number and error context

### Changed
- `RetryPolicy::calculateDelay()` now applies jitter based on the configured `JitterMode` (default: `Full`)

## [1.0.3] - 2026-03-23

### Fixed
- Standardize CHANGELOG preamble to use package name

## [1.0.2] - 2026-03-17

### Changed
- Standardized package metadata, README structure, and CI workflow per package guide

## [1.0.1] - 2026-03-16

### Changed
- Standardize composer.json: add homepage, scripts
- Add Development section to README

## [1.0.0] - 2026-03-13

### Added
- `RetryClient` with automatic retry logic and exponential backoff
- `RetryPolicy` configuration value object with sensible defaults
- `RetryPolicyBuilder` for fluent policy construction
- `RetryResult` value object with attempt tracking
- `HttpExecutor` contract for pluggable HTTP execution
- `HttpRequest` and `HttpResponse` value objects
- `MaxRetriesExceededException` for exhausted retries
- Retry-After header support for 429 responses
- Configurable jitter for backoff delays
- Configurable retryable status codes
