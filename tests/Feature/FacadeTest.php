<?php

declare(strict_types=1);

use Kirora\Client;
use Kirora\Laravel\Facades\Kirora;
use Kirora\Service\Contacts;
use Kirora\Service\Transactional;

it('resolves the bound client', function () {
    expect(Kirora::getFacadeRoot())->toBe(app(Client::class));
});

it('offers each service as a method', function () {
    expect(Kirora::contacts())->toBeInstanceOf(Contacts::class)
        ->and(Kirora::contacts())->toBe(app(Client::class)->contacts)
        ->and(Kirora::transactional())->toBeInstanceOf(Transactional::class)
        ->and(Kirora::contacts()->lists)->toBe(app(Client::class)->contacts->lists);
});

it('forwards the client methods', function () {
    $api = fakeApi([
        apiResponse(['key' => [], 'workspace' => ['name' => 'Acme']]),
        apiResponse(['id' => 'ct_1', 'email' => 'ada@example.com']),
    ]);

    expect(Kirora::me()->workspace['name'])->toBe('Acme');

    $contact = Kirora::forWorkspace('tenant-2')->contacts->get('ct_1');

    expect($contact->email)->toBe('ada@example.com')
        ->and($api->request(1)->getHeaderLine('Kirora-Workspace'))->toBe('tenant-2');
});

it('sends service calls through the bound client', function () {
    $api = fakeApi([apiResponse(['messages' => [], 'skippedChannels' => []], 202)]);

    Kirora::transactional()->send([
        'to' => ['email' => 'ada@example.com'],
        'email' => ['template' => 'order-receipt'],
    ], ['idempotencyKey' => 'receipt-1']);

    expect($api->request()->getMethod())->toBe('POST')
        ->and($api->request()->getUri()->getPath())->toBe('/v1/transactional/messages')
        ->and($api->request()->getHeaderLine('Idempotency-Key'))->toBe('receipt-1');
});

it('throws for a name that is neither a method nor a service', function () {
    Kirora::nothing();
})->throws(Error::class, 'undefined method');
