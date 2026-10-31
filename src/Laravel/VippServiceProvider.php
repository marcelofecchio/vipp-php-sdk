<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Laravel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use MarceloFecchio\Vipp\Config\VippConfig;
use MarceloFecchio\Vipp\VippClient;
use MarceloFecchio\Vipp\VippClientInterface;

/**
 * Registrado pela package auto-discovery. As credenciais só são lidas quando o cliente é usado pela primeira vez,
 * então a aplicação sobe normalmente mesmo sem `VIPP_*` no `.env`.
 */
final class VippServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(self::arquivoConfig(), 'vipp');

        $this->app->singleton(VippConfig::class, static function (Application $app): VippConfig {
            $config = $app->make(Repository::class)->get('vipp', []);

            return VippConfig::fromArray(is_array($config) ? $config : []);
        });

        $this->app->singleton(VippClient::class, static fn(Application $app): VippClient => new VippClient($app->make(VippConfig::class)));

        $this->app->singleton(VippClientInterface::class, static fn(Application $app): VippClientInterface => $app->make(VippClient::class));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([self::arquivoConfig() => $this->app->configPath('vipp.php')], 'vipp-config');
        }
    }

    private static function arquivoConfig(): string
    {
        return dirname(__DIR__, 2).'/config/vipp.php';
    }
}
