<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Http\Client\Exception\NetworkException;
use Http\Mock\Client as MockClient;
use MarceloFecchio\Vipp\Config\PerfilVipp;
use MarceloFecchio\Vipp\Config\VippConfig;
use MarceloFecchio\Vipp\Enum\StatusPostagem;
use MarceloFecchio\Vipp\Exceptions\AutenticacaoException;
use MarceloFecchio\Vipp\Exceptions\HttpException;
use MarceloFecchio\Vipp\Exceptions\RespostaInvalidaException;
use MarceloFecchio\Vipp\Exceptions\TransporteException;
use MarceloFecchio\Vipp\Exceptions\ValidacaoException;
use MarceloFecchio\Vipp\Postagem\ContratoEct;
use MarceloFecchio\Vipp\Postagem\Destinatario;
use MarceloFecchio\Vipp\Postagem\Postagem;
use MarceloFecchio\Vipp\Postagem\Volume;
use MarceloFecchio\Vipp\Resultado\ErroVipp;
use MarceloFecchio\Vipp\VippClient;

beforeEach(function (): void {
    $this->http = new MockClient();
    $this->vipp = VippClient::homologacao($this->http);
});

/**
 * @return array<string, mixed>
 */
function corpoEnviado(MockClient $http): array
{
    $request = $http->getLastRequest();
    expect($request)->not->toBeFalse();

    return json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
}

it('envia POST em JSON para o endpoint PostarObjeto com o PerfilVipp primeiro', function (): void {
    $this->http->addResponse(respostaFixture(200, 'homologacao-200-valida'));

    $this->vipp->postarObjeto(postagemExemplo());

    $request = $this->http->getLastRequest();
    $corpo = corpoEnviado($this->http);

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://vpsrv.visualset.com.br/api/v1/middleware/PostarObjeto')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and($request->getHeaderLine('Accept-Encoding'))->toBe('UTF-8')
        ->and(array_key_first($corpo))->toBe('PerfilVipp')
        ->and($corpo['PerfilVipp'])->toBe(['Usuario' => 'onbiws', 'Token' => '112233', 'IdPerfil' => '9363'])
        ->and($corpo['Destinatario']['Cep'])->toBe('01203001')
        ->and($corpo['Volumes'][0]['DeclaracaoConteudo']['ItemConteudo'][0]['Valor'])->toBe('125,98');
});

it('interpreta a resposta válida real da homologação', function (): void {
    $this->http->addResponse(respostaFixture(200, 'homologacao-200-valida'));

    $resultado = $this->vipp->postarObjeto(postagemExemplo());
    $volume = $resultado->volume;

    expect($resultado->ehValida())->toBeTrue()
        ->and($resultado->status)->toBe(StatusPostagem::Valida)
        ->and($resultado->etiqueta())->toBe('SQ000352886BR')
        ->and($resultado->erros)->toBe([])
        ->and($resultado->servicoEct)->toBe('4162')
        ->and($resultado->httpStatus)->toBe(200)
        ->and($resultado->resposta)->not->toHaveKey('PerfilVipp')
        ->and($volume?->idConhecimento)->toBe(663471935)
        ->and($volume?->valorTarifa?->centavos)->toBe(1548)
        ->and($volume?->valorAdicionais?->centavos)->toBe(1299)
        ->and($volume?->valorPostagem?->centavos)->toBe(2847)
        ->and($volume?->valorDeclarado?->centavos)->toBe(15000)
        ->and($volume?->entregaSabado)->toBeTrue()
        ->and($volume?->entregaDomiciliar)->toBeTrue()
        ->and($volume?->diasUteisPrazo)->toBe(1)
        ->and($volume?->adicionais)->toBe('AR VD')
        ->and($volume?->codigoBarraVolume)->toBe('PED-123456')
        ->and($volume?->categoria)->toBe(200);
});

it('sinaliza quando o ViPP registra uma etiqueta diferente da enviada', function (): void {
    $this->http->addResponse(respostaFixture(200, 'homologacao-200-valida'));

    $volume = new Volume(declaracaoExemplo(), peso: 1600, etiqueta: 'AA123456785BR');
    $resultado = $this->vipp->postarObjeto(postagemExemplo($volume));

    expect($resultado->etiquetaEnviada)->toBe('AA123456785BR')
        ->and($resultado->etiqueta())->toBe('SQ000352886BR')
        ->and($resultado->etiquetaDivergente())->toBeTrue();
});

it('devolve resultado inválido (sem exceção) quando o ViPP recusa os dados com HTTP 400', function (string $fixture, int $codigo): void {
    $this->http->addResponse(respostaFixture(400, $fixture));

    $resultado = new VippClient(new VippConfig(VippConfig::homologacao()->perfil, validarAntesDeEnviar: false), $this->http)
        ->postarObjeto(postagemExemplo());

    expect($resultado->ehValida())->toBeFalse()
        ->and($resultado->etiqueta())->toBeNull()
        ->and($resultado->httpStatus)->toBe(400)
        ->and($resultado->erros[0]->codigoErro)->toBe($codigo)
        ->and($resultado->mensagensDeErro()[0])->toStartWith("[{$codigo}]");
})->with([
    'campos obrigatórios' => ['homologacao-400-campos-obrigatorios', 1310],
    'DV da etiqueta' => ['homologacao-400-etiqueta-dv', 2002],
    'data da NF' => ['homologacao-400-data-nf', 1500],
    'cartão sem contrato' => ['homologacao-400-cartao-sem-contrato', 1100],
    'contrato obrigatório em produção' => ['producao-400-contrato-91', 91],
]);

it('traduz o erro 91 sem descrição e expõe exigeContrato()', function (): void {
    $this->http->addResponse(respostaFixture(400, 'producao-400-contrato-91'));

    $resultado = $this->vipp->postarObjeto(postagemExemplo());
    $erro = $resultado->erros[0];

    expect($resultado->exigeContrato())->toBeTrue()
        ->and($erro->ehContratoObrigatorio())->toBeTrue()
        ->and($erro->semDescricao)->toBeTrue()
        ->and($resultado->mensagensDeErro())->toBe([
            '[91] NumeroContrato: obrigatório neste perfil (erro 91 do ViPP, sem descrição); informe ContratoEct com NrContrato e NrCartao.',
            '[91] NumeroCartao: obrigatório neste perfil (erro 91 do ViPP, sem descrição); informe ContratoEct com NrContrato e NrCartao.',
        ]);
});

it('mantém o texto genérico para outros erros sem descrição', function (): void {
    $erro = ErroVipp::fromArray(['CodigoErro' => 1234, 'Campo' => 'Algo', 'Descricao' => '']);

    expect((string) $erro)->toBe('[1234] Algo: Erro sem descrição')
        ->and($erro->semDescricao)->toBeTrue()
        ->and($erro->ehContratoObrigatorio())->toBeFalse();
});

it('não marca exigeContrato() em outros erros', function (): void {
    $this->http->addResponse(respostaFixture(400, 'homologacao-400-cartao-sem-contrato'));

    expect($this->vipp->postarObjeto(new Postagem(destinatarioExemplo(), postagemExemplo()->volume, contratoEct: new ContratoEct('1', null, '2')))->exigeContrato())->toBeFalse();
});

it('barra a postagem sem contrato antes do envio quando exigirContrato está ligado', function (): void {
    $config = new VippConfig(VippConfig::homologacao()->perfil, exigirContrato: true);
    $vipp = new VippClient($config, $this->http);

    expect(fn() => $vipp->postarObjeto(postagemExemplo()))->toThrow(ValidacaoException::class, 'ContratoEct.NrContrato')
        ->and($this->http->getRequests())->toBe([])
        ->and($vipp->validar(postagemExemplo()))->toHaveCount(2);
});

it('lança AutenticacaoException em HTTP 401, sem expor o token', function (string $fixture, int $codigo): void {
    $this->http->addResponse(respostaFixture(401, $fixture));

    try {
        $this->vipp->postarObjeto(postagemExemplo());
        $this->fail('Deveria lançar AutenticacaoException.');
    } catch (AutenticacaoException $e) {
        expect($e->statusCode)->toBe(401)
            ->and($e->erros[0]->codigoErro)->toBe($codigo)
            ->and($e->ehTemporario())->toBeFalse()
            ->and($e->corpo)->toContain('"Token": "***"')
            ->not->toContain('112233')
            ->not->toContain('errado');
    }
})->with([
    'login' => ['homologacao-401-login', 1030],
    'perfil' => ['homologacao-401-perfil', 1002],
]);

it('lança HttpException para outros erros HTTP', function (int $status, bool $temporario): void {
    $this->http->addResponse(new Response($status, ['Content-Type' => 'text/html'], '<html>erro</html>'));

    try {
        $this->vipp->postarObjeto(postagemExemplo());
        $this->fail('Deveria lançar HttpException.');
    } catch (HttpException $e) {
        expect($e->statusCode)->toBe($status)
            ->and($e->ehTemporario())->toBe($temporario)
            ->and($e->getMessage())->toContain("HTTP {$status}");
    }
})->with([[404, false], [423, false], [429, true], [500, true], [504, true], [400, false]]);

it('lança RespostaInvalidaException quando HTTP 200 não traz JSON', function (): void {
    $this->http->addResponse(new Response(200, [], 'ok'));

    $this->vipp->postarObjeto(postagemExemplo());
})->throws(RespostaInvalidaException::class);

it('converte falha de rede em TransporteException', function (): void {
    $this->http->addException(new NetworkException('timeout', new Request('POST', '/')));

    $this->vipp->postarObjeto(postagemExemplo());
})->throws(TransporteException::class, 'Falha de comunicação com o ViPP: timeout');

it('não faz requisição quando a validação local falha', function (): void {
    $destinatario = new Destinatario(nome: '', endereco: 'Rua', numero: '1', bairro: 'B', cidade: 'C', uf: 'SP', cep: '01203001');

    expect(fn() => $this->vipp->postarObjeto(postagemExemplo(destinatario: $destinatario)))->toThrow(ValidacaoException::class)
        ->and($this->http->getRequests())->toBe([]);
});

it('usa o perfil informado na chamada no lugar do perfil da configuração', function (): void {
    $this->http->addResponse(respostaFixture(200, 'homologacao-200-valida'));

    $this->vipp->postarObjeto(postagemExemplo(), new PerfilVipp('outro', 'segredo', '42'));

    expect(corpoEnviado($this->http)['PerfilVipp'])->toBe(['Usuario' => 'outro', 'Token' => 'segredo', 'IdPerfil' => '42']);
});

it('respeita a URL base configurada', function (): void {
    $this->http->addResponse(respostaFixture(200, 'homologacao-200-valida'));
    $config = new VippConfig(VippConfig::homologacao()->perfil, 'http://localhost:8080/');

    new VippClient($config, $this->http)->postarObjeto(postagemExemplo());

    expect((string) $this->http->getLastRequest()->getUri())->toBe('http://localhost:8080/api/v1/middleware/PostarObjeto');
});

it('expõe a validação local sem enviar nada', function (): void {
    expect($this->vipp->validar(postagemExemplo()))->toBe([])
        ->and($this->http->getRequests())->toBe([]);
});

it('cria o cliente HTTP padrão (Guzzle) quando nenhum é injetado', function (): void {
    expect(new VippClient(VippConfig::homologacao()))->toBeInstanceOf(VippClient::class);
});
