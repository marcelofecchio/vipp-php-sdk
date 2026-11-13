<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Support;

use MarceloFecchio\Vipp\Exceptions\ValidacaoException;
use MarceloFecchio\Vipp\ValueObject\Dinheiro;

/**
 * Leitura tolerante do JSON do ViPP: números às vezes vêm como string (`"1600"`) e às vezes como int (`200`),
 * e valores monetários vêm com vírgula (`"15,48"`) ou ponto (`"0.00"`).
 *
 * @internal
 */
final class Leitor
{
    /**
     * @param array<array-key, mixed> $dados
     */
    public static function texto(array $dados, string $chave): ?string
    {
        $valor = $dados[$chave] ?? null;
        if (is_string($valor)) {
            $valor = trim($valor);

            return $valor === '' ? null : $valor;
        }

        return is_int($valor) || is_float($valor) ? (string) $valor : null;
    }

    /**
     * @param array<array-key, mixed> $dados
     */
    public static function inteiro(array $dados, string $chave): ?int
    {
        $valor = $dados[$chave] ?? null;
        if (is_int($valor)) {
            return $valor;
        }

        return is_string($valor) && preg_match('/^-?\d+$/', trim($valor)) === 1 ? (int) $valor : null;
    }

    /**
     * Flags `"1"`/`"0"` do ViPP.
     *
     * @param array<array-key, mixed> $dados
     */
    public static function flag(array $dados, string $chave): ?bool
    {
        $valor = self::inteiro($dados, $chave);

        return $valor === null ? null : $valor === 1;
    }

    /**
     * @param array<array-key, mixed> $dados
     */
    public static function dinheiro(array $dados, string $chave): ?Dinheiro
    {
        $valor = self::texto($dados, $chave);
        if ($valor === null) {
            return null;
        }

        try {
            return Dinheiro::reais($valor, $chave);
        } catch (ValidacaoException) {
            return null;
        }
    }

    /**
     * @param array<array-key, mixed> $dados
     *
     * @return array<array-key, mixed>
     */
    public static function objeto(array $dados, string $chave): array
    {
        $valor = $dados[$chave] ?? null;

        return is_array($valor) ? $valor : [];
    }

    /**
     * Erros do `DeleteConhecimento`: vêm como `{"Message": "[{...}]"}`, com a lista de erros em JSON dentro de uma string.
     *
     * @param array<array-key, mixed> $dados
     *
     * @return list<array<array-key, mixed>>
     */
    public static function listaDeMessage(array $dados): array
    {
        $mensagem = $dados['Message'] ?? null;
        if (!is_string($mensagem)) {
            return [];
        }

        $lista = json_decode($mensagem, true);

        return is_array($lista) ? array_values(array_filter($lista, is_array(...))) : [];
    }

    /**
     * @param array<array-key, mixed> $dados
     *
     * @return list<array<array-key, mixed>>
     */
    public static function lista(array $dados, string $chave): array
    {
        $valor = $dados[$chave] ?? null;
        if (!is_array($valor)) {
            return [];
        }

        return array_values(array_filter($valor, is_array(...)));
    }
}
