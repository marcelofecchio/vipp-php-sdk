<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Config;

use MarceloFecchio\Vipp\Exceptions\ConfiguracaoException;

/**
 * Configuração do cliente. Homologação e produção usam a MESMA URL: o ambiente é definido pelas credenciais.
 */
final readonly class VippConfig
{
    public const string URL_PADRAO = 'https://vpsrv.visualset.com.br';

    public const string CAMINHO_POSTAR_OBJETO = '/api/v1/middleware/PostarObjeto';

    /** Não consta no manual; aparece na documentação interativa do servidor e foi explicado pelo suporte da VisualSet. */
    public const string CAMINHO_EXCLUIR_POSTAGEM = '/api/v1/conhecimento/DeleteConhecimento';

    /** Também fora do manual (documentação interativa do servidor). Consulta somente leitura. */
    public const string CAMINHO_SITUACAO_POSTAGEM = '/api/v1/conhecimento/GetSituacaoPostagem';

    /** Credenciais públicas de homologação, publicadas no PDF do ViPP. */
    public const array CREDENCIAIS_HOMOLOGACAO = ['usuario' => 'onbiws', 'token' => '112233', 'id_perfil' => '9363'];

    public string $baseUrl;

    /**
     * @param float $timeout segundos; usado quando o SDK cria o cliente HTTP (Guzzle). Clientes injetados usam a própria configuração.
     * @param bool $validarAntesDeEnviar roda o ValidadorPostagem antes de cada envio
     * @param bool $exigirContrato exige `ContratoEct` com `NrContrato` e `NrCartao` na validação local.
     *                             Recomendado em produção: perfis podem recusar a postagem sem eles (erro 91).
     */
    public function __construct(
        public PerfilVipp $perfil,
        string $baseUrl = self::URL_PADRAO,
        public float $timeout = 30.0,
        public bool $validarAntesDeEnviar = true,
        public bool $exigirContrato = false,
    ) {
        $baseUrl = rtrim(trim($baseUrl), '/');
        if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false || preg_match('#^https?://#i', $baseUrl) !== 1) {
            throw new ConfiguracaoException("URL base do ViPP inválida: \"{$baseUrl}\".");
        }

        if ($timeout <= 0) {
            throw new ConfiguracaoException('O timeout deve ser maior que zero.');
        }

        $this->baseUrl = $baseUrl;
    }

    /**
     * Configuração com as credenciais públicas de homologação (usuário `onbiws`, perfil 9363).
     */
    public static function homologacao(): self
    {
        $c = self::CREDENCIAIS_HOMOLOGACAO;

        return new self(new PerfilVipp($c['usuario'], $c['token'], $c['id_perfil']));
    }

    /**
     * Monta a partir de um array no formato de `config/vipp.php` (chaves `usuario`, `token`, `id_perfil`,
     * `base_url`, `timeout`, `validar_antes_de_enviar`, `exigir_contrato`).
     *
     * @param array<array-key, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $texto = static function (string $chave, string $env) use ($config): string {
            $valor = $config[$chave] ?? null;
            if (!is_scalar($valor) || trim((string) $valor) === '') {
                throw new ConfiguracaoException("Configuração do ViPP ausente: \"{$chave}\" (variável {$env}).");
            }

            return trim((string) $valor);
        };

        $baseUrl = $config['base_url'] ?? null;
        $timeout = $config['timeout'] ?? null;
        $validar = $config['validar_antes_de_enviar'] ?? true;
        $exigirContrato = $config['exigir_contrato'] ?? false;

        return new self(
            perfil: new PerfilVipp($texto('usuario', 'VIPP_USUARIO'), $texto('token', 'VIPP_TOKEN'), $texto('id_perfil', 'VIPP_ID_PERFIL')),
            baseUrl: is_string($baseUrl) && trim($baseUrl) !== '' ? $baseUrl : self::URL_PADRAO,
            timeout: is_numeric($timeout) ? (float) $timeout : 30.0,
            validarAntesDeEnviar: filter_var($validar, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
            exigirContrato: filter_var($exigirContrato, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
        );
    }

    public function urlPostarObjeto(): string
    {
        return $this->baseUrl.self::CAMINHO_POSTAR_OBJETO;
    }

    public function urlExcluirPostagem(): string
    {
        return $this->baseUrl.self::CAMINHO_EXCLUIR_POSTAGEM;
    }

    public function urlSituacaoPostagem(): string
    {
        return $this->baseUrl.self::CAMINHO_SITUACAO_POSTAGEM;
    }
}
