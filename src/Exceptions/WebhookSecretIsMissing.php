<?php

declare(strict_types=1);

namespace Kirora\Laravel\Exceptions;

use RuntimeException;

/**
 * A webhook arrived, but there's no secret to verify it with, so it was refused.
 */
final class WebhookSecretIsMissing extends RuntimeException
{
    public static function create(): self
    {
        return new self('Kirora: set KIRORA_WEBHOOK_SECRET (kirora.webhook.secret) to your webhook endpoint\'s signing secret to receive webhooks');
    }
}
