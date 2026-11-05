<?php

declare(strict_types=1);

namespace Kirora\Laravel\Events;

/**
 * `message.failed`: Sending broke and will not be retried.
 */
final class MessageFailed extends KiroraWebhook
{
}
