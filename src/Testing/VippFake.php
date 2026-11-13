<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Testing;

use Closure;
use MarceloFecchio\Vipp\Config\PerfilVipp;
use MarceloFecchio\Vipp\Enum\BuscarPor;
use MarceloFecchio\Vipp\Exceptions\ValidacaoException;
use MarceloFecchio\Vipp\Postagem\Postagem;
use MarceloFecchio\Vipp\Resultado\ErroVipp;
use MarceloFecchio\Vipp\Resultado\ResultadoConexao;
use MarceloFecchio\Vipp\Resultado\ResultadoExclusao;
use MarceloFecchio\Vipp\Resultado\ResultadoPostagem;
use MarceloFecchio\Vipp\Resultado\SituacaoPostagem;
use MarceloFecchio\Vipp\Support\Normaliza;
use MarceloFecchio\Vipp\Validation\Etiqueta;
use MarceloFecchio\Vipp\Validation\ValidadorPostagem;
use MarceloFecchio\Vipp\VippClientInterface;
use Throwable;

/**
 * Dublê do cliente para os testes de quem usa o SDK: não faz HTTP, registra as postagens e devolve respostas
 * programadas (ou uma postagem válida por padrão). Aplica a mesma validação local do cliente real.
 *
 * No Laravel: `$fake = Vipp::fake();`.
 */
final class VippFake implements VippClientInterface
{
    /** @var list<Postagem> */
    private array $postagens = [];

    /** @var list<ResultadoPostagem|Throwable|Closure(Postagem): ResultadoPostagem> */
    private array $fila = [];

    /** @var list<string> */
    private array $exclusoes = [];

    /** @var list<ResultadoExclusao|Throwable> */
    private array $filaExclusao = [];

    /** @var list<ResultadoConexao|Throwable> */
    private array $filaConexao = [];

    /** @var list<list<SituacaoPostagem>|Throwable> */
    private array $filaSituacao = [];

    /** @var list<string> */
    private array $consultas = [];

    private int $sequencia = 0;

    /**
     * @param bool $exigirContrato mesmo efeito de `VippConfig::$exigirContrato`
     */
    public function __construct(
        private readonly bool $validar = true,
        private readonly bool $exigirContrato = false,
    ) {}

    /**
     * Programa as próximas respostas, em ordem. Depois que a fila acaba, volta ao padrão (postagem válida).
     *
     * @param ResultadoPostagem|Throwable|Closure(Postagem): ResultadoPostagem ...$respostas
     */
    public function responderCom(ResultadoPostagem|Throwable|Closure ...$respostas): self
    {
        array_push($this->fila, ...$respostas);

        return $this;
    }

    public function postarObjeto(Postagem $postagem, ?PerfilVipp $perfil = null): ResultadoPostagem
    {
        if ($this->validar) {
            new ValidadorPostagem($this->exigirContrato)->garantir($postagem);
        }

        $this->postagens[] = $postagem;

        $resposta = array_shift($this->fila);

        return match (true) {
            $resposta instanceof Throwable => throw $resposta,
            $resposta instanceof Closure => $resposta($postagem),
            $resposta instanceof ResultadoPostagem => $resposta,
            default => self::resultadoValido($postagem->volume->etiqueta ?? $this->proximaEtiqueta(), $postagem->volume->etiqueta),
        };
    }

    /**
     * Programa as próximas respostas de `excluirPostagem()`. Sem programação, a exclusão tem sucesso.
     */
    public function responderExclusaoCom(ResultadoExclusao|Throwable ...$respostas): self
    {
        array_push($this->filaExclusao, ...$respostas);

        return $this;
    }

    public function excluirPostagem(string $etiqueta, ?PerfilVipp $perfil = null): ResultadoExclusao
    {
        $etiqueta = (string) Normaliza::codigo($etiqueta);

        if ($this->validar && ($erro = Etiqueta::erro($etiqueta)) !== null) {
            throw ValidacaoException::campo('Etiqueta', $erro);
        }

        $this->exclusoes[] = $etiqueta;

        $resposta = array_shift($this->filaExclusao);

        return match (true) {
            $resposta instanceof Throwable => throw $resposta,
            $resposta instanceof ResultadoExclusao => $resposta,
            default => self::resultadoExclusao($etiqueta),
        };
    }

    /**
     * Etiquetas recebidas por `excluirPostagem()`, em ordem.
     *
     * @return list<string>
     */
    public function exclusoes(): array
    {
        return $this->exclusoes;
    }

    public static function resultadoExclusao(string $etiqueta = 'SQ000000005BR', bool $excluida = true): ResultadoExclusao
    {
        return ResultadoExclusao::fromResposta([
            'TotalConhecimentos' => $excluida ? 1 : 0,
            'ConhecimentosExcluidos' => $excluida ? 1 : 0,
            'ArquivosExcluidos' => 0,
            'ObjetoEPPN' => 0,
        ], 200, $etiqueta);
    }

    /**
     * Programa as próximas respostas de `verificarCredenciais()`. Sem programação, as credenciais são aceitas.
     */
    public function responderConexaoCom(ResultadoConexao|Throwable ...$respostas): self
    {
        array_push($this->filaConexao, ...$respostas);

        return $this;
    }

    public function verificarCredenciais(?PerfilVipp $perfil = null): ResultadoConexao
    {
        $resposta = array_shift($this->filaConexao);

        return match (true) {
            $resposta instanceof Throwable => throw $resposta,
            $resposta instanceof ResultadoConexao => $resposta,
            default => new ResultadoConexao(true, [], 204),
        };
    }

    /**
     * Programa as próximas respostas de `situacaoPostagem()`: uma lista de `SituacaoPostagem` ou uma exceção.
     * Sem programação, devolve lista vazia (nada encontrado).
     *
     * @param list<SituacaoPostagem>|Throwable ...$respostas
     */
    public function responderSituacaoCom(array|Throwable ...$respostas): self
    {
        array_push($this->filaSituacao, ...$respostas);

        return $this;
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

        if ($this->validar && $buscarPor === BuscarPor::Etiqueta && ($erro = Etiqueta::erro($valor)) !== null) {
            throw ValidacaoException::campo('Etiqueta', $erro);
        }

        $this->consultas[] = $valor;

        $resposta = array_shift($this->filaSituacao);

        return $resposta instanceof Throwable ? throw $resposta : ($resposta ?? []);
    }

    /**
     * Valores recebidos por `situacaoPostagem()`, em ordem.
     *
     * @return list<string>
     */
    public function consultas(): array
    {
        return $this->consultas;
    }

    public static function situacao(string $etiqueta = 'SQ000000005BR', ?string $referencia = null): SituacaoPostagem
    {
        return SituacaoPostagem::fromArray([
            'Etiqueta' => $etiqueta,
            'ObservacaoUm' => $referencia,
            'DataDeEntradaNoVipp' => '2026-01-01T10:00:00',
            'ValorDaPostagem' => 15.48,
            'PrazoDeEntrega' => 3,
            'PrazoDeEntregaEstaContando' => 'S',
            'EntegaNoDomicilio' => 'S',
            'EntegaAosSabados' => 'N',
            'CodigoFinanceiroECT' => 3298,
            'DescricaoFinanceiroECT' => '03298 - PAC',
            'EtiquetaImpressa' => 'N',
            'ARImpresso' => 'N',
            'DeclaracaoDeConteudoImpressa' => 'N',
        ]);
    }

    public static function conexaoRecusada(): ResultadoConexao
    {
        return new ResultadoConexao(false, [new ErroVipp(1030, 'Login', 'Usuário ou Senha Inválidos!', 2, 'Validacao')], 401);
    }

    public function validar(Postagem $postagem): array
    {
        return new ValidadorPostagem($this->exigirContrato)->validar($postagem);
    }

    /**
     * @return list<Postagem>
     */
    public function postagens(): array
    {
        return $this->postagens;
    }

    public function ultimaPostagem(): ?Postagem
    {
        return $this->postagens === [] ? null : $this->postagens[array_key_last($this->postagens)];
    }

    public static function resultadoValido(string $etiqueta = 'SQ000000005BR', ?string $etiquetaEnviada = null): ResultadoPostagem
    {
        return ResultadoPostagem::fromResposta([
            'Servico' => ['ServicoECT' => '03298'],
            'Volumes' => [[
                'Etiqueta' => $etiqueta,
                'ValorTarifa' => '15,48',
                'ValorAdicionais' => '0,00',
                'ValorPostagem' => '15,48',
                'StEntregaSabado' => '0',
                'StEntregaDomiciliar' => '1',
                'DiasUteisPrazo' => '3',
            ]],
            'StPostagem' => 1,
            'IdStatusPostagem' => 1,
            'StatusPostagem' => 'Valida',
        ], 200, $etiquetaEnviada);
    }

    /**
     * @param array<int, string> $erros código => descrição (ex.: `[1330 => 'É Obrigatório Informar o CEP do Destinatário!']`)
     */
    public static function resultadoInvalido(array $erros = [1330 => 'É Obrigatório Informar o CEP do Destinatário!']): ResultadoPostagem
    {
        $lista = [];
        foreach ($erros as $codigo => $descricao) {
            $lista[] = ['TipoErro' => 2, 'DescricaoTipoErro' => 'Validacao', 'CodigoErro' => $codigo, 'Descricao' => $descricao];
        }

        return ResultadoPostagem::fromResposta([
            'ListaErros' => $lista,
            'StPostagem' => 0,
            'IdStatusPostagem' => 0,
            'StatusPostagem' => 'Invalida',
        ], 400);
    }

    private function proximaEtiqueta(): string
    {
        $numero = sprintf('%08d', ++$this->sequencia);

        return 'SQ'.$numero.Etiqueta::digitoVerificador($numero).'BR';
    }
}
