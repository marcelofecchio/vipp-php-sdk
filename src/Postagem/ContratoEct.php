<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Postagem;

use MarceloFecchio\Vipp\Support\Normaliza;

/**
 * Contrato dos Correios. Só é necessário quando o Perfil de Importação não o tiver configurado (Nota 1).
 */
final readonly class ContratoEct
{
    public ?string $nrContrato;
    public ?string $codigoAdministrativo;
    public ?string $nrCartao;

    public function __construct(
        ?string $nrContrato = null,
        ?string $codigoAdministrativo = null,
        ?string $nrCartao = null,
    ) {
        $this->nrContrato = Normaliza::digitos($nrContrato);
        $this->codigoAdministrativo = Normaliza::digitos($codigoAdministrativo);
        $this->nrCartao = Normaliza::digitos($nrCartao);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'NrContrato' => $this->nrContrato,
            'CodigoAdministrativo' => $this->codigoAdministrativo,
            'NrCartao' => $this->nrCartao,
        ], static fn(?string $v): bool => $v !== null);
    }
}
