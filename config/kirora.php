<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | API key
    |--------------------------------------------------------------------------
    |
    | A server key from Developer → Server keys in the Kirora dashboard. Keep it
    | in your environment, out of source control.
    |
    */

    'api_key' => env('KIRORA_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Client options
    |--------------------------------------------------------------------------
    |
    | Leave any of these null to use the SDK's default: the API at
    | https://api.kirora.io, a 30 second timeout per attempt, 3 retries, and at
    | most 30 seconds between attempts. "workspace" is for partner keys only:
    | the externalId of the tenant workspace every call acts in.
    |
    */

    'base_url' => env('KIRORA_BASE_URL'),

    'timeout' => env('KIRORA_TIMEOUT'),

    'max_retries' => env('KIRORA_MAX_RETRIES'),

    'max_retry_delay' => env('KIRORA_MAX_RETRY_DELAY'),

    'workspace' => env('KIRORA_WORKSPACE'),

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | The package registers POST /{path} to receive Kirora's webhooks. Each one
    | is checked against "secret", the signing secret of your webhook endpoint
    | in Kirora, and rejected if its timestamp is more than "tolerance" seconds
    | from now. Set "route" to false to register the route yourself.
    |
    | The route is outside the "web" middleware group, so CSRF protection does
    | not apply to it. "middleware" adds middleware to it, and "domain"
    | restricts it to one domain.
    |
    */

    'webhook' => [
        'secret' => env('KIRORA_WEBHOOK_SECRET'),
        'tolerance' => env('KIRORA_WEBHOOK_TOLERANCE', 300),
        'route' => env('KIRORA_WEBHOOK_ROUTE', true),
        'path' => env('KIRORA_WEBHOOK_PATH', 'kirora/webhook'),
        'domain' => env('KIRORA_WEBHOOK_DOMAIN'),
        'middleware' => [],
    ],

];
