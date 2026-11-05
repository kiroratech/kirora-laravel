<?php

declare(strict_types=1);

namespace Kirora\Laravel\Http\Controllers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Kirora\Laravel\Events;
use Kirora\Laravel\Http\Middleware\VerifyWebhookSignature;
use Kirora\VerifiedWebhook;

/**
 * Receives Kirora's webhooks, once `VerifyWebhookSignature` has verified them. It applies
 * that middleware itself, so a route of your own to it verifies too. It
 * then dispatches `Events\WebhookReceived` and then the event for the webhook's type. Answers
 * 200 once the listeners return, so queue the slow ones: Kirora retries a delivery that
 * isn't acknowledged in time.
 */
class WebhookController implements HasMiddleware
{
    /**
     * The event dispatched for each webhook type, after `WebhookReceived`.
     *
     * @var array<string, class-string<Events\KiroraWebhook>>
     */
    public const EVENTS = [
        'contact.created' => Events\ContactCreated::class,
        'contact.unsubscribed' => Events\ContactUnsubscribed::class,
        'contact.consent_changed' => Events\ContactConsentChanged::class,
        'form.submitted' => Events\FormSubmitted::class,
        'campaign.sent' => Events\CampaignSent::class,
        'journey.enrolled' => Events\JourneyEnrolled::class,
        'journey.action_reached' => Events\JourneyActionReached::class,
        'message.delivered' => Events\MessageDelivered::class,
        'message.bounced' => Events\MessageBounced::class,
        'message.complained' => Events\MessageComplained::class,
        'message.opened' => Events\MessageOpened::class,
        'message.clicked' => Events\MessageClicked::class,
        'message.failed' => Events\MessageFailed::class,
        'message.skipped' => Events\MessageSkipped::class,
        'message.sent' => Events\MessageSent::class,
        'webhook.test' => Events\WebhookTest::class,
    ];

    /**
     * @return list<class-string>
     */
    public static function middleware(): array
    {
        return [VerifyWebhookSignature::class];
    }

    public function __construct(
        private readonly Dispatcher $events,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $webhook = $request->attributes->get(VerifyWebhookSignature::ATTRIBUTE);
        // The middleware above always sets it; refuse rather than trust a request without it.
        if (! $webhook instanceof VerifiedWebhook) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }

        $this->events->dispatch(new Events\WebhookReceived($webhook->payload, $webhook->headers));
        $event = static::EVENTS[$webhook->headers->event] ?? null;
        if ($event !== null) {
            $this->events->dispatch(new $event($webhook->payload, $webhook->headers));
        }

        return new Response('', Response::HTTP_OK);
    }
}
