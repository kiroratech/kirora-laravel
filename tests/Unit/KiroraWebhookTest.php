<?php

declare(strict_types=1);

use Kirora\Laravel\Events\MessageBounced;
use Kirora\WebhookHeaders;

it('survives serialization for queued listeners', function () {
    $event = new MessageBounced(
        ['eventId' => 'evt_1', 'eventType' => 'message.bounced', 'data' => ['message' => ['id' => 'msg_1']]],
        new WebhookHeaders('message.bounced', 'evt_1', 3, '1700000000', 'sha256=abc'),
    );

    $copy = unserialize(serialize($event));

    expect($copy)->toEqual($event)
        ->and($copy->attempt())->toBe(3)
        ->and($copy->data())->toBe(['message' => ['id' => 'msg_1']]);
});

it('copes with a payload missing its optional parts', function () {
    $event = new MessageBounced([], new WebhookHeaders('message.bounced', 'evt_1', 1, '1700000000', 'sha256=abc'));

    expect($event->data())->toBe([])
        ->and($event->occurredAt())->toBeNull();
});
