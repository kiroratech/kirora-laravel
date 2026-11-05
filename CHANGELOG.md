# Changelog

All notable changes to `kirora/kirora-laravel` are documented here. This project follows
[Semantic Versioning](https://semver.org/); before 1.0, minor releases may include
breaking changes.

## 0.1.0 - Unreleased

First release, for Laravel 12 (PHP 8.2 or later) and Laravel 13 (PHP 8.3 or later), over
`kirora/kirora-php` 0.1.

- `KiroraServiceProvider` binds a `Kirora\Client` singleton, also as `kirora`, configured
  from `config/kirora.php` and the `KIRORA_*` environment variables. Requests report
  `kirora-laravel/0.1.0` in the `User-Agent`.
- The `Kirora\Laravel\Facades\Kirora` facade, with each service as a method:
  `Kirora::contacts()`.
- A webhook route, `POST /kirora/webhook`, which verifies each webhook's signature against
  the raw body and dispatches `WebhookReceived` and an event for each webhook type, such
  as `MessageDelivered`.
- Bind `kirora.http_client` to a PSR-18 client to send requests with it.
