<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp;

use Http\Discovery\Psr17FactoryDiscovery;
use MarceloFecchio\Vipp\Config\PerfilVipp;
use MarceloFecchio\Vipp\Config\VippConfig;
use MarceloFecchio\Vipp\Exceptions\AutenticacaoException;
use MarceloFecchio\Vipp\Exceptions\HttpException;
use MarceloFecchio\Vipp\Exceptions\RespostaInvalidaException;
use MarceloFecchio\Vipp\Http\HttpClientFactory;
use MarceloFecchio\Vipp\Http\Mascara;
use MarceloFecchio\Vipp\Http\RespostaHttp;
use MarceloFecchio\Vipp\Http\Transport;
use MarceloFecchio\Vipp\Postagem\Postagem;
use MarceloFecchio\Vipp\Resultado\ErroVipp;
use MarceloFecchio\Vipp\Resultado\ResultadoPostagem;
use MarceloFecchio\Vipp\Support\Leitor;
use MarceloFecchio\Vipp\Validation\ValidadorPostagem;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Cliente do Web Service de Postagem do ViPP (REST/JSON).
 *
 * Sem estado mutável entre chamadas: pode ser singleton e ser usado em jobs/filas.
 * Não repete requisições: a postagem não é idempotente e o reenvio pode duplicar o objeto.
 */
final readonly class VippClient implements VippClientInterface
{
    private Transport $transport;

    public function __construct(
        private VippConfig $config,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        $this->transport = new Transport(
            $httpClient ?? HttpClientFactory::criar($config->timeout),
            $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory(),
            $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory(),
        );
    }

    /**
     * Cliente apontando para a homologação com as credenciais públicas do PDF.
     */
    public static function homologacao(?ClientInterface $httpClient = null): self
    {
        return new self(VippConfig::homologacao(), $httpClient);
    }

    public function postarObjeto(Postagem $postagem, ?PerfilVipp $perfil = null): ResultadoPostagem
    {
        if ($this->config->validarAntesDeEnviar) {
            new ValidadorPostagem($this->config->exigirContrato)->garantir($postagem);
        }

        $payload = ['PerfilVipp' => ($perfil ?? $this->config->perfil)->toArray()] + $postagem->toArray();

        $resposta = $this->transport->post($this->config->urlPostarObjeto(), $payload);

        return $this->interpretar($resposta, $postagem->volume->etiqueta);
    }

    public function validar(Postagem $postagem): array
    {
        return new ValidadorPostagem($this->config->exigirContrato)->validar($postagem);
    }

    private function interpretar(RespostaHttp $resposta, ?string $etiquetaEnviada): ResultadoPostagem
    {
        $status = $resposta->status;
        $dados = $resposta->dados;
        $corpo = Mascara::corpo($resposta->corpo);

        if ($status === 401) {
            throw new AutenticacaoException(
                array_map(ErroVipp::fromArray(...), Leitor::lista($dados ?? [], 'ListaErros')),
                $corpo,
            );
        }

        // 200/201: postagem processada; 400 com ListaErros: postagem recusada na validação do ViPP.
        $ehResultado = ($status >= 200 && $status < 300)
            || ($status === 400 && $dados !== null && array_key_exists('ListaErros', $dados));

        if ($ehResultado) {
            if ($dados === null || !array_key_exists('StatusPostagem', $dados) && !array_key_exists('StPostagem', $dados)) {
                throw new RespostaInvalidaException("Resposta do ViPP sem StatusPostagem (HTTP {$status}).", $status, $corpo);
            }

            return ResultadoPostagem::fromResposta($dados, $status, $etiquetaEnviada);
        }

        throw new HttpException($status, self::mensagemHttp($status), $corpo);
    }

    private static function mensagemHttp(int $status): string
    {
        $motivo = match ($status) {
            400 => 'requisição inválida',
            404 => 'endpoint não encontrado; confira a URL base',
            423 => 'recurso bloqueado',
            429 => 'limite de requisições excedido',
            500 => 'erro interno do servidor do ViPP',
            504 => 'timeout do gateway do ViPP',
            507 => 'armazenamento insuficiente no ViPP',
            509 => 'limite de banda excedido',
            default => 'resposta inesperada',
        };

        return "ViPP respondeu HTTP {$status}: {$motivo}.";
    }
}
