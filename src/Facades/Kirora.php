<?php

declare(strict_types=1);

namespace Kirora\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Kirora\Client;

/**
 * The `Kirora\Client` the service provider binds. The client's services are properties,
 * which a facade can't forward, so the facade offers each as a method instead:
 * `Kirora::contacts()` is `$client->contacts`.
 *
 * The SDK declares a global `Kirora` class, so import this facade with
 * `use Kirora\Laravel\Facades\Kirora;` rather than aliasing it as `Kirora`.
 *
 * @method static \Kirora\Service\Contacts contacts()
 * @method static \Kirora\Service\Lists lists()
 * @method static \Kirora\Service\Campaigns campaigns()
 * @method static \Kirora\Service\Transactional transactional()
 * @method static \Kirora\Service\Events events()
 * @method static \Kirora\Service\Segments segments()
 * @method static \Kirora\Service\Messages messages()
 * @method static \Kirora\Service\Suppressions suppressions()
 * @method static \Kirora\Service\Workspaces workspaces()
 * @method static \Kirora\Service\EmbedSessions embedSessions()
 * @method static \Kirora\Models\ApiCaller me(array<string, mixed> $options = [])
 * @method static \Kirora\Client forWorkspace(string $externalId)
 * @method static mixed request(string $method, string $path, mixed $body = null, array<string, mixed> $options = [])
 * @method static array<array-key, mixed> requestData(string $method, string $path, mixed $body = null, array<string, mixed> $options = [])
 * @method static \Kirora\Page<array<string, mixed>> requestPage(string $path, array<string, mixed> $query = [], array<string, mixed> $options = [])
 * @method static \Generator<int, array<string, mixed>> requestAll(string $path, array<string, mixed> $query = [], array<string, mixed> $options = [])
 * @method static \Psr\Http\Message\ResponseInterface requestRaw(string $method, string $path, mixed $body = null, array<string, mixed> $options = [])
 *
 * @see Client
 */
final class Kirora extends Facade
{
    /**
     * @param  string  $method
     * @param  array<mixed>  $args
     */
    public static function __callStatic($method, $args): mixed
    {
        $client = static::getFacadeRoot();
        if ($args === [] && $client instanceof Client && ! method_exists($client, $method) && isset($client->{$method})) {
            return $client->{$method};
        }

        return parent::__callStatic($method, $args);
    }

    protected static function getFacadeAccessor(): string
    {
        return 'kirora';
    }
}
