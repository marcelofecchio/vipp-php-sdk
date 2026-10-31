<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Support;

/**
 * Normalizações aplicadas nos construtores dos DTOs, para que o payload siga o formato que o ViPP espera.
 *
 * @internal
 */
final class Normaliza
{
    /**
     * Remove espaços nas pontas e espaços repetidos; string vazia vira null.
     */
    public static function texto(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim((string) preg_replace('/\s+/u', ' ', $valor));

        return $valor === '' ? null : $valor;
    }

    /**
     * Mantém só os dígitos (CEP, telefone, contrato); string sem dígitos vira null.
     */
    public static function digitos(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = (string) preg_replace('/\D+/', '', $valor);

        return $valor === '' ? null : $valor;
    }

    /**
     * CPF/CNPJ: remove pontuação e mantém letras maiúsculas (o CNPJ alfanumérico passa a valer em 2026).
     */
    public static function documento(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = strtoupper((string) preg_replace('/[^0-9A-Za-z]+/', '', $valor));

        return $valor === '' ? null : $valor;
    }

    /**
     * Código em maiúsculas sem espaços (UF, etiqueta).
     */
    public static function codigo(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = strtoupper((string) preg_replace('/\s+/', '', $valor));

        return $valor === '' ? null : $valor;
    }
}
