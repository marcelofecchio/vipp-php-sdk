<?php

declare(strict_types=1);

use MarceloFecchio\Vipp\Exceptions\ValidacaoException;
use MarceloFecchio\Vipp\ValueObject\Dinheiro;

it('interpreta reais em vários formatos', function (int|float|string $entrada, int $centavos): void {
    expect(Dinheiro::reais($entrada)->centavos)->toBe($centavos);
})->with([
    'int em reais' => [10, 1000],
    'float' => [10.5, 1050],
    'float com arredondamento' => [0.1 + 0.2, 30],
    'brasileiro com milhar' => ['1.234,56', 123456],
    'brasileiro sem milhar' => ['1234,56', 123456],
    'ponto decimal' => ['1234.56', 123456],
    'resposta do ViPP com ponto' => ['0.00', 0],
    'resposta do ViPP com vírgula' => ['15,48', 1548],
    'com R$' => ['R$ 10', 1000],
    'milhar sem decimais' => ['1.234', 123400],
    'uma casa decimal' => ['7,5', 750],
    'negativo' => ['-3,20', -320],
]);

it('sempre formata com vírgula e duas casas, sem separador de milhar', function (): void {
    expect(Dinheiro::reais('1.234,5')->formatar())->toBe('1234,50')
        ->and(Dinheiro::centavos(5)->formatar())->toBe('0,05')
        ->and(Dinheiro::centavos(-320)->formatar())->toBe('-3,20')
        ->and((string) Dinheiro::reais(10))->toBe('10,00');
});

it('recusa texto que não é valor', function (string $texto): void {
    Dinheiro::reais($texto, 'Volumes.ValorDeclarado');
})->with(['abc', '1,2,3', '10,123', ''])->throws(ValidacaoException::class);

it('faz operações sem perder precisão', function (): void {
    $item = Dinheiro::reais('0,10')->multiplicar(3);

    expect($item->centavos)->toBe(30)
        ->and($item->somar(Dinheiro::centavos(70))->emReais())->toBe(1.0)
        ->and($item->maiorQue(Dinheiro::centavos(29)))->toBeTrue();
});

it('trata vazio como ausente em deOpcional', function (): void {
    expect(Dinheiro::deOpcional(null))->toBeNull()
        ->and(Dinheiro::deOpcional(''))->toBeNull()
        ->and(Dinheiro::deOpcional(Dinheiro::centavos(1))?->centavos)->toBe(1);
});
