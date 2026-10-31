<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Exceptions;

use MarceloFecchio\Vipp\Resultado\ErroVipp;

/**
 * HTTP 401: usuário/token inválidos (erro 1030) ou usuário sem acesso ao perfil (erro 1002).
 * É um problema de configuração, não dos dados da postagem.
 */
final class AutenticacaoException extends HttpException
{
    /**
     * @param list<ErroVipp> $erros
     */
    public function __construct(public readonly array $erros, string $corpo = '')
    {
        $descricoes = array_map(static fn(ErroVipp $e): string => (string) $e, $erros);

        parent::__construct(
            401,
            'ViPP recusou as credenciais: '.($descricoes === [] ? 'não autorizado' : implode('; ', $descricoes)),
            $corpo,
        );
    }
}
