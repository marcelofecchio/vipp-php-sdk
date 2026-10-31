<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Testing;

use Closure;
use MarceloFecchio\Vipp\Config\PerfilVipp;
use MarceloFecchio\Vipp\Postagem\Postagem;
use MarceloFecchio\Vipp\Resultado\ResultadoPostagem;
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

    private int $sequencia = 0;

    public function __construct(private readonly bool $validar = true) {}

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
            new ValidadorPostagem()->garantir($postagem);
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

    public function validar(Postagem $postagem): array
    {
        return new ValidadorPostagem()->validar($postagem);
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
