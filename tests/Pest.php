<?php

declare(strict_types=1);

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Kirora\Laravel\KiroraServiceProvider;
use Kirora\Laravel\Tests\TestCase;
use Psr\Http\Message\RequestInterface;

// Unit tests need no application.
uses(TestCase::class)->in('Feature');

/**
 * A fake Kirora API, bound as the SDK's HTTP client so no request leaves the test.
 */
final class FakeApi
{
    /** @var array<int, array{request: RequestInterface, options: array<string, mixed>}> */
    public array $history = [];

    public function request(int $index = 0): RequestInterface
    {
        return $this->history[$index]['request'];
    }

    /**
     * @return array<string, mixed>
     */
    public function options(int $index = 0): array
    {
        return $this->history[$index]['options'];
    }
}

/**
 * Binds a fake API answering with `$responses`, in order. Call it before the client is
 * first resolved.
 *
 * @param  list<mixed>  $responses
 */
function fakeApi(array $responses): FakeApi
{
    $fake = new FakeApi();
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($fake->history));
    app()->instance(KiroraServiceProvider::HTTP_CLIENT, new GuzzleClient(['handler' => $stack]));

    return $fake;
}

/**
 * @param  array<string, mixed>  $data
 */
function apiResponse(array $data, int $status = 200): Response
{
    return new Response($status, ['Content-Type' => 'application/json', 'X-Request-Id' => 'req_test'], json_encode(['data' => $data], JSON_THROW_ON_ERROR));
}

function apiError(int $status, string $code): Response
{
    return new Response($status, ['Content-Type' => 'application/json'], json_encode(['error' => ['code' => $code, 'message' => $code]], JSON_THROW_ON_ERROR));
}

/**
 * Posts a raw webhook body, as Kirora does, without re-encoding it.
 *
 * @param  array<string, string>  $server
 */
function postWebhook(string $body, array $server, string $uri = '/kirora/webhook'): Illuminate\Testing\TestResponse
{
    return test()->call('POST', $uri, [], [], [], $server, $body);
}

/**
 * A webhook as Kirora sends it: the raw body, and its signed headers as `$_SERVER` keys.
 *
 * @param  array<string, mixed>  $overrides  Header overrides, by lowercase name.
 * @return array{0: string, 1: array<string, string>}
 */
function signedWebhook(string $eventType = 'message.delivered', ?string $body = null, array $overrides = [], string $secret = TestCase::SECRET, ?int $timestamp = null): array
{
    $eventId = '01a0f2cf-4444-7555-8666-777788889999';
    $body ??= json_encode([
        'eventId' => $eventId,
        'eventType' => $eventType,
        'occurredAt' => '2026-10-01T09:30:00.000Z',
        'data' => ['message' => ['id' => 'msg_123', 'status' => 'delivered']],
    ], JSON_THROW_ON_ERROR);
    $timestamp = (string) ($timestamp ?? time());
    $headers = [
        'x-webhook-event' => $eventType,
        'x-webhook-event-id' => $eventId,
        'x-webhook-timestamp' => $timestamp,
        'x-webhook-attempt' => '1',
        'x-webhook-signature' => 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret),
        ...$overrides,
    ];
    $server = ['CONTENT_TYPE' => 'application/json'];
    foreach ($headers as $name => $value) {
        if ($value !== null) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = (string) $value;
        }
    }

    return [$body, $server];
}
