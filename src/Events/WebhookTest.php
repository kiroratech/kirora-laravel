<?php

declare(strict_types=1);

namespace Kirora\Laravel\Events;

/**
 * `webhook.test`: A test delivery to check an endpoint, sent by `POST
 * /v1/webhooks/{id}/test` or from the dashboard.
 */
final class WebhookTest extends KiroraWebhook
{
}
