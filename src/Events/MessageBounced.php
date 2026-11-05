<?php

declare(strict_types=1);

namespace Kirora\Laravel\Events;

/**
 * `message.bounced`: Delivery failed permanently (a hard bounce).
 */
final class MessageBounced extends KiroraWebhook
{
}
