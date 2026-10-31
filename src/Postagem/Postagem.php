<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Postagem;

use MarceloFecchio\Vipp\Support\Normaliza;

/**
 * Uma postagem = um objeto (um volume).
 *
 * O payload do ViPP aceita uma lista `Volumes`, mas a homologação processa só o primeiro volume e descarta
 * os demais sem avisar. Para não perder dados, o SDK envia exatamente um volume por requisição.
 */
final readonly class Postagem
{
    /** Código financeiro do serviço nos Correios (ex.: SEDEX `03220`, PAC `03298`). Opcional se o perfil já definir. */
    public ?string $servicoEct;

    /** @var list<NotaFiscal> */
    public array $notasFiscais;

    /**
     * @param array<array-key, NotaFiscal> $notasFiscais
     */
    public function __construct(
        public Destinatario $destinatario,
        public Volume $volume,
        ?string $servicoEct = null,
        array $notasFiscais = [],
        public ?ContratoEct $contratoEct = null,
    ) {
        $this->servicoEct = Normaliza::digitos($servicoEct);
        $this->notasFiscais = array_values($notasFiscais);
    }

    /**
     * Payload sem o bloco `PerfilVipp` (adicionado pelo cliente).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [];

        $contrato = $this->contratoEct?->toArray() ?? [];
        if ($contrato !== []) {
            $payload['ContratoEct'] = $contrato;
        }

        $payload['Destinatario'] = $this->destinatario->toArray();

        if ($this->servicoEct !== null) {
            $payload['Servico'] = ['ServicoECT' => $this->servicoEct];
        }

        if ($this->notasFiscais !== []) {
            $payload['NotasFiscais'] = array_map(static fn(NotaFiscal $nf): array => $nf->toArray(), $this->notasFiscais);
        }

        $payload['Volumes'] = [$this->volume->toArray()];

        return $payload;
    }
}
