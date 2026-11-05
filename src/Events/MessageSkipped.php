<?php

declare(strict_types=1);

namespace Kirora\Laravel\Events;

/**
 * `message.skipped`: A guard stopped it before sending.
 */
final class MessageSkipped extends KiroraWebhook
{
}
