<?php

declare(strict_types=1);

namespace Kirora\Laravel\Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use Kirora\Laravel\Events\MessageBounced;
use RuntimeException;

final class QueuedBounceListener implements ShouldQueue
{
    public int $tries = 1;

    /** @var list<MessageBounced> */
    public static array $handled = [];

    public static bool $fail = false;

    public function handle(MessageBounced $event): void
    {
        if (self::$fail) {
            throw new RuntimeException('listener failed in the worker');
        }
        self::$handled[] = $event;
    }
}
