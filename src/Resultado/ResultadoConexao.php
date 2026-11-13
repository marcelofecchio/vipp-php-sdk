<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Resultado;

/**
 * Resultado de `verificarCredenciais()`. Credencial recusada NÃO é exceção aqui: é o que o método existe para descobrir.
 *
 * Só usuário e token são conferidos: a consulta usada não recebe o `IdPerfil`, então um perfil errado só aparece
 * na primeira postagem (erro 1002).
 */
final readonly class ResultadoConexao
{
    /**
     * @param list<ErroVipp> $erros
     */
    public function __construct(
        public bool $autenticado,
        public array $erros,
        public int $httpStatus,
    ) {}

    /**
     * Mensagens prontas para exibir ao usuário.
     *
     * @return list<string>
     */
    public function mensagensDeErro(): array
    {
        return array_map(static fn(ErroVipp $e): string => (string) $e, $this->erros);
    }
}
