<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Exceptions;

use RuntimeException;

/**
 * Resposta HTTP de erro que não representa uma validação da postagem (404, 423, 429, 5xx etc.).
 */
class HttpException extends RuntimeException implements VippException
{
    public function __construct(
        public readonly int $statusCode,
        string $mensagem,
        public readonly string $corpo = '',
    ) {
        parent::__construct($mensagem, $statusCode);
    }

    /**
     * Erros que costumam passar sozinhos (limite de requisições, sobrecarga, timeout de gateway).
     * O SDK não repete automaticamente: a postagem não é idempotente e o reenvio pode duplicar o objeto.
     */
    public function ehTemporario(): bool
    {
        return in_array($this->statusCode, [429, 500, 502, 503, 504, 507, 509], true);
    }
}
