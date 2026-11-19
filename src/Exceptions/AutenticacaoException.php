<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Exceptions;

use MarceloFecchio\Vipp\Resultado\ErroVipp;

/**
 * Usuário/token inválidos (erro 1030), credencial ausente (1000/1010/1020) ou usuário sem acesso ao perfil (1002).
 * É um problema de configuração, não dos dados da postagem. Nos endpoints REST vem com HTTP 401; no SOAP de bloqueio,
 * o ViPP responde HTTP 200 com o erro em `ListaErros` (`statusCode` guarda o HTTP real).
 */
final class AutenticacaoException extends HttpException
{
    /** Códigos de `ListaErros` que indicam problema de credencial. */
    public const array CODIGOS_CREDENCIAL = [1000, 1002, 1010, 1020, 1030];

    /**
     * @param list<ErroVipp> $erros
     */
    public function __construct(public readonly array $erros, string $corpo = '', int $statusCode = 401)
    {
        $descricoes = array_map(static fn(ErroVipp $e): string => (string) $e, $erros);

        parent::__construct(
            $statusCode,
            'ViPP recusou as credenciais: '.($descricoes === [] ? 'não autorizado' : implode('; ', $descricoes)),
            $corpo,
        );
    }
}
