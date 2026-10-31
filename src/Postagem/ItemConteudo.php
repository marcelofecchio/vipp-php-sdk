<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Postagem;

use MarceloFecchio\Vipp\Support\Normaliza;
use MarceloFecchio\Vipp\ValueObject\Dinheiro;

/**
 * Item da Declaração de Conteúdo. `valor` é o valor UNITÁRIO: a SEFAZ calcula quantidade × valor (Nota 17).
 */
final readonly class ItemConteudo
{
    public ?string $descricao;
    public Dinheiro $valor;

    public function __construct(
        string $descricao,
        public int $quantidade,
        Dinheiro|int|float|string $valor,
    ) {
        $this->descricao = Normaliza::texto($descricao);
        $this->valor = Dinheiro::de($valor, 'ItemConteudo.Valor');
    }

    public function total(): Dinheiro
    {
        return $this->valor->multiplicar($this->quantidade);
    }

    /**
     * @return array{DescricaoConteudo: string, Quantidade: int, Valor: string}
     */
    public function toArray(): array
    {
        return [
            'DescricaoConteudo' => $this->descricao ?? '',
            'Quantidade' => $this->quantidade,
            'Valor' => $this->valor->formatar(),
        ];
    }
}
