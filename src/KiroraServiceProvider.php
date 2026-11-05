<?php

declare(strict_types=1);

namespace Kirora\Laravel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Support\ServiceProvider;
use Kirora\Client;
use Kirora\Laravel\Support\ConfigValue;

/**
 * Binds a `Kirora\Client`, configured from `config/kirora.php`, and registers the webhook
 * route.
 */
final class KiroraServiceProvider extends ServiceProvider
{
    /**
     * The version of this package, sent in the `User-Agent` header beside the SDK's.
     */
    public const VERSION = '0.1.0';

    /**
     * Bind a PSR-18 client under this name to send the SDK's requests with it, such as a
     * Guzzle client with a proxy, or a fake in tests. Unbound, the SDK uses a new Guzzle
     * client. Laravel's `Http` client isn't PSR-18, so `Http::fake()` doesn't reach the SDK.
     */
    public const HTTP_CLIENT = 'kirora.http_client';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/kirora.php', 'kirora');
        $this->mergeWebhookDefaults();

        $this->app->singleton(Client::class, static function (Application $app): Client {
            /** @var Repository $config */
            $config = $app->make('config');

            // Unset or empty, the SDK reads KIRORA_API_KEY from the process environment, and
            // says how to set it if that's missing too.
            $apiKey = $config->get('kirora.api_key');
            if ($apiKey !== null && ! is_string($apiKey)) {
                throw new \InvalidArgumentException('Kirora: kirora.api_key must be a string');
            }

            return \Kirora::client($apiKey, self::options($app, $config));
        });
        $this->app->alias(Client::class, 'kirora');
    }

    public function boot(): void
    {
        if (ConfigValue::boolean($this->app->make('config')->get('kirora.webhook.route'))) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/webhooks.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/kirora.php' => $this->app->configPath('kirora.php'),
            ], 'kirora-config');
        }
    }

    /**
     * `mergeConfigFrom()` merges top-level keys only, so a published config whose `webhook`
     * array leaves a key out would lose its default. Fill those in from the package's file.
     */
    private function mergeWebhookDefaults(): void
    {
        // A cached config was merged before it was cached.
        if ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached()) {
            return;
        }
        /** @var Repository $config */
        $config = $this->app->make('config');
        $published = $config->get('kirora.webhook');
        if (is_array($published)) {
            $defaults = require __DIR__ . '/../config/kirora.php';
            $config->set('kirora.webhook', [...$defaults['webhook'], ...$published]);
        }
    }

    /**
     * The client options set in the config. Unset ones are left out, so the SDK applies its
     * defaults and reads `KIRORA_BASE_URL` itself.
     *
     * @return array<string, mixed>
     */
    private static function options(Application $app, Repository $config): array
    {
        $options = array_filter([
            'baseUrl' => ConfigValue::string($config->get('kirora.base_url')),
            'timeout' => ConfigValue::number($config->get('kirora.timeout')),
            'maxRetries' => ConfigValue::number($config->get('kirora.max_retries')),
            'maxRetryDelay' => ConfigValue::number($config->get('kirora.max_retry_delay')),
            'workspace' => ConfigValue::string($config->get('kirora.workspace')),
        ], static fn (mixed $value): bool => $value !== null);
        $options['appInfo'] = ['name' => 'kirora-laravel', 'version' => self::VERSION];

        // The SDK checks that it's a PSR-18 client.
        if ($app->bound(self::HTTP_CLIENT)) {
            $options['httpClient'] = $app->make(self::HTTP_CLIENT);
        }

        return $options;
    }
}
