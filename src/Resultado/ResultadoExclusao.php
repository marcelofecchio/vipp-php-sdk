<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Resultado;

use MarceloFecchio\Vipp\Support\Leitor;

/**
 * Resultado de `excluirPostagem()`. O ViPP só exclui objetos pré-postados com status "aguardando liberação" (informação
 * do suporte da VisualSet). Etiqueta inexistente ou em outro status NÃO é erro: volta HTTP 200 com tudo zerado.
 * Confira sempre `excluida()`.
 */
final readonly class ResultadoExclusao
{
    /**
     * @param array<array-key, mixed> $resposta resposta bruta
     */
    public function __construct(
        public string $etiqueta,
        public int $totalConhecimentos,
        public int $conhecimentosExcluidos,
        public int $arquivosExcluidos,
        public int $objetoEPPN,
        public int $httpStatus,
        public array $resposta = [],
    ) {}

    /**
     * @param array<array-key, mixed> $resposta
     */
    public static function fromResposta(array $resposta, int $httpStatus, string $etiqueta): self
    {
        return new self(
            etiqueta: $etiqueta,
            totalConhecimentos: Leitor::inteiro($resposta, 'TotalConhecimentos') ?? 0,
            conhecimentosExcluidos: Leitor::inteiro($resposta, 'ConhecimentosExcluidos') ?? 0,
            arquivosExcluidos: Leitor::inteiro($resposta, 'ArquivosExcluidos') ?? 0,
            objetoEPPN: Leitor::inteiro($resposta, 'ObjetoEPPN') ?? 0,
            httpStatus: $httpStatus,
            resposta: $resposta,
        );
    }

    /**
     * True quando o ViPP excluiu ao menos um objeto. A etiqueta NÃO volta a ficar livre: para postar de novo é preciso
     * refazer a postagem (informação do suporte).
     */
    public function excluida(): bool
    {
        return $this->conhecimentosExcluidos > 0;
    }
}
