<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Postagem;

use MarceloFecchio\Vipp\Enum\ServicoAdicional;
use MarceloFecchio\Vipp\Support\Normaliza;
use MarceloFecchio\Vipp\ValueObject\Dinheiro;

/**
 * O objeto postal. Peso em GRAMAS e dimensões em CENTÍMETROS, sempre inteiros (Nota 11): a homologação
 * trunca `"1,6"` para 1 grama, por isso o SDK só aceita `int`.
 *
 * `etiqueta` é o número de registro já gerado nos Correios (Nota 16). Sem ela, o ViPP gera um número.
 */
final readonly class Volume
{
    public ?string $etiqueta;
    public ?string $codigoBarraVolume;
    public ?string $codigoBarraCliente;
    public ?string $conteudo;
    public ?Dinheiro $valorDeclarado;
    public ?Dinheiro $valorACobrar;

    /** @var list<string> */
    public array $adicionais;

    public ?string $contaLote;
    public ?string $chaveRoteamento;
    public ?string $observacaoVisual;
    public ?string $observacaoQuatro;
    public ?string $observacaoCinco;

    /**
     * @param list<ServicoAdicional|string> $adicionais
     */
    public function __construct(
        public DeclaracaoConteudo $declaracaoConteudo,
        public ?int $peso = null,
        public ?int $altura = null,
        public ?int $largura = null,
        public ?int $comprimento = null,
        ?string $etiqueta = null,
        ?string $codigoBarraVolume = null,
        ?string $codigoBarraCliente = null,
        ?string $conteudo = null,
        Dinheiro|int|float|string|null $valorDeclarado = null,
        Dinheiro|int|float|string|null $valorACobrar = null,
        array $adicionais = [],
        ?string $contaLote = null,
        ?string $chaveRoteamento = null,
        ?string $observacaoVisual = null,
        ?string $observacaoQuatro = null,
        ?string $observacaoCinco = null,
        public ?int $posicaoVolume = null,
    ) {
        $this->etiqueta = Normaliza::codigo($etiqueta);
        $this->codigoBarraVolume = Normaliza::texto($codigoBarraVolume);
        $this->codigoBarraCliente = Normaliza::digitos($codigoBarraCliente);
        $this->conteudo = Normaliza::texto($conteudo);
        $this->valorDeclarado = Dinheiro::deOpcional($valorDeclarado, 'Volumes.ValorDeclarado');
        $this->valorACobrar = Dinheiro::deOpcional($valorACobrar, 'Volumes.VlrACobrar');
        $this->adicionais = array_values(array_unique(array_map(
            static fn(ServicoAdicional|string $a): string => $a instanceof ServicoAdicional ? $a->value : (string) Normaliza::codigo($a),
            $adicionais,
        )));
        $this->contaLote = Normaliza::texto($contaLote);
        $this->chaveRoteamento = Normaliza::texto($chaveRoteamento);
        $this->observacaoVisual = Normaliza::texto($observacaoVisual);
        $this->observacaoQuatro = Normaliza::texto($observacaoQuatro);
        $this->observacaoCinco = Normaliza::texto($observacaoCinco);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $opcionais = array_filter([
            'Peso' => $this->peso === null ? null : (string) $this->peso,
            'Altura' => $this->altura === null ? null : (string) $this->altura,
            'Largura' => $this->largura === null ? null : (string) $this->largura,
            'Comprimento' => $this->comprimento === null ? null : (string) $this->comprimento,
            'ContaLote' => $this->contaLote,
            'ChaveRoteamento' => $this->chaveRoteamento,
            'CodigoBarraVolume' => $this->codigoBarraVolume,
            'CodigoBarraCliente' => $this->codigoBarraCliente,
            'ObservacaoVisual' => $this->observacaoVisual,
            'ObservacaoQuatro' => $this->observacaoQuatro,
            'ObservacaoCinco' => $this->observacaoCinco,
            'PosicaoVolume' => $this->posicaoVolume === null ? null : (string) $this->posicaoVolume,
            'Conteudo' => $this->conteudo,
        ], static fn(?string $v): bool => $v !== null);

        $finais = array_filter([
            'ValorDeclarado' => $this->valorDeclarado?->formatar(),
            'AdicionaisVolume' => $this->adicionais === [] ? null : implode(' ', $this->adicionais),
            'VlrACobrar' => $this->valorACobrar?->formatar(),
            'Etiqueta' => $this->etiqueta,
        ], static fn(?string $v): bool => $v !== null);

        return $opcionais + ['DeclaracaoConteudo' => $this->declaracaoConteudo->toArray()] + $finais;
    }
}
