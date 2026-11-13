<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Http\Client\Exception\NetworkException;
use Http\Mock\Client as MockClient;
use MarceloFecchio\Vipp\Config\PerfilVipp;
use MarceloFecchio\Vipp\Enum\BuscarPor;
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

/**
 * @return array<string, string>
 */
function queryEnviada(MockClient $http): array
{
    parse_str($http->getLastRequest()->getUri()->getQuery(), $query);

    return $query;
}

it('consulta por etiqueta com GET e os parâmetros do ViPP', function (): void {
    $this->http->addResponse(respostaFixture(200, 'homologacao-200-situacao-resumo'));

    $this->vipp->situacaoPostagem(' sq000370425br ');

    $request = $this->http->getLastRequest();

    expect($request->getMethod())->toBe('GET')
        ->and($request->getUri()->getHost())->toBe('vpsrv.visualset.com.br')
        ->and($request->getUri()->getPath())->toBe('/api/v1/conhecimento/GetSituacaoPostagem')
        ->and(queryEnviada($this->http))->toBe([
            'Usuario' => 'onbiws',
            'Senha' => '112233',
            'StDadosCompletos' => '0',
            'BuscarPor' => 'EtiquetaPostagem',
            'ListaObjeto' => 'SQ000370425BR',
        ]);
});

it('interpreta a situação real devolvida pela homologação', function (): void {
    $this->http->addResponse(respostaFixture(200, 'homologacao-200-situacao-resumo'));

    $lista = $this->vipp->situacaoPostagem('SQ000370425BR');
    $situacao = $lista[0];

    expect($lista)->toHaveCount(1)
        ->and($situacao->etiqueta)->toBe('SQ000370425BR')
        ->and($situacao->referencia)->toBe('SDK-DEL-E-220723')
        ->and($situacao->dataEntrada?->format('Y-m-d H:i'))->toBe('2026-11-13 19:07')
        ->and($situacao->valorPostagem?->centavos)->toBe(1027)
        ->and($situacao->prazoEntrega)->toBe(1)
        ->and($situacao->prazoEstaContando)->toBeTrue()
        ->and($situacao->entregaDomiciliar)->toBeFalse()
        ->and($situacao->entregaSabados)->toBeFalse()
        ->and($situacao->codigoServico)->toBe('04162')
        ->and($situacao->descricaoServico)->toBe('04162 - SEDEX  (CONTRATO VAREJO)')
        ->and($situacao->nomeDestinatario)->toBe('Teste de WebService')
        ->and($situacao->cepDestinatario)->toBe('01203001')
        ->and($situacao->pesoReal)->toBe(300)
        ->and($situacao->unidadePostadora)->toBe('AGF OZANAN')
        ->and($situacao->resultadoAfericao)->toBe('Não Aferido')
        ->and($situacao->etiquetaImpressa)->toBeFalse()
        ->and($situacao->arImpresso)->toBeFalse()
        ->and($situacao->declaracaoImpressa)->toBeFalse()
        ->and($situacao->idConhecimento)->toBeNull()
        ->and($situacao->dados)->toHaveKey('Etiqueta');
});

it('traz o idConhecimento na consulta completa', function (): void {
    $this->http->addResponse(respostaFixture(200, 'homologacao-200-situacao-completa'));

    $situacao = $this->vipp->situacaoPostagem('SQ000370425BR', completo: true)[0];

    expect(queryEnviada($this->http)['StDadosCompletos'])->toBe('1')
        ->and($situacao->idConhecimento)->toBe(666216982)
        ->and($situacao->dados['PostagensRastreio']['NumeroContrato'])->toBe(35);
});

it('consulta pela referência enviada em codigoBarraVolume, sem validar como etiqueta', function (): void {
    $this->http->addResponse(respostaFixture(200, 'homologacao-200-situacao-resumo'));

    $lista = $this->vipp->situacaoPostagem(' ME-1234 ', BuscarPor::Referencia);

    expect($lista)->toHaveCount(1)
        ->and(queryEnviada($this->http)['BuscarPor'])->toBe('ObservacaoUm')
        ->and(queryEnviada($this->http)['ListaObjeto'])->toBe('ME-1234');
});

it('devolve lista vazia quando o ViPP não encontra nada (HTTP 204)', function (): void {
    $this->http->addResponse(new Response(204));

    expect($this->vipp->situacaoPostagem('SQ000000005BR'))->toBe([]);
});

it('valida o valor antes de enviar', function (string $valor, BuscarPor $por, string $campo): void {
    expect(fn() => $this->vipp->situacaoPostagem($valor, $por))->toThrow(ValidacaoException::class, $campo)
        ->and($this->http->getRequests())->toBe([]);
})->with([
    'etiqueta com DV errado' => ['SQ123456786BR', BuscarPor::Etiqueta, 'Etiqueta'],
    'etiqueta vazia' => ['', BuscarPor::Etiqueta, 'ListaObjeto'],
    'referência vazia' => ['  ', BuscarPor::Referencia, 'ListaObjeto'],
]);

it('lança AutenticacaoException em HTTP 401', function (): void {
    $this->http->addResponse(respostaFixture(401, 'homologacao-401-situacao-login'));

    try {
        $this->vipp->situacaoPostagem('SQ000370425BR');
        $this->fail('Deveria lançar AutenticacaoException.');
    } catch (AutenticacaoException $e) {
        expect($e->erros[0]->codigoErro)->toBe(1030);
    }
});

it('lança HttpException para os demais erros e RespostaInvalidaException para corpo sem SituacaoPostagem', function (): void {
    $this->http->addResponse(new Response(400, [], '{"ListaErros":[{"Campo":"BuscarPor"}]}'));
    $this->http->addResponse(new Response(200, [], '{"outra":"coisa"}'));

    expect(fn() => $this->vipp->situacaoPostagem('SQ000370425BR'))->toThrow(HttpException::class)
        ->and(fn() => $this->vipp->situacaoPostagem('SQ000370425BR'))->toThrow(RespostaInvalidaException::class, 'SituacaoPostagem');
});

it('usa o perfil informado (usuário e senha)', function (): void {
    $this->http->addResponse(new Response(204));

    $this->vipp->situacaoPostagem('SQ000370425BR', perfil: new PerfilVipp('outro', 'segredo', '42'));

    expect(queryEnviada($this->http))->toMatchArray(['Usuario' => 'outro', 'Senha' => 'segredo']);
});

it('não vaza a senha da URL na falha de rede nem encadeia a exceção original', function (): void {
    $url = 'https://vpsrv.visualset.com.br/api/v1/conhecimento/GetSituacaoPostagem?Usuario=onbiws&Senha=112233&StDadosCompletos=0';
    $this->http->addException(new NetworkException("cURL error 28: timeout for {$url}", new Request('GET', $url)));

    try {
        $this->vipp->situacaoPostagem('SQ000370425BR');
        $this->fail('Deveria lançar TransporteException.');
    } catch (TransporteException $e) {
        expect($e->getMessage())->toContain('Senha=***')
            ->not->toContain('112233')
            ->and($e->getPrevious())->toBeNull();
    }
});

describe('verificarCredenciais', function (): void {
    it('aprova credenciais válidas sem criar nada (consulta uma etiqueta inexistente)', function (): void {
        $this->http->addResponse(new Response(204));

        $resultado = $this->vipp->verificarCredenciais();

        expect($resultado->autenticado)->toBeTrue()
            ->and($resultado->erros)->toBe([])
            ->and($resultado->httpStatus)->toBe(204)
            ->and($this->http->getLastRequest()->getMethod())->toBe('GET')
            ->and(queryEnviada($this->http)['ListaObjeto'])->toBe('SQ000000005BR');
    });

    it('devolve resultado (sem exceção) quando as credenciais são recusadas', function (): void {
        $this->http->addResponse(respostaFixture(401, 'homologacao-401-situacao-login'));

        $resultado = $this->vipp->verificarCredenciais(new PerfilVipp('onbiws', 'errado', '9363'));

        expect($resultado->autenticado)->toBeFalse()
            ->and($resultado->httpStatus)->toBe(401)
            ->and($resultado->erros[0]->codigoErro)->toBe(1030)
            ->and($resultado->mensagensDeErro())->toBe(['[1030] Login: Usuário ou Senha Inválidos!'])
            ->and(queryEnviada($this->http)['Senha'])->toBe('errado');
    });

    it('lança HttpException em outros erros', function (): void {
        $this->http->addResponse(new Response(500, [], 'erro'));

        $this->vipp->verificarCredenciais();
    })->throws(HttpException::class);
});
