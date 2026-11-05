<?php

declare(strict_types=1);

namespace Kirora\Laravel\Events;

/**
 * `message.opened`: The first tracked open of an email, which may be automated.
 */
final class MessageOpened extends KiroraWebhook
{
}
