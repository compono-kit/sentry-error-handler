# SentryErrorHandler

A `HandlesErrors` implementation that forwards messages and exceptions to Sentry via the `compono-kit/sentry-client` package.

## Installation

```bash
composer require compono-kit/sentry-error-handler
```

## Setup

Create a `SentryClient` with a `SentryClientConfig` and pass it to `SentryErrorHandler`. Call `install()` once at application bootstrap to apply error reporting settings.

```php
use ComponoKit\SentryClient\SentryClient;
use ComponoKit\SentryClient\SentryClientConfig;
use ComponoKit\ErrorHandlers\Sentry\SentryErrorHandler;

$client = new SentryClient(new SentryClientConfig([
    'dsn'              => 'https://your-dsn@sentry.io/123',
    'environment'      => 'production',
    'release'          => '1.4.2',
    'error-reporting'  => E_ALL,
    'display-errors'   => false,
]));

$handler = new SentryErrorHandler($client);
$handler->install();
```

## Reporting errors

### Exceptions

```php
try {
    $order->process();
} catch (\Throwable $exception) {
    // Default message: "RuntimeException occurred"
    $handler->exception($exception);

    // Custom message
    $handler->exception($exception, 'Order processing failed');
}
```

The exception's message, file, line, and stack trace are automatically attached as context.

### Severity levels

```php
$handler->warning('Retry limit almost reached');
$handler->error('Payment gateway returned unexpected status');
$handler->critical('Database connection lost');
$handler->alert('Disk space below 5 %');
$handler->emergency('Service is completely unavailable');
```

## Context

Pass a flat array for simple key/value details, or a nested array to group entries into named sections.

```php
// Flat — all entries appear under "Details"
$handler->error('Invalid checkout state', [
    'order_id' => '4711',
    'status'   => 'pending',
]);

// Grouped — each key becomes its own section in Sentry
$handler->error('Payment failed', [
    'Order'   => ['id' => '4711', 'total' => '99.00'],
    'Payment' => ['provider' => 'stripe', 'code' => 'card_declined'],
]);

// Mixed — scalar values land in "Details", arrays become named groups
$handler->error('Checkout aborted', [
    'user_id' => '42',
    'Cart'    => ['items' => '3', 'total' => '29.99'],
]);
```

Context has a maximum depth of two levels. Nesting arrays inside a group throws a `\LogicException`.

## Tags

Tags are indexed in Sentry and can be used to filter and search events.

```php
$handler->warning('Cache miss rate elevated', context: [], tags: [
    'region'      => 'eu-west-1',
    'environment' => 'production',
]);

// Tags work on all severity methods
$handler->exception($exception, tags: ['team' => 'checkout']);
```
