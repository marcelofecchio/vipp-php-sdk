<?php

declare(strict_types=1);

use MarceloFecchio\Vipp\Config\PerfilVipp;
use MarceloFecchio\Vipp\Config\VippConfig;
use MarceloFecchio\Vipp\Exceptions\AutenticacaoException;
use MarceloFecchio\Vipp\Postagem\NotaFiscal;
use MarceloFecchio\Vipp\Postagem\Postagem;
use MarceloFecchio\Vipp\Postagem\Volume;
use MarceloFecchio\Vipp\Validation\Etiqueta;
use MarceloFecchio\Vipp\VippClient;

// ATENÇÃO: estes testes criam postagens reais no ambiente de homologação do ViPP.
// Só rodam com VIPP_RUN_INTEGRATION=1 (composer test:integration).

beforeEach(function (): void {
    if (getenv('VIPP_RUN_INTEGRATION') !== '1') {
        $this->markTestSkipped('Defina VIPP_RUN_INTEGRATION=1 para rodar contra a homologação do ViPP.');
    }

    $usuario = getenv('VIPP_USUARIO');
    $this->vipp = is_string($usuario) && $usuario !== ''
        ? new VippClient(VippConfig::fromArray([
            'usuario' => $usuario,
            'token' => getenv('VIPP_TOKEN'),
            'id_perfil' => getenv('VIPP_ID_PERFIL'),
            'base_url' => getenv('VIPP_BASE_URL') ?: null,
        ]))
        : VippClient::homologacao();
});

it('registra uma postagem válida e devolve etiqueta, valores e prazo', function (): void {
    $volume = new Volume(declaracaoExemplo(), peso: 1600, altura: 4, largura: 11, comprimento: 16, codigoBarraVolume: 'SDK-TESTE-'.date('YmdHis'));
    $postagem = new Postagem(destinatarioExemplo(), $volume, notasFiscais: [new NotaFiscal(numero: '49906', serie: '1', data: new DateTimeImmutable(), valorTotal: '251,96')]);

    $resultado = $this->vipp->postarObjeto($postagem);

    expect($resultado->ehValida())->toBeTrue($resultado->mensagensDeErro() === [] ? 'inválida' : implode('; ', $resultado->mensagensDeErro()))
        ->and(Etiqueta::valida((string) $resultado->etiqueta()))->toBeTrue()
        ->and($resultado->volume?->valorPostagem?->centavos)->toBeGreaterThan(0)
        ->and($resultado->volume?->diasUteisPrazo)->toBeGreaterThan(0)
        ->and($resultado->resposta)->not->toHaveKey('PerfilVipp');
})->group('integration');

it('lança AutenticacaoException com token errado', function (): void {
    $this->vipp->postarObjeto(postagemExemplo(), new PerfilVipp('onbiws', 'token-errado', '9363'));
})->throws(AutenticacaoException::class)->group('integration');
