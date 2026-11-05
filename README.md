# Kirora for Laravel

Manage contacts, track events, send campaigns and transactional messages, and receive
verified Kirora webhooks as Laravel events.

- A configured `Kirora\Client` in Laravel's container, with dependency injection and a facade.
- Automatic webhook routing, signature verification, and typed Laravel event classes.
- The [Kirora PHP SDK's](https://github.com/kiroratech/kirora-php) services, response models,
  pagination, retries, and idempotency keys.

[Laravel guide](https://kirora.io/docs/sdks/laravel) ·
[API reference](https://kirora.io/docs/api/reference) ·
[PHP SDK usage](https://github.com/kiroratech/kirora-php/blob/main/docs/usage.md) ·
[Changelog](CHANGELOG.md)

## Contents

- [Installation](#installation)
- [Quickstart](#quickstart)
- [Configuration](#configuration)
- [Usage](#usage)
- [Webhooks](#webhooks)
- [Sending email](#sending-email)
- [Testing your code](#testing-your-code)
- [Troubleshooting](#troubleshooting)
- [Compatibility](#compatibility) and [development](#development)

## Installation

| Laravel | PHP requirement |
| ------- | --------------- |
| 12      | 8.2 or later    |
| 13      | 8.3 or later    |

```sh
composer require kirora/kirora-laravel
```

Laravel discovers the service provider automatically. Create an API key under
**Developer → Server keys** in the Kirora dashboard with the scopes your integration
needs, and add it to your application's `.env`:

```ini
KIRORA_API_KEY=your_api_key
```

Keep the key on your server and out of source control. Browser and mobile SDKs use
[server-issued user tokens](https://github.com/kiroratech/kirora-php/blob/main/docs/usage.md#authenticate-browser-and-mobile-sdk-users).

## Quickstart

Open your application's Tinker shell with `php artisan tinker`, then create or update
a contact. Replace the external ID and email with your own test values:

```php
use Kirora\Laravel\Facades\Kirora;

$contact = Kirora::contacts()->identify([
    'externalId' => 'laravel-quickstart-user',
    'email' => 'ada@example.com',
]);

echo "Contact saved: {$contact->id}\n";
```

A successful call prints `Contact saved: <contact ID>`. To check credentials without
creating data, call `Kirora::me()` instead. Import the facade in application code as
shown above; it is separate from the PHP SDK's global `\Kirora` factory.

## Configuration

The default configuration reads your environment variables, so publishing it is
optional. To customize settings such as webhook middleware, publish `config/kirora.php`:

```sh
php artisan vendor:publish --tag=kirora-config
```

Laravel merges your file with the package's defaults one top-level key at a time. A
`webhook` array in your file replaces the whole default array, so publish the full file
and edit it, rather than writing a partial one. If a `webhook` key is left out, the
package's default for it applies: the route stays on, at `kirora/webhook`, with a
300-second tolerance.

| Variable                   | Default          | Purpose                                                        |
| -------------------------- | ---------------- | -------------------------------------------------------------- |
| `KIRORA_API_KEY`           | none             | Your server key. Required.                                     |
| `KIRORA_BASE_URL`          | Kirora's API     | The API host, without `/v1`.                                   |
| `KIRORA_TIMEOUT`           | 30               | Seconds per attempt.                                           |
| `KIRORA_MAX_RETRIES`       | 3                | Retries after the first attempt, from 0 to 10.                 |
| `KIRORA_MAX_RETRY_DELAY`   | 30               | The longest wait between attempts, in seconds.                 |
| `KIRORA_WORKSPACE`         | none             | Partner keys only: the tenant workspace every call acts in.    |
| `KIRORA_WEBHOOK_SECRET`    | none             | Your webhook endpoint's signing secret. Required for webhooks. |
| `KIRORA_WEBHOOK_TOLERANCE` | 300              | How old or new a webhook's timestamp may be, in seconds.       |
| `KIRORA_WEBHOOK_PATH`      | `kirora/webhook` | The webhook route's path.                                      |
| `KIRORA_WEBHOOK_DOMAIN`    | any              | Limits the webhook route to one domain.                        |
| `KIRORA_WEBHOOK_ROUTE`     | `true`           | Set to `false` to register the webhook route yourself.         |

Client settings are validated when `Kirora\Client` is first resolved. A missing API
key or invalid client option throws `InvalidArgumentException`. Webhook secrets and
tolerance are checked when a delivery is received.

After changing `.env` locally, clear any cached configuration with
`php artisan config:clear`. In production, rebuild it with `php artisan config:cache`
and restart long-running queue workers or Octane processes so they pick up the new
settings. See [Laravel's configuration caching guidance](https://laravel.com/docs/12.x/configuration#configuration-caching).

## Usage

Inject `Kirora\Client`, or use the facade. Both give you the same singleton.
Track events for a contact that already exists. In an authenticated checkout flow:

```php
use Kirora\Client;

class CheckoutController
{
    public function __invoke(Client $kirora)
    {
        $kirora->events->track([
            'externalId' => (string) auth()->id(),
            'name' => 'Order Completed',
            'properties' => ['orderId' => '1234', 'total' => 49.99],
        ], ['idempotencyKey' => 'order-1234-completed']);
    }
}
```

On the facade, each service is a method instead of a property, so `$kirora->contacts` is
`Kirora::contacts()`:

```php
use Kirora\Laravel\Facades\Kirora;

$contact = Kirora::contacts()->identify([
    'externalId' => 'user-42',
    'email' => 'ada@example.com',
]);

foreach (Kirora::contacts()->lists->all($contact->id) as $membership) {
    // ...
}
```

The client's methods, such as `me()`, `forWorkspace()` and `request()`, work as they
are. A scoped client uses properties to reach its services:

```php
$tenant = Kirora::forWorkspace('tenant-external-id');
$contact = $tenant->contacts->get('user-42', ['idType' => 'externalId']);
```

`forWorkspace()` returns a new client and leaves the shared client unchanged. Use it
for each tenant rather than changing global configuration between requests or jobs.

> **Import the facade; don't alias it as `Kirora`.** The SDK declares a global `Kirora`
> class, the one with `Kirora::client()`, and Composer loads it on every request. An
> alias called `Kirora` in `config/app.php` never takes effect, and `\Kirora::contacts()`
> would call the SDK class instead. In namespaced code, write
> `use Kirora\Laravel\Facades\Kirora;`. If you want a global alias, for example in Blade,
> give it a different name:
>
> ```php
> 'aliases' => Facade::defaultAliases()->merge([
>     'KiroraApi' => Kirora\Laravel\Facades\Kirora::class,
> ])->toArray(),
> ```

Reuse the container's client across your operations. For the underlying services,
models, pagination and error handling, see the
[PHP SDK usage guide](https://github.com/kiroratech/kirora-php/blob/main/docs/usage.md).
API failures throw `Kirora\Exceptions\ErrorException`; connection failures throw
`TransporterException` or its `TimeoutException` subclass. Include the exception's
request ID, when available, when reporting an API failure.

For browser and mobile SDK users, sign
[user tokens](https://github.com/kiroratech/kirora-php/blob/main/docs/usage.md#authenticate-browser-and-mobile-sdk-users)
with `Kirora\UserToken::create()` from the SDK.

## Webhooks

The package registers `POST /kirora/webhook`, named `kirora.webhook`. To use it:

1. In Kirora, add a webhook endpoint at `https://your-app.example/kirora/webhook`.
2. Set `KIRORA_WEBHOOK_SECRET` to that endpoint's signing secret.
3. Confirm the route with `php artisan route:list --name=kirora.webhook`.
4. Listen for the events you need.

The default route is outside the `web` middleware group and does not use session or
CSRF middleware. Its signature middleware checks each webhook against the raw request
body with `Kirora\Webhook::assert()`. It refuses a missing or wrong signature, a timestamp
outside the tolerance, and event headers that don't match the signed body. A refused
webhook gets an empty `403` that doesn't say why; the reason is logged at `debug` level.
With no secret configured, every webhook is refused: the middleware throws
`WebhookSecretIsMissing`, and the response is a `500`.

A verified webhook dispatches two events: `Kirora\Laravel\Events\WebhookReceived` for
every webhook, then the event for its type:

| Webhook                   | Event                   |
| ------------------------- | ----------------------- |
| `contact.created`         | `ContactCreated`        |
| `contact.unsubscribed`    | `ContactUnsubscribed`   |
| `contact.consent_changed` | `ContactConsentChanged` |
| `form.submitted`          | `FormSubmitted`         |
| `campaign.sent`           | `CampaignSent`          |
| `journey.enrolled`        | `JourneyEnrolled`       |
| `journey.action_reached`  | `JourneyActionReached`  |
| `message.sent`            | `MessageSent`           |
| `message.delivered`       | `MessageDelivered`      |
| `message.opened`          | `MessageOpened`         |
| `message.clicked`         | `MessageClicked`        |
| `message.bounced`         | `MessageBounced`        |
| `message.complained`      | `MessageComplained`     |
| `message.failed`          | `MessageFailed`         |
| `message.skipped`         | `MessageSkipped`        |
| `webhook.test`            | `WebhookTest`           |

A webhook type added to the API after your version of the package dispatches only
`WebhookReceived`. Every event has the decoded body in `$payload` and Kirora's headers in
`$headers`. It also has helpers: `eventId()`, `eventType()`, `type()` (a
`Kirora\Enums\WebhookEvent`, or null for a type the SDK doesn't know), `occurredAt()`,
`data()` and `attempt()`.

```php
namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Kirora\Laravel\Events\MessageBounced;

class LogMessageBounce implements ShouldQueue
{
    public function handle(MessageBounced $event): void
    {
        logger()->warning('Kirora message bounced', [
            'event_id' => $event->eventId(),
            'message_id' => $event->data()['message']['id'] ?? null,
        ]);
    }
}
```

Laravel discovers listeners in `app/Listeners` by their `handle()` type. Otherwise,
register them with `Event::listen()`. Listeners match by exact class, so to get every
webhook, listen for `WebhookReceived`, not the shared `KiroraWebhook` base class.

The controller answers `200` after synchronous listeners finish and queued listeners
have been submitted. Use a queue connection that processes jobs outside the webhook
request and run a worker for listeners implementing `ShouldQueue`; a `sync` connection
still executes their work during the request.

- A synchronous listener or queue-submission failure can make the request fail. Kirora
  can then redeliver the webhook, so processing must tolerate duplicate event IDs.
- A queued listener that fails **after** a `200` has already been returned must be retried
  through Laravel's queue. Kirora does not see that later failure. Configure job retries
  and monitor failed jobs; see [Laravel's queued event listeners](https://laravel.com/docs/12.x/events#queued-event-listeners).

The package dispatches events for each verified delivery; your application owns payload
storage and deduplication. Listening to both `WebhookReceived` and a typed event invokes
both listeners for the same delivery.

To change the route, set `KIRORA_WEBHOOK_PATH` or `KIRORA_WEBHOOK_DOMAIN`, or add
middleware in `kirora.webhook.middleware`, such as `throttle:120,1`. To register it
yourself, set `KIRORA_WEBHOOK_ROUTE=false`. The controller applies the signature
middleware itself, so your route needs nothing else:

```php
use Illuminate\Support\Facades\Route;
use Kirora\Laravel\Http\Controllers\WebhookController;

Route::post('hooks/kirora', WebhookController::class);
```

Register custom routes outside `web`, or exclude their URI from your
application's CSRF protection. Rebuild the route cache after changing route settings
if your deployment uses `php artisan route:cache`.

## Sending email

Send transactional email through `Kirora::transactional()->send()`. Configure your
workspace's email sending setup and template first, then replace `order-receipt` with
your template's alias or ID. This example uses your application's `$user` and `$order`:

```php
use Kirora\Laravel\Facades\Kirora;

$sent = Kirora::transactional()->send([
    'to' => ['email' => $user->email],
    'data' => ['orderId' => $order->id],
    'email' => ['template' => 'order-receipt'],
], ['idempotencyKey' => "receipt-{$order->id}"]);

foreach ($sent->skippedChannels as $skipped) {
    logger()->info('Kirora channel skipped', [
        'channel' => $skipped->channel,
        'code' => $skipped->code,
    ]);
}
```

Reuse the same idempotency key when your application retries the same operation with
the same body. The SDK also retries eligible failures automatically. A successful send
can leave a requested channel unsent, so inspect `skippedChannels` as shown above.

For inline email, pass `subject`, `from`, and `html` or `text` in `email`. You can render
a Blade view for `html`; any Kirora merge tags left in the rendered content must have
their required values in `data`. Each send addresses one recipient. The package exposes
Kirora's API directly and does not register a `Mail::to()` / `Mailable` transport. See
[transactional messages](https://github.com/kiroratech/kirora-php#transactional-messages)
for payload examples and channel behavior.

## Testing your code

The SDK doesn't use Laravel's `Http` client, so `Http::fake()` doesn't reach it. The
`Http` client isn't PSR-18, and the SDK sends requests with Guzzle. Instead, bind a PSR-18
client under `KiroraServiceProvider::HTTP_CLIENT` before the client is first resolved.
For example, in a Pest feature test, configure a test key and bind a Guzzle mock before
calling the facade:

```php
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Kirora\Laravel\Facades\Kirora;
use Kirora\Laravel\KiroraServiceProvider;

it('retrieves a contact', function () {
    config(['kirora.api_key' => 'test-key']);

    $this->app->instance(KiroraServiceProvider::HTTP_CLIENT, new Guzzle([
        'handler' => HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => ['id' => 'ct_1', 'email' => 'ada@example.com'],
            ], JSON_THROW_ON_ERROR)),
        ])),
    ]));

    $contact = Kirora::contacts()->get('ct_1');

    expect($contact->email)->toBe('ada@example.com');
});
```

You can also replace the client itself with
`$this->app->instance(\Kirora\Client::class, $client)` before the facade is first used.
The HTTP-client binding also supports production clients with a proxy or other
transport configuration. With a non-Guzzle PSR-18 client, configure timeouts directly
on that client; the SDK's `timeout` option applies to Guzzle.

### Webhook tests

Test routing and verification together by posting a signed body. Set the signing secret
in the test and assert that the typed event is dispatched:

```php
use Illuminate\Support\Facades\Event;
use Kirora\Laravel\Events\MessageBounced;
use Kirora\Laravel\Events\WebhookReceived;

it('dispatches a verified webhook', function () {
    $secret = 'webhook-test-secret';
    config(['kirora.webhook.secret' => $secret]);
    Event::fake([WebhookReceived::class, MessageBounced::class]);

    $body = json_encode([
        'eventId' => 'evt_1',
        'eventType' => 'message.bounced',
        'occurredAt' => now()->toIso8601String(),
        'data' => ['message' => ['id' => 'msg_1', 'status' => 'bounced']],
    ], JSON_THROW_ON_ERROR);
    $timestamp = (string) time();

    $this->call('POST', '/kirora/webhook', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_WEBHOOK_EVENT' => 'message.bounced',
        'HTTP_X_WEBHOOK_EVENT_ID' => 'evt_1',
        'HTTP_X_WEBHOOK_TIMESTAMP' => $timestamp,
        'HTTP_X_WEBHOOK_SIGNATURE' => 'sha256=' . hash_hmac('sha256', "$timestamp.$body", $secret),
    ], content: $body)->assertOk();

    Event::assertDispatched(MessageBounced::class, function (MessageBounced $event) {
        return $event->eventId() === 'evt_1';
    });
});
```

This checks signature verification and event dispatch. Test your listener's application
behavior separately, including duplicate-event handling and queue failures.

## Troubleshooting

| Problem                                       | Check                                                                                                                                                                     |
| --------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Missing API key or stale settings             | Check `config('kirora.api_key')`, clear cached config locally, and rebuild it during deployment. Restart long-running workers after changes. Keep key values out of logs. |
| `Call to undefined method Kirora::contacts()` | Import `Kirora\Laravel\Facades\Kirora`; the SDK's global `\Kirora` class is a different entry point.                                                                      |
| Webhooks return `403`                         | Check the endpoint's secret, unchanged raw body, timestamp and event headers. Look for `Kirora: refused a webhook` in debug logs.                                         |
| Webhooks fail with `WebhookSecretIsMissing`   | Set `KIRORA_WEBHOOK_SECRET` to the endpoint's signing secret and refresh cached configuration.                                                                            |
| Webhook route is missing or returns `404`     | Check `KIRORA_WEBHOOK_ROUTE`, path/domain settings and `php artisan route:list --name=kirora.webhook`; rebuild any route cache.                                           |
| A custom webhook route returns `419`          | Check whether the route inherited CSRF middleware; use a dedicated route outside `web` or exclude its URI.                                                                |
| A queued listener does not run                | Check the queue connection, running workers, failed jobs and listener registration. `php artisan event:list` shows registered listeners.                                  |
| `Http::fake()` does not intercept API calls   | Bind a mock PSR-18 client before the Kirora singleton is resolved, as shown above.                                                                                        |

## Compatibility

- **Laravel 12:** PHP 8.2 or later.
- **Laravel 13:** PHP 8.3 or later; see [Laravel’s server requirements](https://laravel.com/docs/13.x/deployment#server-requirements).
- **SDK:** `kirora/kirora-php` 0.1.
- **Releases:** before 1.0, minor releases may include breaking changes. See the
  [changelog](CHANGELOG.md).

For bugs or feature requests, [open an issue](https://github.com/kiroratech/kirora-laravel/issues).
For integration help, email [support@kirora.io](mailto:support@kirora.io).

## Development

For repository development, `composer.json` currently resolves the SDK through a
Composer path repository at `../kirora-php`. Check both repositories out side by side.

`composer.lock` isn't committed, so `composer install` resolves dependencies for your PHP
version. On PHP 8.3 or later you get Laravel 13, Testbench 11 and Pest 4. On PHP 8.2 you
get Laravel 12, Testbench 10 and Pest 3. To test the other Laravel version, run
`composer update --with="laravel/framework:^12"` (or `^13`), as CI does.

From `kirora-laravel`, run:

```sh
composer install
composer test           # Pest, with orchestra/testbench
composer analyse        # PHPStan
composer format:check   # php-cs-fixer; `composer format` fixes
```

API behaviour, such as retries, idempotency, models and errors, belongs in the SDK.

## License

[MIT](LICENSE)
