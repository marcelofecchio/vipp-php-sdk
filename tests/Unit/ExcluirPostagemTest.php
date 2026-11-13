<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Http\Client\Exception\NetworkException;
use Http\Mock\Client as MockClient;
use MarceloFecchio\Vipp\Config\PerfilVipp;
use MarceloFecchio\Vipp\Config\VippConfig;
use MarceloFecchio\Vipp\Exceptions\AutenticacaoException;
use MarceloFecchio\Vipp\Exceptions\HttpException;
use MarceloFecchio\Vipp\Exceptions\RespostaInvalidaException;
use MarceloFecchio\Vipp\Exceptions\TransporteException;
use MarceloFecchio\Vipp\Exceptions\ValidacaoException;
use MarceloFecchio\Vipp\VippClient;

beforeEach(function (): void {
    $this->http = new MockClient();
    $this->vipp = VippClient::homologacao($this->http);
});

it('envia DELETE sem corpo, com usuário, token e etiqueta nos cabeçalhos', function (): void {
    $this->http->addResponse(respostaFixture(200, 'inferida-200-exclusao-ok'));

    $this->vipp->excluirPostagem(' sq000352886br ');

    $request = $this->http->getLastRequest();

    expect($request->getMethod())->toBe('DELETE')
        ->and((string) $request->getUri())->toBe('https://vpsrv.visualset.com.br/api/v1/conhecimento/DeleteConhecimento')
        ->and($request->getHeaderLine('Usuario'))->toBe('onbiws')
        ->and($request->getHeaderLine('Token'))->toBe('112233')
        ->and($request->getHeaderLine('Etiqueta'))->toBe('SQ000352886BR')
        ->and((string) $request->getBody())->toBe('');
});

it('interpreta a exclusão que removeu o objeto', function (): void {
    $this->http->addResponse(respostaFixture(200, 'inferida-200-exclusao-ok'));

    $resultado = $this->vipp->excluirPostagem('SQ000352886BR');

    expect($resultado->excluida())->toBeTrue()
        ->and($resultado->etiqueta)->toBe('SQ000352886BR')
        ->and($resultado->totalConhecimentos)->toBe(1)
        ->and($resultado->conhecimentosExcluidos)->toBe(1)
        ->and($resultado->httpStatus)->toBe(200);
});

it('trata etiqueta sem objeto elegível como resultado, não como erro (resposta real da homologação)', function (): void {
    $this->http->addResponse(respostaFixture(200, 'homologacao-200-exclusao-nada-excluido'));

    $resultado = $this->vipp->excluirPostagem('SQ000000005BR');

    expect($resultado->excluida())->toBeFalse()
        ->and($resultado->totalConhecimentos)->toBe(0)
        ->and($resultado->conhecimentosExcluidos)->toBe(0)
        ->and($resultado->arquivosExcluidos)->toBe(0)
        ->and($resultado->objetoEPPN)->toBe(0)
        ->and($resultado->resposta)->toHaveKey('ConhecimentosExcluidos');
});

it('lança AutenticacaoException lendo os erros que vêm dentro de "Message"', function (string $fixture, array $codigos): void {
    $this->http->addResponse(respostaFixture(401, $fixture));

    try {
        $this->vipp->excluirPostagem('SQ000352886BR');
        $this->fail('Deveria lançar AutenticacaoException.');
    } catch (AutenticacaoException $e) {
        expect(array_map(static fn($erro) => $erro->codigoErro, $e->erros))->toBe($codigos)
            ->and($e->getMessage())->toContain('ViPP recusou as credenciais');
    }
})->with([
    'token errado' => ['homologacao-401-exclusao-login', [1030]],
    'sem credenciais' => ['homologacao-401-exclusao-sem-credenciais', [1000, 1010]],
]);

it('valida a etiqueta antes de enviar', function (string $etiqueta): void {
    expect(fn() => $this->vipp->excluirPostagem($etiqueta))->toThrow(ValidacaoException::class, 'Etiqueta')
        ->and($this->http->getRequests())->toBe([]);
})->with(['SQ123456786BR', 'SQ12345678BR', '']);

it('não valida a etiqueta quando validarAntesDeEnviar está desligado', function (): void {
    $this->http->addResponse(respostaFixture(200, 'homologacao-200-exclusao-nada-excluido'));
    $vipp = new VippClient(new VippConfig(VippConfig::homologacao()->perfil, validarAntesDeEnviar: false), $this->http);

    expect($vipp->excluirPostagem('SQ123456786BR')->excluida())->toBeFalse()
        ->and($this->http->getRequests())->toHaveCount(1);
});

it('usa o perfil informado na chamada', function (): void {
    $this->http->addResponse(respostaFixture(200, 'inferida-200-exclusao-ok'));

    $this->vipp->excluirPostagem('SQ000352886BR', new PerfilVipp('outro', 'segredo', '42'));

    $request = $this->http->getLastRequest();

    expect($request->getHeaderLine('Usuario'))->toBe('outro')
        ->and($request->getHeaderLine('Token'))->toBe('segredo');
});

it('lança HttpException para outros erros HTTP, como o 400 de SQL do servidor', function (): void {
    $this->http->addResponse(new Response(400, [], "Procedure or function 'ConhecimentoListarParaExcluirPPN' expects parameter '@ValorDaBusca', which was not supplied."));

    try {
        $this->vipp->excluirPostagem('SQ000352886BR');
        $this->fail('Deveria lançar HttpException.');
    } catch (HttpException $e) {
        expect($e->statusCode)->toBe(400)
            ->and($e->corpo)->toContain('ValorDaBusca');
    }
});

it('lança RespostaInvalidaException quando o 200 não traz as contagens', function (): void {
    $this->http->addResponse(new Response(200, [], '{"IdConhecimento": 1}'));

    $this->vipp->excluirPostagem('SQ000352886BR');
})->throws(RespostaInvalidaException::class, 'ConhecimentosExcluidos');

it('converte falha de rede em TransporteException', function (): void {
    $this->http->addException(new NetworkException('timeout', new Request('DELETE', '/')));

    $this->vipp->excluirPostagem('SQ000352886BR');
})->throws(TransporteException::class);
