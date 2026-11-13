<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Http;

use JsonException;
use MarceloFecchio\Vipp\Exceptions\TransporteException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Envia o JSON ao ViPP e devolve status + corpo decodificado. Não interpreta o conteúdo nem repete requisições.
 *
 * @internal
 */
final readonly class Transport
{
    public function __construct(
        private ClientInterface $http,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    /**
     * @param array<string, mixed> $payload
     *
     * @throws TransporteException
     */
    public function post(string $url, array $payload): RespostaHttp
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        // Accept-Encoding: UTF-8 é pedido pelo PDF; a homologação funciona sem ele, mas é mantido por compatibilidade.
        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withHeader('Accept-Encoding', 'UTF-8')
            ->withBody($this->streamFactory->createStream($json));

        return $this->enviar($request);
    }

    /**
     * DELETE sem corpo: o DeleteConhecimento recebe usuário, token e etiqueta em cabeçalhos.
     *
     * @param array<string, string> $cabecalhos
     *
     * @throws TransporteException
     */
    public function delete(string $url, array $cabecalhos): RespostaHttp
    {
        $request = $this->requestFactory->createRequest('DELETE', $url)->withHeader('Accept', 'application/json');
        foreach ($cabecalhos as $nome => $valor) {
            $request = $request->withHeader($nome, $valor);
        }

        return $this->enviar($request);
    }

    /**
     * @throws TransporteException
     */
    private function enviar(RequestInterface $request): RespostaHttp
    {
        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new TransporteException('Falha de comunicação com o ViPP: '.$e->getMessage(), 0, $e);
        }

        $corpo = (string) $response->getBody();

        return new RespostaHttp($response->getStatusCode(), $corpo, self::decodificar($corpo));
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private static function decodificar(string $corpo): ?array
    {
        // Remove BOM, que servidores IIS às vezes incluem.
        $corpo = preg_replace('/^\xEF\xBB\xBF/', '', $corpo) ?? $corpo;

        try {
            $dados = json_decode($corpo, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($dados) ? $dados : null;
    }
}
