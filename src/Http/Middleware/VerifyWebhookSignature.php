<?php

declare(strict_types=1);

namespace Kirora\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Kirora\Exceptions\WebhookVerificationException;
use Kirora\Laravel\Exceptions\WebhookSecretIsMissing;
use Kirora\Laravel\Support\ConfigValue;
use Kirora\VerifiedWebhook;
use Kirora\Webhook;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Refuses a webhook that Kirora didn't sign with `kirora.webhook.secret`, with an empty
 * 403 that doesn't say why; the reason is logged at debug level. A verified webhook is put
 * on the request, as the `VerifiedWebhook` attribute, for the controller, which applies
 * this middleware itself.
 *
 * With no secret configured, every webhook is refused: it throws `WebhookSecretIsMissing`,
 * which your exception handler reports and answers with a 500.
 */
final class VerifyWebhookSignature
{
    public const ATTRIBUTE = VerifiedWebhook::class;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param  Closure(Request): SymfonyResponse  $next
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $secret = ConfigValue::string(config('kirora.webhook.secret'));
        if (! is_string($secret)) {
            throw WebhookSecretIsMissing::create();
        }

        try {
            // The raw body: decoded and re-encoded JSON wouldn't match the signature.
            $webhook = Webhook::assert($secret, $request->getContent(), $request->headers->all(), [
                'tolerance' => ConfigValue::number(config('kirora.webhook.tolerance')) ?? Webhook::DEFAULT_TOLERANCE,
            ]);
        } catch (WebhookVerificationException $error) {
            $this->logger->debug('Kirora: refused a webhook', ['reason' => $error->reason->value]);

            return new Response('', Response::HTTP_FORBIDDEN);
        }

        $request->attributes->set(self::ATTRIBUTE, $webhook);

        return $next($request);
    }
}
