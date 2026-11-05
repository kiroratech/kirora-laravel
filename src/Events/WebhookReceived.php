<?php

declare(strict_types=1);

namespace Kirora\Laravel\Events;

/**
 * Dispatched for every verified webhook, whatever its type, before the event for the type. Types added to the API after this version of the package get only this event.
 */
final class WebhookReceived extends KiroraWebhook
{
}
