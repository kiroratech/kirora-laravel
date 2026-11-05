<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Kirora\Client;
use Kirora\Laravel\Events\MessageBounced;
use Kirora\Laravel\Facades\Kirora;
use Kirora\Laravel\Tests\Fixtures\QueuedBounceListener;

/**
 * A database queue, so queued listeners run in a worker after the webhook request, as in
 * production, rather than during it as with the sync connection.
 */
function useDatabaseQueue(): void
{
    // Testbench's database connection is already "testing"; failed jobs default to sqlite.
    config([
        'queue.default' => 'database',
        'queue.failed.database' => 'testing',
    ]);
    test()->loadLaravelMigrations();
    QueuedBounceListener::$handled = [];
    QueuedBounceListener::$fail = false;
    Event::listen(MessageBounced::class, QueuedBounceListener::class);
}

function runWorker(): void
{
    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);
}

it('acknowledges a webhook once a queued listener is queued, then runs it in a worker', function () {
    useDatabaseQueue();
    [$body, $server] = signedWebhook('message.bounced');

    postWebhook($body, $server)->assertOk();

    expect(QueuedBounceListener::$handled)->toBeEmpty()
        ->and(DB::table('jobs')->count())->toBe(1);

    runWorker();

    expect(QueuedBounceListener::$handled)->toHaveCount(1)
        ->and(QueuedBounceListener::$handled[0]->eventId())->toBe('01a0f2cf-4444-7555-8666-777788889999')
        ->and(QueuedBounceListener::$handled[0]->data()['message']['id'])->toBe('msg_123')
        ->and(DB::table('jobs')->count())->toBe(0);
});

it('leaves a queued listener failing in the worker to Laravel\'s failed jobs, after the 200', function () {
    useDatabaseQueue();
    QueuedBounceListener::$fail = true;
    [$body, $server] = signedWebhook('message.bounced');

    postWebhook($body, $server)->assertOk();
    runWorker();

    expect(DB::table('failed_jobs')->count())->toBe(1)
        ->and(DB::table('failed_jobs')->value('exception'))->toContain('listener failed in the worker')
        ->and(DB::table('jobs')->count())->toBe(0);
});

it('keeps the shared client unscoped across workspace calls', function () {
    $api = fakeApi([
        apiResponse(['id' => 'ct_a']),
        apiResponse(['id' => 'ct_b']),
        apiResponse(['id' => 'ct_c']),
        apiResponse(['id' => 'ct_d']),
    ]);

    Kirora::forWorkspace('tenant-a')->contacts->get('ct_a');
    Kirora::forWorkspace('tenant-b')->contacts->get('ct_b');
    Kirora::contacts()->get('ct_c');
    app(Client::class)->forWorkspace('tenant-a')->contacts->get('ct_d');

    expect($api->request(0)->getHeaderLine('Kirora-Workspace'))->toBe('tenant-a')
        ->and($api->request(1)->getHeaderLine('Kirora-Workspace'))->toBe('tenant-b')
        ->and($api->request(2)->hasHeader('Kirora-Workspace'))->toBeFalse()
        ->and($api->request(3)->getHeaderLine('Kirora-Workspace'))->toBe('tenant-a')
        ->and(app(Client::class))->toBe(app('kirora'));
});
