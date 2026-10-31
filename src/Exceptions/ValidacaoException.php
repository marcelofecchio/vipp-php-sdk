<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Exceptions;

use InvalidArgumentException;
use MarceloFecchio\Vipp\Validation\Violacao;

/**
 * Dados da postagem reprovados pela validação local, antes de qualquer chamada HTTP.
 */
final class ValidacaoException extends InvalidArgumentException implements VippException
{
    /**
     * @param list<Violacao> $violacoes
     */
    public function __construct(public readonly array $violacoes)
    {
        $linhas = array_map(static fn(Violacao $v): string => (string) $v, $violacoes);

        parent::__construct("Postagem inválida:\n- ".implode("\n- ", $linhas));
    }

    public static function campo(string $campo, string $mensagem): self
    {
        return new self([new Violacao($campo, $mensagem)]);
    }
}
