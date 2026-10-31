<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Postagem;

use DateTimeInterface;
use MarceloFecchio\Vipp\Support\Normaliza;
use MarceloFecchio\Vipp\ValueObject\Dinheiro;

/**
 * Nota fiscal do objeto. Todos os campos são opcionais.
 *
 * A data é enviada como `DD/MM/AAAA`: a homologação recusa o formato sem barras citado na Nota 8 do PDF
 * (erro 1500). Strings `AAAA-MM-DD` são convertidas.
 */
final readonly class NotaFiscal
{
    public ?string $numero;
    public ?string $serie;
    public ?string $data;
    public ?Dinheiro $valorTotal;
    public ?string $protocolo;
    public ?string $observacao;

    public function __construct(
        ?string $numero = null,
        ?string $serie = null,
        DateTimeInterface|string|null $data = null,
        Dinheiro|int|float|string|null $valorTotal = null,
        ?string $protocolo = null,
        ?string $observacao = null,
    ) {
        $this->numero = Normaliza::digitos($numero);
        $this->serie = Normaliza::texto($serie);
        $this->data = self::formatarData($data);
        $this->valorTotal = Dinheiro::deOpcional($valorTotal, 'NotasFiscais.VlrTotalNota');
        $this->protocolo = Normaliza::texto($protocolo);
        $this->observacao = Normaliza::texto($observacao);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'DtNotaFiscal' => $this->data,
            'SerieNotaFiscal' => $this->serie,
            'NrNotaFiscal' => $this->numero,
            'VlrTotalNota' => $this->valorTotal?->formatar(),
            'ProtocoloNotaFiscal' => $this->protocolo,
            'ObservacaoNotaFiscal' => $this->observacao,
        ], static fn(?string $v): bool => $v !== null);
    }

    private static function formatarData(DateTimeInterface|string|null $data): ?string
    {
        if ($data instanceof DateTimeInterface) {
            return $data->format('d/m/Y');
        }

        $data = Normaliza::texto($data);
        if ($data !== null && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $data, $m) === 1) {
            return "{$m[3]}/{$m[2]}/{$m[1]}";
        }

        return $data;
    }
}
