<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Kirora\Enums\WebhookEvent;
use Kirora\Laravel\Events\KiroraWebhook;
use Kirora\Laravel\Events\MessageDelivered;
use Kirora\Laravel\Events\WebhookReceived;
use Kirora\Laravel\Exceptions\WebhookSecretIsMissing;
use Kirora\Laravel\Http\Controllers\WebhookController;
use Kirora\Laravel\KiroraServiceProvider;

function fakeWebhookEvents(): void
{
    Event::fake([WebhookReceived::class, ...array_values(WebhookController::EVENTS)]);
}

it('registers the webhook route', function () {
    $route = Route::getRoutes()->getByName('kirora.webhook');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('kirora/webhook')
        ->and($route->methods())->toBe(['POST'])
        ->and($route->gatherMiddleware())->not->toContain('web');
});

it('dispatches WebhookReceived and the event for the type', function () {
    fakeWebhookEvents();
    [$body, $server] = signedWebhook('message.delivered');

    postWebhook($body, $server)->assertOk()->assertContent('');

    Event::assertDispatched(WebhookReceived::class, fn (WebhookReceived $event) => $event->eventType() === 'message.delivered');
    Event::assertDispatched(MessageDelivered::class, function (MessageDelivered $event) {
        return $event->eventId() === '01a0f2cf-4444-7555-8666-777788889999'
            && $event->type() === WebhookEvent::MessageDelivered
            && $event->attempt() === 1
            && $event->occurredAt() === '2026-10-01T09:30:00.000Z'
            && $event->data() === ['message' => ['id' => 'msg_123', 'status' => 'delivered']]
            && $event->payload['eventType'] === 'message.delivered';
    });
    Event::assertDispatchedTimes(WebhookReceived::class, 1);
    Event::assertDispatchedTimes(MessageDelivered::class, 1);
});

it('has an event for every webhook type the SDK knows', function (WebhookEvent $type) {
    fakeWebhookEvents();
    [$body, $server] = signedWebhook($type->value);
    $class = WebhookController::EVENTS[$type->value] ?? null;

    postWebhook($body, $server)->assertOk();

    expect($class)->not->toBeNull()
        ->and(class_basename($class))->toBe($type->name)
        ->and(is_subclass_of($class, KiroraWebhook::class))->toBeTrue();
    Event::assertDispatched($class, fn (KiroraWebhook $event) => $event->type() === $type);
})->with(WebhookEvent::cases());

it('dispatches only WebhookReceived for a type it does not know', function () {
    fakeWebhookEvents();
    [$body, $server] = signedWebhook('invoice.paid');

    postWebhook($body, $server)->assertOk();

    Event::assertDispatched(WebhookReceived::class, fn (WebhookReceived $event) => $event->eventType() === 'invoice.paid' && $event->type() === null);
    Event::assertDispatchedTimes(WebhookReceived::class, 1);
    expect(Event::dispatched(MessageDelivered::class))->toBeEmpty();
});

it('verifies the raw body, not re-encoded JSON', function () {
    fakeWebhookEvents();
    // Spacing and escapes that json_encode wouldn't reproduce.
    $body = "{ \"eventId\": \"01a0f2cf-4444-7555-8666-777788889999\",\n  \"eventType\": \"message.delivered\", \"data\": {\"name\": \"Zo\\u00eb\", \"url\": \"https:\\/\\/x.test\"} }";
    [$body, $server] = signedWebhook('message.delivered', $body);

    postWebhook($body, $server)->assertOk();

    Event::assertDispatched(MessageDelivered::class, fn (MessageDelivered $event) => $event->data() === ['name' => 'Zoë', 'url' => 'https://x.test']);
});

it('refuses a webhook without saying why', function (array $webhook) {
    fakeWebhookEvents();
    [$body, $server] = $webhook;

    postWebhook($body, $server)->assertForbidden()->assertContent('');

    Event::assertNothingDispatched();
})->with([
    'wrong secret' => fn () => signedWebhook(secret: 'whsec_other'),
    'no signature' => fn () => signedWebhook(overrides: ['x-webhook-signature' => null]),
    'stale timestamp' => fn () => signedWebhook(timestamp: time() - 301),
    'future timestamp' => fn () => signedWebhook(timestamp: time() + 301),
    'event header not the body\'s' => fn () => signedWebhook(overrides: ['x-webhook-event' => 'message.bounced']),
    'event id header not the body\'s' => fn () => signedWebhook(overrides: ['x-webhook-event-id' => 'other']),
    'body changed after signing' => function () {
        [$body, $server] = signedWebhook();

        return [str_replace('delivered', 'bounced', $body), $server];
    },
    'no headers' => fn () => ['{}', ['CONTENT_TYPE' => 'application/json']],
]);

it('accepts any one of several signatures', function () {
    fakeWebhookEvents();
    [$body, $server] = signedWebhook();
    $server['HTTP_X_WEBHOOK_SIGNATURE'] = 'sha256=' . str_repeat('0', 64) . ', ' . $server['HTTP_X_WEBHOOK_SIGNATURE'];

    postWebhook($body, $server)->assertOk();
});

it('reads the tolerance from config', function () {
    config(['kirora.webhook.tolerance' => '600']);
    fakeWebhookEvents();
    [$body, $server] = signedWebhook(timestamp: time() - 450);

    postWebhook($body, $server)->assertOk();
});

it('refuses every webhook when no secret is configured', function (?string $secret) {
    config(['kirora.webhook.secret' => $secret]);
    fakeWebhookEvents();
    [$body, $server] = signedWebhook();

    $this->withoutExceptionHandling();

    expect(fn () => postWebhook($body, $server))->toThrow(WebhookSecretIsMissing::class);
    Event::assertNothingDispatched();
})->with([null, '']);

it('answers 500 when no secret is configured, so Kirora retries', function () {
    config(['kirora.webhook.secret' => null]);
    [$body, $server] = signedWebhook();

    postWebhook($body, $server)->assertStatus(500);
});

it('answers 500 when a listener fails, so Kirora retries', function () {
    Event::listen(MessageDelivered::class, function () {
        throw new RuntimeException('listener failed');
    });
    [$body, $server] = signedWebhook();

    postWebhook($body, $server)->assertStatus(500);
});

it('verifies webhooks on a route of your own, without adding the middleware', function () {
    fakeWebhookEvents();
    Route::post('my/webhook', WebhookController::class);
    [$body, $server] = signedWebhook();
    [$forged, $forgedServer] = signedWebhook(secret: 'whsec_other');

    postWebhook($forged, $forgedServer, '/my/webhook')->assertForbidden();
    Event::assertNothingDispatched();

    postWebhook($body, $server, '/my/webhook')->assertOk();
    Event::assertDispatched(MessageDelivered::class);
});

it('takes the path, domain and middleware from config', function () {
    $this->withConfig([
        'kirora.webhook.path' => 'hooks/kirora',
        'kirora.webhook.domain' => 'hooks.example.test',
        'kirora.webhook.middleware' => ['throttle:60,1'],
    ]);
    fakeWebhookEvents();
    [$body, $server] = signedWebhook();

    $route = Route::getRoutes()->getByName('kirora.webhook');
    expect($route->uri())->toBe('hooks/kirora')
        ->and($route->getDomain())->toBe('hooks.example.test')
        ->and($route->gatherMiddleware())->toContain('throttle:60,1');

    postWebhook($body, $server, 'http://hooks.example.test/hooks/kirora')->assertOk();
    postWebhook($body, $server, '/kirora/webhook')->assertNotFound();
});

it('registers no route when disabled', function (mixed $value) {
    $this->withConfig(['kirora.webhook.route' => $value]);

    expect(Route::getRoutes()->getByName('kirora.webhook'))->toBeNull();
})->with([false, 'false', 'off', 'no', '0']);

it('fills in webhook keys a published config leaves out', function () {
    // As a published config/kirora.php is loaded, before the provider registers.
    config(['kirora.webhook' => ['secret' => 'whsec_mine', 'path' => 'hooks/kirora']]);

    (new KiroraServiceProvider(app()))->register();

    expect(config('kirora.webhook'))->toMatchArray([
        'secret' => 'whsec_mine',
        'path' => 'hooks/kirora',
        'route' => true,
        'tolerance' => 300,
        'middleware' => [],
    ]);
});

it('keeps the default path when the configured one is empty', function (string $path) {
    $this->withConfig(['kirora.webhook.path' => $path]);

    expect(Route::getRoutes()->getByName('kirora.webhook')->uri())->toBe('kirora/webhook');
})->with(['', '/']);
