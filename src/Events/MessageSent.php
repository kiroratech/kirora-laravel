<?php

declare(strict_types=1);

namespace Kirora\Laravel\Events;

/**
 * `message.sent`: The message was handed to the email provider or the push service, for
 * every kind of message (`message.messageType` says which).
 */
final class MessageSent extends KiroraWebhook
{
}
