<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Validation;

/**
 * Validação de CPF e CNPJ, inclusive o CNPJ alfanumérico (letras valem `ord(c) - 48`, conforme a regra da Receita).
 * Espera o valor já normalizado (sem pontuação, em maiúsculas).
 */
final class Documento
{
    public static function valido(string $documento): bool
    {
        return match (strlen($documento)) {
            11 => self::cpfValido($documento),
            14 => self::cnpjValido($documento),
            default => false,
        };
    }

    public static function cpfValido(string $cpf): bool
    {
        if (preg_match('/^\d{11}$/', $cpf) !== 1 || preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $cpf[$i] * ($t + 1 - $i);
            }
            $digito = (10 * $soma) % 11 % 10;
            if ((int) $cpf[$t] !== $digito) {
                return false;
            }
        }

        return true;
    }

    public static function cnpjValido(string $cnpj): bool
    {
        if (preg_match('/^[0-9A-Z]{12}\d{2}$/', $cnpj) !== 1 || preg_match('/^(\d)\1{13}$/', $cnpj) === 1) {
            return false;
        }

        $valores = array_map(static fn(string $c): int => ord($c) - 48, str_split($cnpj));

        foreach ([12, 13] as $posicao) {
            $pesos = $posicao === 12
                ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
                : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
            $soma = 0;
            foreach ($pesos as $i => $peso) {
                $soma += $valores[$i] * $peso;
            }
            $resto = $soma % 11;
            $digito = $resto < 2 ? 0 : 11 - $resto;
            if ($valores[$posicao] !== $digito) {
                return false;
            }
        }

        return true;
    }
}
