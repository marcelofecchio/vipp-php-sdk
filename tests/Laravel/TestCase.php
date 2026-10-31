<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Tests\Laravel;

use MarceloFecchio\Vipp\Laravel\Facades\Vipp;
use MarceloFecchio\Vipp\Laravel\VippServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [VippServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Vipp' => Vipp::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('vipp.usuario', 'onbiws');
        $app['config']->set('vipp.token', '112233');
        $app['config']->set('vipp.id_perfil', '9363');
    }
}
