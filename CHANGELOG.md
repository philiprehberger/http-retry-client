# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

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
