<?php

declare(strict_types=1);

use MarceloFecchio\Vipp\Config\PerfilVipp;
use MarceloFecchio\Vipp\Config\VippConfig;
use MarceloFecchio\Vipp\Exceptions\ConfiguracaoException;

it('monta a configuração de homologação com as credenciais públicas do PDF', function (): void {
    $config = VippConfig::homologacao();

    expect($config->perfil->toArray())->toBe(['Usuario' => 'onbiws', 'Token' => '112233', 'IdPerfil' => '9363'])
        ->and($config->urlPostarObjeto())->toBe('https://vpsrv.visualset.com.br/api/v1/middleware/PostarObjeto')
        ->and($config->validarAntesDeEnviar)->toBeTrue();
});

it('lê o formato de config/vipp.php, inclusive valores vindos do .env como string', function (): void {
    $config = VippConfig::fromArray([
        'usuario' => 'loja',
        'token' => 'segredo',
        'id_perfil' => 123,
        'base_url' => '',
        'timeout' => '15',
        'validar_antes_de_enviar' => 'false',
    ]);

    expect($config->perfil->idPerfil)->toBe('123')
        ->and($config->baseUrl)->toBe(VippConfig::URL_PADRAO)
        ->and($config->timeout)->toBe(15.0)
        ->and($config->validarAntesDeEnviar)->toBeFalse();
});

it('aponta a variável de ambiente que falta', function (): void {
    VippConfig::fromArray(['usuario' => 'loja', 'id_perfil' => '1']);
})->throws(ConfiguracaoException::class, 'VIPP_TOKEN');

it('recusa URL base e timeout inválidos', function (string $url, float $timeout): void {
    new VippConfig(VippConfig::homologacao()->perfil, $url, $timeout);
})->with([
    'sem esquema' => ['vpsrv.visualset.com.br', 30.0],
    'ftp' => ['ftp://vpsrv.visualset.com.br', 30.0],
    'timeout zero' => [VippConfig::URL_PADRAO, 0.0],
])->throws(ConfiguracaoException::class);

it('recusa perfil incompleto ou não numérico', function (string $usuario, string $token, string $idPerfil): void {
    new PerfilVipp($usuario, $token, $idPerfil);
})->with([
    ['', 'x', '1'],
    ['u', ' ', '1'],
    ['u', 'x', 'abc'],
])->throws(ConfiguracaoException::class);

it('não mostra o token em var_dump', function (): void {
    ob_start();
    var_dump(new PerfilVipp('loja', 'super-secreto', '1'));
    $saida = (string) ob_get_clean();

    expect($saida)->not->toContain('super-secreto')->toContain('***');
});
