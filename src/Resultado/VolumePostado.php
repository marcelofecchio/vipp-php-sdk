<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Resultado;

use MarceloFecchio\Vipp\Support\Leitor;
use MarceloFecchio\Vipp\ValueObject\Dinheiro;

/**
 * Volume devolvido pelo ViPP. Em postagem válida traz a etiqueta, os valores e o prazo.
 * Peso e medidas abaixo do mínimo dos Correios voltam com o mínimo aplicado.
 */
final readonly class VolumePostado
{
    /**
     * @param array<array-key, mixed> $dados resposta bruta do volume
     */
    public function __construct(
        public ?string $etiqueta,
        public ?int $idConhecimento,
        public ?Dinheiro $valorTarifa,
        public ?Dinheiro $valorAdicionais,
        public ?Dinheiro $valorPostagem,
        public ?Dinheiro $valorDeclarado,
        public ?bool $entregaSabado,
        public ?bool $entregaDomiciliar,
        public ?int $diasUteisPrazo,
        public ?int $peso,
        public ?int $altura,
        public ?int $largura,
        public ?int $comprimento,
        public ?string $adicionais,
        public ?string $codigoBarraVolume,
        public ?int $categoria,
        public array $dados = [],
    ) {}

    /**
     * @param array<array-key, mixed> $dados
     */
    public static function fromArray(array $dados): self
    {
        return new self(
            etiqueta: Leitor::texto($dados, 'Etiqueta'),
            idConhecimento: Leitor::inteiro($dados, 'IdConhecimento'),
            valorTarifa: Leitor::dinheiro($dados, 'ValorTarifa'),
            valorAdicionais: Leitor::dinheiro($dados, 'ValorAdicionais'),
            valorPostagem: Leitor::dinheiro($dados, 'ValorPostagem'),
            valorDeclarado: Leitor::dinheiro($dados, 'ValorDeclarado'),
            entregaSabado: Leitor::flag($dados, 'StEntregaSabado'),
            entregaDomiciliar: Leitor::flag($dados, 'StEntregaDomiciliar'),
            diasUteisPrazo: Leitor::inteiro($dados, 'DiasUteisPrazo'),
            peso: Leitor::inteiro($dados, 'Peso'),
            altura: Leitor::inteiro($dados, 'Altura'),
            largura: Leitor::inteiro($dados, 'Largura'),
            comprimento: Leitor::inteiro($dados, 'Comprimento'),
            adicionais: Leitor::texto($dados, 'AdicionaisVolume'),
            codigoBarraVolume: Leitor::texto($dados, 'CodigoBarraVolume'),
            categoria: Leitor::inteiro($dados, 'Categoria'),
            dados: $dados,
        );
    }
}
