<?php

declare(strict_types=1);

use MarceloFecchio\Vipp\Validation\Documento;

it('aceita CPF e CNPJ válidos', function (string $documento): void {
    expect(Documento::valido($documento))->toBeTrue();
})->with([
    'CPF' => '52998224725',
    'CNPJ da VisualSet (exemplo do PDF)' => '03998581000190',
    'CNPJ' => '11222333000181',
    'CNPJ alfanumérico (exemplo da Receita)' => '12ABC34501DE35',
]);

it('recusa documentos inválidos', function (string $documento): void {
    expect(Documento::valido($documento))->toBeFalse();
})->with([
    'CPF com DV errado' => '52998224724',
    'CPF repetido' => '11111111111',
    'CNPJ com DV errado' => '11222333000182',
    'CNPJ repetido' => '00000000000000',
    'tamanho errado' => '123456789',
    'letra no DV do CNPJ' => '12ABC34501DE3A',
]);
