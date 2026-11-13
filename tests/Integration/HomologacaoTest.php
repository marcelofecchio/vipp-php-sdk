<?php

declare(strict_types=1);

use MarceloFecchio\Vipp\Config\PerfilVipp;
use MarceloFecchio\Vipp\Config\VippConfig;
use MarceloFecchio\Vipp\Enum\BuscarPor;
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

// Seguros: usam uma etiqueta inexistente, então nunca excluem nada. O ViPP só exclui objetos "pré-postados aguardando
// liberação", e o perfil de homologação cria os objetos já postados, então o caminho de sucesso não é testável aqui.
it('exclusão de etiqueta sem objeto elegível volta 200 com tudo zerado', function (): void {
    $resultado = $this->vipp->excluirPostagem('SQ000000005BR');

    expect($resultado->excluida())->toBeFalse()
        ->and($resultado->totalConhecimentos)->toBe(0);
})->group('integration');

it('exclusão com token errado lança AutenticacaoException', function (): void {
    $this->vipp->excluirPostagem('SQ000000005BR', new PerfilVipp('onbiws', 'token-errado', '9363'));
})->throws(AutenticacaoException::class)->group('integration');

// Somente leitura: não criam nem alteram nada no ViPP.
it('verificarCredenciais aprova as credenciais da homologação', function (): void {
    $resultado = $this->vipp->verificarCredenciais();

    expect($resultado->autenticado)->toBeTrue()
        ->and($resultado->erros)->toBe([]);
})->group('integration');

it('verificarCredenciais recusa token errado sem lançar exceção', function (): void {
    $resultado = $this->vipp->verificarCredenciais(new PerfilVipp('onbiws', 'token-errado', '9363'));

    expect($resultado->autenticado)->toBeFalse()
        ->and($resultado->erros[0]->codigoErro)->toBe(1030);
})->group('integration');

it('situacaoPostagem devolve lista vazia para etiqueta e referência inexistentes', function (): void {
    expect($this->vipp->situacaoPostagem('SQ000000005BR'))->toBe([])
        ->and($this->vipp->situacaoPostagem('REFERENCIA-QUE-NAO-EXISTE', BuscarPor::Referencia))->toBe([]);
})->group('integration');

it('situacaoPostagem encontra pela referência uma postagem criada agora', function (): void {
    $referencia = 'SDK-SIT-'.date('YmdHis');
    $volume = new Volume(declaracaoExemplo(), peso: 300, codigoBarraVolume: $referencia);
    $postado = $this->vipp->postarObjeto(postagemExemplo($volume));

    $lista = $this->vipp->situacaoPostagem($referencia, BuscarPor::Referencia, completo: true);

    expect($postado->ehValida())->toBeTrue()
        ->and($lista)->toHaveCount(1)
        ->and($lista[0]->etiqueta)->toBe($postado->etiqueta())
        ->and($lista[0]->idConhecimento)->toBe($postado->volume?->idConhecimento);
})->group('integration');
