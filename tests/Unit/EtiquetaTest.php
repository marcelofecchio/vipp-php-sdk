<?php

declare(strict_types=1);

use MarceloFecchio\Vipp\Validation\Etiqueta;

it('aceita etiquetas reais devolvidas pela homologação e o exemplo do PDF', function (string $etiqueta): void {
    expect(Etiqueta::valida($etiqueta))->toBeTrue();
})->with(['SQ000352413BR', 'SQ000352886BR', 'AA123456785BR']);

it('calcula o dígito verificador, inclusive os casos especiais de resto 0 e 1', function (string $numero, int $dv): void {
    expect(Etiqueta::digitoVerificador($numero))->toBe($dv);
})->with([
    'resto 0 vira 5' => ['00000000', 5],
    'resto 1 vira 0' => ['00000008', 0],
    'caso comum' => ['12345678', 5],
    'etiqueta real' => ['00035241', 3],
]);

it('recusa dígito verificador errado', function (): void {
    expect(Etiqueta::erro('SQ123456786BR'))->toBe('dígito verificador inválido.');
});

it('recusa formato inválido', function (string $etiqueta): void {
    expect(Etiqueta::erro($etiqueta))->toStartWith('formato inválido');
})->with(['SQ12345678BR', 'S1123456785BR', 'SQ123456785US', 'sq123456785br', '']);

it('exige 8 dígitos no cálculo do DV', function (): void {
    Etiqueta::digitoVerificador('123');
})->throws(InvalidArgumentException::class);
