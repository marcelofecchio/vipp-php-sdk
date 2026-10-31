<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Exceptions;

use RuntimeException;

/**
 * O ViPP respondeu algo que não é o JSON esperado.
 */
final class RespostaInvalidaException extends RuntimeException implements VippException
{
    public function __construct(
        string $mensagem,
        public readonly int $statusCode = 0,
        public readonly string $corpo = '',
    ) {
        parent::__construct($mensagem, $statusCode);
    }
}
