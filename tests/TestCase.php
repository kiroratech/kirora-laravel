<?php

declare(strict_types=1);

namespace Kirora\Laravel\Tests;

use Kirora\Laravel\KiroraServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public const SECRET = 'whsec_test';

    /**
     * Config set before the providers boot, for the test that sets it with `withConfig()`.
     *
     * @var array<string, mixed>
     */
    private array $config = [];

    protected function getPackageProviders($app): array
    {
        return [KiroraServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('kirora.api_key', 'test_key');
        $app['config']->set('kirora.webhook.secret', self::SECRET);
        foreach ($this->config as $key => $value) {
            $app['config']->set($key, $value);
        }
    }

    /**
     * Boots the application again with this config, for settings read when the providers
     * boot, such as the webhook route's.
     *
     * @param  array<string, mixed>  $config
     */
    public function withConfig(array $config): static
    {
        $this->config = $config;
        $this->refreshApplication();

        return $this;
    }
}
