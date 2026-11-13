<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp;

use Http\Discovery\Psr17FactoryDiscovery;
use MarceloFecchio\Vipp\Config\PerfilVipp;
use MarceloFecchio\Vipp\Config\VippConfig;
use MarceloFecchio\Vipp\Enum\BuscarPor;
use MarceloFecchio\Vipp\Exceptions\AutenticacaoException;
use MarceloFecchio\Vipp\Exceptions\HttpException;
use MarceloFecchio\Vipp\Exceptions\RespostaInvalidaException;
use MarceloFecchio\Vipp\Exceptions\ValidacaoException;
use MarceloFecchio\Vipp\Http\HttpClientFactory;
use MarceloFecchio\Vipp\Http\Mascara;
use MarceloFecchio\Vipp\Http\RespostaHttp;
use MarceloFecchio\Vipp\Http\Transport;
use MarceloFecchio\Vipp\Postagem\Postagem;
use MarceloFecchio\Vipp\Resultado\ErroVipp;
use MarceloFecchio\Vipp\Resultado\ResultadoConexao;
use MarceloFecchio\Vipp\Resultado\ResultadoExclusao;
use MarceloFecchio\Vipp\Resultado\ResultadoPostagem;
use MarceloFecchio\Vipp\Resultado\SituacaoPostagem;
use MarceloFecchio\Vipp\Support\Leitor;
use MarceloFecchio\Vipp\Support\Normaliza;
use MarceloFecchio\Vipp\Validation\Etiqueta;
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
    /** Etiqueta que não existe: a consulta com ela não encontra nada e só confere usuário e senha. */
    private const string ETIQUETA_SONDA = 'SQ000000005BR';

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

    public function excluirPostagem(string $etiqueta, ?PerfilVipp $perfil = null): ResultadoExclusao
    {
        $etiqueta = (string) Normaliza::codigo($etiqueta);

        if ($this->config->validarAntesDeEnviar && ($erro = Etiqueta::erro($etiqueta)) !== null) {
            throw ValidacaoException::campo('Etiqueta', $erro);
        }

        $perfil ??= $this->config->perfil;

        $resposta = $this->transport->delete($this->config->urlExcluirPostagem(), [
            'Usuario' => $perfil->usuario,
            'Token' => $perfil->token,
            'Etiqueta' => $etiqueta,
        ]);

        $corpo = Mascara::corpo($resposta->corpo);

        if ($resposta->status === 401) {
            throw new AutenticacaoException(self::errosDeAutenticacao($resposta->dados), $corpo);
        }

        if ($resposta->status >= 200 && $resposta->status < 300) {
            if ($resposta->dados === null || !array_key_exists('ConhecimentosExcluidos', $resposta->dados)) {
                throw new RespostaInvalidaException("Resposta do ViPP sem ConhecimentosExcluidos (HTTP {$resposta->status}).", $resposta->status, $corpo);
            }

            return ResultadoExclusao::fromResposta($resposta->dados, $resposta->status, $etiqueta);
        }

        throw new HttpException($resposta->status, self::mensagemHttp($resposta->status), $corpo);
    }

    public function verificarCredenciais(?PerfilVipp $perfil = null): ResultadoConexao
    {
        $resposta = $this->consultarSituacao(self::ETIQUETA_SONDA, BuscarPor::Etiqueta, false, $perfil);

        if ($resposta->status === 401) {
            return new ResultadoConexao(false, self::errosDeAutenticacao($resposta->dados), 401);
        }

        if ($resposta->status >= 200 && $resposta->status < 300) {
            return new ResultadoConexao(true, [], $resposta->status);
        }

        throw new HttpException($resposta->status, self::mensagemHttp($resposta->status), Mascara::corpo($resposta->corpo));
    }

    public function situacaoPostagem(
        string $valor,
        BuscarPor $buscarPor = BuscarPor::Etiqueta,
        bool $completo = false,
        ?PerfilVipp $perfil = null,
    ): array {
        $valor = $buscarPor === BuscarPor::Etiqueta ? (string) Normaliza::codigo($valor) : trim($valor);

        if ($valor === '') {
            throw ValidacaoException::campo('ListaObjeto', 'informe a etiqueta ou a referência a consultar.');
        }

        if ($buscarPor === BuscarPor::Etiqueta && $this->config->validarAntesDeEnviar && ($erro = Etiqueta::erro($valor)) !== null) {
            throw ValidacaoException::campo('Etiqueta', $erro);
        }

        $resposta = $this->consultarSituacao($valor, $buscarPor, $completo, $perfil);
        $corpo = Mascara::corpo($resposta->corpo);

        if ($resposta->status === 401) {
            throw new AutenticacaoException(self::errosDeAutenticacao($resposta->dados), $corpo);
        }

        // 204: nenhum objeto encontrado (inclusive etiqueta inexistente).
        if ($resposta->status === 204) {
            return [];
        }

        if ($resposta->status >= 200 && $resposta->status < 300) {
            if ($resposta->dados === null || !array_key_exists('SituacaoPostagem', $resposta->dados)) {
                throw new RespostaInvalidaException("Resposta do ViPP sem SituacaoPostagem (HTTP {$resposta->status}).", $resposta->status, $corpo);
            }

            return array_map(SituacaoPostagem::fromArray(...), Leitor::lista($resposta->dados, 'SituacaoPostagem'));
        }

        throw new HttpException($resposta->status, self::mensagemHttp($resposta->status), $corpo);
    }

    private function consultarSituacao(string $valor, BuscarPor $buscarPor, bool $completo, ?PerfilVipp $perfil): RespostaHttp
    {
        $perfil ??= $this->config->perfil;

        // O endpoint só recebe usuário e senha (o token do PerfilVipp) e não recebe o IdPerfil.
        return $this->transport->get($this->config->urlSituacaoPostagem(), [
            'Usuario' => $perfil->usuario,
            'Senha' => $perfil->token,
            'StDadosCompletos' => $completo ? '1' : '0',
            'BuscarPor' => $buscarPor->value,
            'ListaObjeto' => $valor,
        ]);
    }

    /**
     * Os erros do 401 vêm em `ListaErros` (PostarObjeto, GetSituacaoPostagem) ou em `{"Message": "[...]"}` (DeleteConhecimento).
     *
     * @param array<array-key, mixed>|null $dados
     *
     * @return list<ErroVipp>
     */
    private static function errosDeAutenticacao(?array $dados): array
    {
        $dados ??= [];
        $erros = Leitor::lista($dados, 'ListaErros');

        return array_map(ErroVipp::fromArray(...), $erros === [] ? Leitor::listaDeMessage($dados) : $erros);
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
            throw new AutenticacaoException(self::errosDeAutenticacao($dados), $corpo);
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
