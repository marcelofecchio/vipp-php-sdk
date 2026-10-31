<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Validation;

/**
 * Número de registro (etiqueta) dos Correios: 2 letras, 8 dígitos, 1 dígito verificador e o sufixo `BR`.
 * Ex.: `AA123456785BR`.
 */
final class Etiqueta
{
    private const array PESOS = [8, 6, 4, 2, 3, 5, 9, 7];

    /**
     * Dígito verificador (módulo 11) para os 8 dígitos do número.
     */
    public static function digitoVerificador(string $numero): int
    {
        if (preg_match('/^\d{8}$/', $numero) !== 1) {
            throw new \InvalidArgumentException('O número da etiqueta deve ter 8 dígitos.');
        }

        $soma = 0;
        foreach (str_split($numero) as $i => $digito) {
            $soma += (int) $digito * self::PESOS[$i];
        }

        $resto = $soma % 11;

        return match ($resto) {
            0 => 5,
            1 => 0,
            default => 11 - $resto,
        };
    }

    /**
     * Mensagem de erro, ou null se a etiqueta for válida.
     */
    public static function erro(string $etiqueta): ?string
    {
        if (preg_match('/^[A-Z]{2}(\d{8})(\d)BR$/', $etiqueta, $m) !== 1) {
            return 'formato inválido; esperado 2 letras, 9 dígitos e "BR" (ex.: AA123456785BR).';
        }

        if (self::digitoVerificador($m[1]) !== (int) $m[2]) {
            return 'dígito verificador inválido.';
        }

        return null;
    }

    public static function valida(string $etiqueta): bool
    {
        return self::erro($etiqueta) === null;
    }
}
