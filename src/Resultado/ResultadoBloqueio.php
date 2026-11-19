<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Resultado;

/**
 * Resultado de `bloquearPostagem()` e `liberarPostagem()` (SOAP `BloquearDownloadConhecimento` /
 * `LiberarDownloadConhecimento`).
 *
 * Atenção: `confirmado()` só diz que o ViPP aceitou o pedido. Na homologação ele confirma até etiqueta inexistente
 * ("Objeto Bloqueado"), e a consulta de situação não mostra se a postagem está bloqueada. Para saber se o bloqueio
 * liberou a exclusão, tente `excluirPostagem()` e confira `excluida()`.
 */
final readonly class ResultadoBloqueio
{
    public const string BLOQUEIO = 'BloquearDownloadConhecimento';

    public const string LIBERACAO = 'LiberarDownloadConhecimento';

    /**
     * @param string $operacao `self::BLOQUEIO` ou `self::LIBERACAO`
     * @param int|null $status `StBloqueado` ou `StLiberado` devolvido (1 = aceito)
     * @param string|null $descricao ex.: "Objeto Bloqueado"
     * @param list<ErroVipp> $erros
     * @param array<string, mixed> $dados resultado lido do XML, sem o `PerfilVipp`
     * @param string $xml resposta bruta, com o token mascarado
     */
    public function __construct(
        public string $etiqueta,
        public string $operacao,
        public ?int $status,
        public ?string $descricao,
        public array $erros,
        public int $httpStatus,
        public array $dados = [],
        public string $xml = '',
    ) {}

    /**
     * True quando o ViPP aceitou o pedido (status 1 e nenhum erro).
     */
    public function confirmado(): bool
    {
        return $this->status === 1 && $this->erros === [];
    }

    /**
     * @return list<string>
     */
    public function mensagensDeErro(): array
    {
        return array_map(static fn(ErroVipp $e): string => (string) $e, $this->erros);
    }
}
