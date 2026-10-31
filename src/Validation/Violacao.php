<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Validation;

use Stringable;

/**
 * Uma regra violada. `campo` usa a notação do payload ViPP (ex.: `Volumes[0].DeclaracaoConteudo.ItemConteudo[1].Valor`).
 */
final readonly class Violacao implements Stringable
{
    public function __construct(
        public string $campo,
        public string $mensagem,
    ) {}

    public function __toString(): string
    {
        return "{$this->campo}: {$this->mensagem}";
    }
}
