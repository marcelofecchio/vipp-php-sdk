<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Postagem;

use MarceloFecchio\Vipp\Support\Normaliza;
use MarceloFecchio\Vipp\ValueObject\Dinheiro;

/**
 * Declaração de Conteúdo, obrigatória em toda postagem (Nota 17). Sem nota fiscal, ela vira a DC-e da SEFAZ.
 */
final readonly class DeclaracaoConteudo
{
    public ?string $documentoRemetente;
    public ?string $documentoDestinatario;

    /** @var list<ItemConteudo> */
    public array $itens;

    /**
     * @param array<array-key, ItemConteudo> $itens
     */
    public function __construct(
        string $documentoRemetente,
        string $documentoDestinatario,
        array $itens,
    ) {
        $this->documentoRemetente = Normaliza::documento($documentoRemetente);
        $this->documentoDestinatario = Normaliza::documento($documentoDestinatario);
        $this->itens = array_values($itens);
    }

    public function total(): Dinheiro
    {
        return array_reduce(
            $this->itens,
            static fn(Dinheiro $soma, ItemConteudo $item): Dinheiro => $soma->somar($item->total()),
            Dinheiro::centavos(0),
        );
    }

    /**
     * @return array{ItemConteudo: list<array<string, int|string>>, DocumentoRemetente: string, DocumentoDestinatario: string}
     */
    public function toArray(): array
    {
        return [
            'ItemConteudo' => array_map(static fn(ItemConteudo $i): array => $i->toArray(), $this->itens),
            'DocumentoRemetente' => $this->documentoRemetente ?? '',
            'DocumentoDestinatario' => $this->documentoDestinatario ?? '',
        ];
    }
}
