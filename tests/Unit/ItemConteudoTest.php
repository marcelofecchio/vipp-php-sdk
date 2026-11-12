<?php

declare(strict_types=1);

use MarceloFecchio\Vipp\Postagem\ItemConteudo;
use MarceloFecchio\Vipp\Postagem\Volume;
use MarceloFecchio\Vipp\Validation\ValidadorPostagem;

it('limpa descrições de NF-e para o formato aceito pela SEFAZ', function (string $entrada, string $esperado): void {
    expect(ItemConteudo::limparDescricao($entrada))->toBe($esperado);
})->with([
    'símbolos comuns da NF-e' => ['CAMISETA #12 & "PREMIUM" * ALGODÃO', 'CAMISETA 12 PREMIUM ALGODÃO'],
    'emoji' => ['Caneca 😀 personalizada', 'Caneca personalizada'],
    'mantém pontuação aceita' => ['Calção (M) - 100% algodão, 1/2 manga: azul; +brinde', 'Calção (M) - 100% algodão, 1/2 manga: azul; +brinde'],
    'quebras de linha e tabs' => ["Kit\n\tcom  2\r\nitens", 'Kit com 2 itens'],
    'só símbolos' => ['#@!*', ''],
]);

it('corta em 70 caracteres sem deixar espaço no fim', function (): void {
    $limpa = ItemConteudo::limparDescricao(str_repeat('a', 69).' bcd');

    expect(mb_strlen($limpa))->toBe(69)
        ->and($limpa)->toBe(str_repeat('a', 69));
});

it('gera descrições que passam na validação local', function (): void {
    $item = new ItemConteudo(ItemConteudo::limparDescricao('PARAFUSO 3/8" C/ PORCA * 50UN #REF-77 & ARRUELA ®'), 1, 10);
    $postagem = postagemExemplo(new Volume(declaracaoExemplo([$item]), peso: 100));

    expect(new ValidadorPostagem()->validar($postagem))->toBe([]);
});
