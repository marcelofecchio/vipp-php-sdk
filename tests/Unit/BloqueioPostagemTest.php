<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Http\Client\Exception\NetworkException;
use Http\Mock\Client as MockClient;
use MarceloFecchio\Vipp\Config\PerfilVipp;
use MarceloFecchio\Vipp\Exceptions\AutenticacaoException;
use MarceloFecchio\Vipp\Exceptions\HttpException;
use MarceloFecchio\Vipp\Exceptions\RespostaInvalidaException;
use MarceloFecchio\Vipp\Exceptions\TransporteException;
use MarceloFecchio\Vipp\Exceptions\ValidacaoException;
use MarceloFecchio\Vipp\Resultado\ResultadoBloqueio;
use MarceloFecchio\Vipp\VippClient;

beforeEach(function (): void {
    $this->http = new MockClient();
    $this->vipp = VippClient::homologacao($this->http);
});

function respostaXml(int $status, string $nome): Response
{
    return new Response($status, ['Content-Type' => 'text/xml; charset=utf-8'], (string) file_get_contents(__DIR__."/../Fixtures/{$nome}.xml"));
}

it('envia o envelope SOAP de bloqueio para o PostagemVipp.asmx', function (): void {
    $this->http->addResponse(respostaXml(200, 'homologacao-200-soap-bloquear'));

    $this->vipp->bloquearPostagem(' sq000380635br ', 'Exclusão & teste <1>');

    $request = $this->http->getLastRequest();
    $corpo = (string) $request->getBody();

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://vpsrv.visualset.com.br/PostagemVipp.asmx')
        ->and($request->getHeaderLine('Content-Type'))->toBe('text/xml; charset=utf-8')
        ->and($request->getHeaderLine('SOAPAction'))->toBe('"http://www.visualset.inf.br/BloquearDownloadConhecimento"')
        ->and($corpo)->toContain('<v:BloquearDownloadConhecimento><v:BloquearPostagem>')
        ->toContain('<v:PerfilVipp><v:Usuario>onbiws</v:Usuario><v:Token>112233</v:Token><v:IdPerfil>9363</v:IdPerfil></v:PerfilVipp>')
        ->toContain('<v:Etiqueta>SQ000380635BR</v:Etiqueta><v:StBloqueado>1</v:StBloqueado>')
        ->toContain('<v:Descricao>Exclusão &amp; teste &lt;1&gt;</v:Descricao>');

    $documento = new DOMDocument();
    expect($documento->loadXML($corpo))->toBeTrue();
});

it('omite a descrição quando não informada', function (): void {
    $this->http->addResponse(respostaXml(200, 'homologacao-200-soap-bloquear'));

    $this->vipp->bloquearPostagem('SQ000380635BR', '  ');

    expect((string) $this->http->getLastRequest()->getBody())->not->toContain('Descricao');
});

it('interpreta o bloqueio confirmado (resposta real da homologação), sem expor o token', function (): void {
    $this->http->addResponse(respostaXml(200, 'homologacao-200-soap-bloquear'));

    $resultado = $this->vipp->bloquearPostagem('SQ000380635BR');

    expect($resultado->confirmado())->toBeTrue()
        ->and($resultado->operacao)->toBe(ResultadoBloqueio::BLOQUEIO)
        ->and($resultado->etiqueta)->toBe('SQ000380635BR')
        ->and($resultado->status)->toBe(1)
        ->and($resultado->descricao)->toBe('Objeto Bloqueado')
        ->and($resultado->erros)->toBe([])
        ->and($resultado->httpStatus)->toBe(200)
        ->and($resultado->dados)->not->toHaveKey('PerfilVipp')
        ->and($resultado->xml)->toContain('<Token>***</Token>')
        ->not->toContain('112233');
});

it('envia o envelope de liberação e interpreta a resposta real', function (): void {
    $this->http->addResponse(respostaXml(200, 'homologacao-200-soap-liberar'));

    $resultado = $this->vipp->liberarPostagem('SQ000380649BR');
    $request = $this->http->getLastRequest();

    expect($request->getHeaderLine('SOAPAction'))->toBe('"http://www.visualset.inf.br/LiberarDownloadConhecimento"')
        ->and((string) $request->getBody())->toContain('<v:LiberarPostagem>')
        ->toContain('<v:Etiqueta>SQ000380649BR</v:Etiqueta><v:StLiberado>1</v:StLiberado>')
        ->not->toContain('Descricao')
        ->and($resultado->confirmado())->toBeTrue()
        ->and($resultado->operacao)->toBe(ResultadoBloqueio::LIBERACAO)
        ->and($resultado->status)->toBe(1);
});

it('lança AutenticacaoException com credencial errada, que o SOAP devolve em HTTP 200', function (): void {
    $this->http->addResponse(respostaXml(200, 'homologacao-200-soap-bloquear-login'));

    try {
        $this->vipp->bloquearPostagem('SQ000000005BR', perfil: new PerfilVipp('onbiws', 'errado', '9363'));
        $this->fail('Deveria lançar AutenticacaoException.');
    } catch (AutenticacaoException $e) {
        expect($e->statusCode)->toBe(200)
            ->and($e->erros[0]->codigoErro)->toBe(1030)
            ->and($e->erros[0]->campo)->toBe('Login')
            ->and($e->corpo)->toContain('<Token>***</Token>')
            ->not->toContain('errado');
    }
});

it('devolve resultado não confirmado para outros erros em ListaErros', function (): void {
    $xml = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
        .'<BloquearDownloadConhecimentoResponse xmlns="http://www.visualset.inf.br/"><BloquearDownloadConhecimentoResult>'
        .'<Etiqueta>SQ000380635BR</Etiqueta><StBloqueado>0</StBloqueado><ListaErros><Erro><TipoErro>RegraNegocio</TipoErro>'
        .'<CodigoErro>9999</CodigoErro><Atributo>Etiqueta</Atributo><Descricao>Objeto não pode ser bloqueado</Descricao></Erro></ListaErros>'
        .'</BloquearDownloadConhecimentoResult></BloquearDownloadConhecimentoResponse></soap:Body></soap:Envelope>';
    $this->http->addResponse(new Response(200, [], $xml));

    $resultado = $this->vipp->bloquearPostagem('SQ000380635BR');

    expect($resultado->confirmado())->toBeFalse()
        ->and($resultado->mensagensDeErro())->toBe(['[9999] Etiqueta: Objeto não pode ser bloqueado']);
});

it('lança HttpException com a mensagem do SOAP Fault (resposta real)', function (): void {
    $this->http->addResponse(respostaXml(500, 'homologacao-500-soap-fault'));

    try {
        $this->vipp->bloquearPostagem('SQ000380635BR');
        $this->fail('Deveria lançar HttpException.');
    } catch (HttpException $e) {
        expect($e->statusCode)->toBe(500)
            ->and($e->getMessage())->toContain('SOAP Fault')
            ->toContain('Server did not recognize the value of HTTP Header SOAPAction');
    }
});

it('lança HttpException sem Fault e RespostaInvalidaException para 200 sem resultado', function (): void {
    $this->http->addResponse(new Response(400, [], ''));
    $this->http->addResponse(new Response(200, [], '<html>ok</html>'));

    expect(fn() => $this->vipp->liberarPostagem('SQ000380649BR'))->toThrow(HttpException::class, 'HTTP 400')
        ->and(fn() => $this->vipp->liberarPostagem('SQ000380649BR'))->toThrow(RespostaInvalidaException::class, 'LiberarDownloadConhecimentoResult');
});

it('valida a etiqueta antes de enviar', function (): void {
    expect(fn() => $this->vipp->bloquearPostagem('SQ123456786BR'))->toThrow(ValidacaoException::class, 'Etiqueta')
        ->and(fn() => $this->vipp->liberarPostagem(''))->toThrow(ValidacaoException::class, 'Etiqueta')
        ->and($this->http->getRequests())->toBe([]);
});

it('converte falha de rede em TransporteException', function (): void {
    $this->http->addException(new NetworkException('timeout', new Request('POST', '/')));

    $this->vipp->bloquearPostagem('SQ000380635BR');
})->throws(TransporteException::class);
