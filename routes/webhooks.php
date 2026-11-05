<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kirora\Laravel\Http\Controllers\WebhookController;

$path = config('kirora.webhook.path');
$middleware = config('kirora.webhook.middleware');
$domain = config('kirora.webhook.domain');

$route = Route::post(is_string($path) && trim($path, '/') !== '' ? $path : 'kirora/webhook', WebhookController::class)
    ->middleware(is_array($middleware) ? $middleware : [])
    ->name('kirora.webhook');

if (is_string($domain) && $domain !== '') {
    $route->domain($domain);
}
