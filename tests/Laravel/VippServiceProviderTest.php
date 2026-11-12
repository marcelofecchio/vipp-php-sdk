<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use MarceloFecchio\Vipp\Config\VippConfig;
use MarceloFecchio\Vipp\Exceptions\ConfiguracaoException;
use MarceloFecchio\Vipp\Laravel\VippServiceProvider;
use MarceloFecchio\Vipp\VippClient;
use MarceloFecchio\Vipp\VippClientInterface;

it('registra o cliente como singleton, pela interface e pela classe', function (): void {
    $cliente = app(VippClientInterface::class);

    expect($cliente)->toBeInstanceOf(VippClient::class)
        ->and(app(VippClientInterface::class))->toBe($cliente)
        ->and(app(VippClient::class))->toBe($cliente);
});

it('mescla os valores padrão de config/vipp.php', function (): void {
    expect(config('vipp.base_url'))->toBe('https://vpsrv.visualset.com.br')
        ->and(config('vipp.timeout'))->toBe(30)
        ->and(config('vipp.validar_antes_de_enviar'))->toBeTrue();
});

it('monta a VippConfig a partir da configuração do Laravel', function (): void {
    config()->set('vipp.timeout', '12');

    $config = app(VippConfig::class);

    expect($config->perfil->usuario)->toBe('onbiws')
        ->and($config->perfil->idPerfil)->toBe('9363')
        ->and($config->timeout)->toBe(12.0);
});

it('só exige as credenciais quando o cliente é usado', function (): void {
    config()->set('vipp.token', null);

    expect(fn() => app(VippClientInterface::class))->toThrow(ConfiguracaoException::class, 'VIPP_TOKEN');
});

it('publica o arquivo de configuração com a tag vipp-config', function (): void {
    $caminhos = ServiceProvider::pathsToPublish(VippServiceProvider::class, 'vipp-config');

    expect($caminhos)->toHaveCount(1)
        ->and(str_replace('\\', '/', (string) array_key_first($caminhos)))->toEndWith('/config/vipp.php')
        ->and(array_values($caminhos)[0])->toBe(config_path('vipp.php'));
});

it('liga exigirContrato pela configuração', function (): void {
    expect(config('vipp.exigir_contrato'))->toBeFalse()
        ->and(app(VippConfig::class)->exigirContrato)->toBeFalse();

    config()->set('vipp.exigir_contrato', 'true');
    app()->forgetInstance(VippConfig::class);

    expect(app(VippConfig::class)->exigirContrato)->toBeTrue();
});
