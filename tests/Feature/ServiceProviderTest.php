<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Kirora\Client;
use Kirora\Exceptions\ErrorException;
use Kirora\Laravel\KiroraServiceProvider;

it('binds one client, under its class and "kirora"', function () {
    expect(app(Client::class))->toBeInstanceOf(Client::class)
        ->and(app(Client::class))->toBe(app('kirora'));
});

it('configures the client from config', function () {
    config([
        'kirora.api_key' => 'key_from_config',
        'kirora.base_url' => 'https://api.example.test',
        'kirora.timeout' => '12.5',
        'kirora.workspace' => 'tenant-1',
    ]);
    $api = fakeApi([apiResponse(['key' => ['id' => 'key_1'], 'workspace' => ['name' => 'Acme']])]);

    expect(app(Client::class)->me()->workspace['name'])->toBe('Acme');

    $request = $api->request();
    expect((string) $request->getUri())->toBe('https://api.example.test/v1/me')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer key_from_config')
        ->and($request->getHeaderLine('Kirora-Workspace'))->toBe('tenant-1')
        ->and($request->getHeaderLine('User-Agent'))->toContain('kirora-laravel/' . KiroraServiceProvider::VERSION)
        ->and($api->options()['timeout'])->toBe(12.5);
});

it('reads max_retries as a whole number', function (string $value) {
    config(['kirora.max_retries' => $value]);
    $api = fakeApi([apiError(503, 'SERVICE_UNAVAILABLE')]);

    expect(fn () => app(Client::class)->me())->toThrow(ErrorException::class)
        ->and($api->history)->toHaveCount(1);
})->with(['0', '0.0']);

it('leaves unset options to the SDK', function () {
    config(['kirora.base_url' => '', 'kirora.timeout' => null, 'kirora.workspace' => null]);
    $api = fakeApi([apiResponse(['key' => [], 'workspace' => []])]);

    app(Client::class)->me();

    expect((string) $api->request()->getUri())->toBe('https://api.kirora.io/v1/me')
        ->and($api->request()->hasHeader('Kirora-Workspace'))->toBeFalse()
        ->and($api->options()['timeout'])->toBe(30.0);
});

it('passes invalid options on to the SDK to refuse', function () {
    config(['kirora.max_retries' => 'lots']);

    app(Client::class);
})->throws(InvalidArgumentException::class, 'maxRetries');

it('says when the API key is missing', function () {
    config(['kirora.api_key' => null]);
    putenv('KIRORA_API_KEY');
    unset($_ENV['KIRORA_API_KEY'], $_SERVER['KIRORA_API_KEY']);

    app(Client::class);
})->throws(InvalidArgumentException::class, 'KIRORA_API_KEY');

it('refuses an API key that is not a string', function () {
    config(['kirora.api_key' => ['nope']]);

    app(Client::class);
})->throws(InvalidArgumentException::class, 'kirora.api_key must be a string');

it('refuses an HTTP client that is not PSR-18', function () {
    app()->instance(KiroraServiceProvider::HTTP_CLIENT, new stdClass());

    app(Client::class);
})->throws(InvalidArgumentException::class, 'PSR-18');

it('publishes the config', function () {
    $paths = ServiceProvider::pathsToPublish(KiroraServiceProvider::class, 'kirora-config');

    expect($paths)->toHaveCount(1)
        ->and(array_values($paths)[0])->toBe(config_path('kirora.php'))
        ->and(file_exists(array_keys($paths)[0]))->toBeTrue();
});

it('has a changelog section for its version', function () {
    $changelog = file_get_contents(__DIR__ . '/../../CHANGELOG.md');

    expect($changelog)->toContain('## ' . KiroraServiceProvider::VERSION);
});
