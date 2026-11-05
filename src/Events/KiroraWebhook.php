<?php

declare(strict_types=1);

namespace Kirora\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kirora\Enums\WebhookEvent;
use Kirora\WebhookHeaders;

/**
 * A verified webhook from Kirora. `WebhookReceived` is dispatched for every one, then the
 * event for its type, such as `MessageDelivered`. Listeners are matched by class, so listen
 * for `WebhookReceived` to get them all; listening for this class gets none.
 *
 * Webhooks can arrive more than once: deduplicate on `eventId()`, and record an event as
 * handled only once its work has succeeded.
 *
 * Failures reach Kirora only while the webhook request is running. If a synchronous
 * listener throws, or a queued listener can't be pushed onto its queue, the response is
 * a 500 and Kirora delivers the webhook again later. A queued listener that fails in a
 * worker fails after the 200 has gone. Kirora doesn't redeliver it, so give the listener
 * `$tries` or `backoff()`, and watch failed jobs.
 */
abstract class KiroraWebhook
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $payload  The decoded body: `eventId`, `eventType`,
     *                                         `occurredAt` and the event's `data`.
     * @param  WebhookHeaders  $headers  Kirora's headers, whose event and event id match the body.
     */
    final public function __construct(
        public readonly array $payload,
        public readonly WebhookHeaders $headers,
    ) {
    }

    /**
     * The same on every delivery attempt of an event, so deduplicate on it.
     */
    public function eventId(): string
    {
        return $this->headers->eventId;
    }

    /**
     * The event type, such as `message.delivered`.
     */
    public function eventType(): string
    {
        return $this->headers->event;
    }

    /**
     * The event type as an enum case, or null for a type added to the API after this SDK.
     */
    public function type(): ?WebhookEvent
    {
        return WebhookEvent::tryFrom($this->eventType());
    }

    /**
     * When Kirora queued the event, as an ISO 8601 string.
     */
    public function occurredAt(): ?string
    {
        $occurredAt = $this->payload['occurredAt'] ?? null;

        return is_string($occurredAt) ? $occurredAt : null;
    }

    /**
     * The event's own fields, such as the `contact` or the `message`.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $data = $this->payload['data'] ?? null;

        /** @var array<string, mixed> */
        return is_array($data) ? $data : [];
    }

    /**
     * Which delivery attempt this is, from 1.
     */
    public function attempt(): int
    {
        return $this->headers->attempt;
    }
}
