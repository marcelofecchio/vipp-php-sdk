<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\ValueObject;

use MarceloFecchio\Vipp\Exceptions\ValidacaoException;
use Stringable;

/**
 * Valor monetário em reais, guardado em centavos para evitar erro de ponto flutuante.
 *
 * Sempre serializado com vírgula e duas casas (`1234,56`): na homologação, um valor só com dígitos
 * é lido como centavos (`"10"` vira R$ 0,10), então o SDK nunca envia valores sem separador.
 */
final readonly class Dinheiro implements Stringable
{
    private function __construct(public int $centavos) {}

    public static function centavos(int $centavos): self
    {
        return new self($centavos);
    }

    /**
     * Aceita reais como int (`10` = R$ 10,00), float (`10.5`) ou string (`"1.234,56"`, `"1234,56"`, `"1234.56"`, `"R$ 10"`).
     */
    public static function reais(int|float|string $valor, string $campo = 'valor'): self
    {
        return match (true) {
            is_int($valor) => new self($valor * 100),
            is_float($valor) => new self((int) round($valor * 100)),
            default => self::deTexto($valor, $campo),
        };
    }

    public static function de(self|int|float|string $valor, string $campo = 'valor'): self
    {
        return $valor instanceof self ? $valor : self::reais($valor, $campo);
    }

    public static function deOpcional(self|int|float|string|null $valor, string $campo = 'valor'): ?self
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return self::de($valor, $campo);
    }

    public function multiplicar(int $fator): self
    {
        return new self($this->centavos * $fator);
    }

    public function somar(self $outro): self
    {
        return new self($this->centavos + $outro->centavos);
    }

    public function maiorQue(self $outro): bool
    {
        return $this->centavos > $outro->centavos;
    }

    public function emReais(): float
    {
        return $this->centavos / 100;
    }

    /**
     * Formato enviado ao ViPP: `1234,56`.
     */
    public function formatar(): string
    {
        $sinal = $this->centavos < 0 ? '-' : '';
        $abs = abs($this->centavos);

        return sprintf('%s%d,%02d', $sinal, intdiv($abs, 100), $abs % 100);
    }

    public function __toString(): string
    {
        return $this->formatar();
    }

    private static function deTexto(string $texto, string $campo): self
    {
        $limpo = (string) preg_replace('/\s+|R\$/u', '', $texto);

        // Formato brasileiro: milhar com ponto opcional e decimal com vírgula.
        if (preg_match('/^(-?)(\d{1,3}(?:\.\d{3})+|\d+)(?:,(\d{1,2}))?$/', $limpo, $m) === 1) {
            return self::montar($m[1], str_replace('.', '', $m[2]), $m[3] ?? '');
        }

        // Ponto decimal (como o ViPP devolve `ValorDeclarado`).
        if (preg_match('/^(-?)(\d+)\.(\d{1,2})$/', $limpo, $m) === 1) {
            return self::montar($m[1], $m[2], $m[3]);
        }

        throw ValidacaoException::campo($campo, "valor monetário inválido: \"{$texto}\".");
    }

    private static function montar(string $sinal, string $inteiro, string $decimal): self
    {
        $centavos = (int) $inteiro * 100 + (int) str_pad($decimal, 2, '0');

        return new self($sinal === '-' ? -$centavos : $centavos);
    }
}
