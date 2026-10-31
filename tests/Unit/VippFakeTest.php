<?php

declare(strict_types=1);

use MarceloFecchio\Vipp\Exceptions\HttpException;
use MarceloFecchio\Vipp\Exceptions\ValidacaoException;
use MarceloFecchio\Vipp\Postagem\Destinatario;
use MarceloFecchio\Vipp\Postagem\Postagem;
use MarceloFecchio\Vipp\Postagem\Volume;
use MarceloFecchio\Vipp\Testing\VippFake;
use MarceloFecchio\Vipp\Validation\Etiqueta;

it('devolve postagem válida com etiqueta gerada e registra o envio', function (): void {
    $fake = new VippFake();

    $primeira = $fake->postarObjeto(postagemExemplo());
    $segunda = $fake->postarObjeto(postagemExemplo());

    expect($primeira->ehValida())->toBeTrue()
        ->and(Etiqueta::valida((string) $primeira->etiqueta()))->toBeTrue()
        ->and($segunda->etiqueta())->not->toBe($primeira->etiqueta())
        ->and($fake->postagens())->toHaveCount(2);
});

it('repete a etiqueta enviada, como o caso de uso do marcelo-envios', function (): void {
    $fake = new VippFake();
    $volume = new Volume(declaracaoExemplo(), peso: 300, etiqueta: 'SQ000352886BR');

    $resultado = $fake->postarObjeto(postagemExemplo($volume));

    expect($resultado->etiqueta())->toBe('SQ000352886BR')
        ->and($resultado->etiquetaDivergente())->toBeFalse()
        ->and($fake->ultimaPostagem()?->volume->etiqueta)->toBe('SQ000352886BR');
});

it('segue a fila de respostas programadas e depois volta ao padrão', function (): void {
    $fake = new VippFake()->responderCom(
        VippFake::resultadoInvalido([2002 => 'Dígito Verificador Inválido!']),
        new HttpException(429, 'limite'),
        static fn(Postagem $p) => VippFake::resultadoValido('SQ000000005BR'),
    );

    expect($fake->postarObjeto(postagemExemplo())->erros[0]->codigoErro)->toBe(2002)
        ->and(fn() => $fake->postarObjeto(postagemExemplo()))->toThrow(HttpException::class)
        ->and($fake->postarObjeto(postagemExemplo())->etiqueta())->toBe('SQ000000005BR')
        ->and($fake->postarObjeto(postagemExemplo())->ehValida())->toBeTrue();
});

it('aplica a mesma validação local do cliente real', function (): void {
    $destinatario = new Destinatario(nome: '', endereco: 'Rua', numero: '1', bairro: 'B', cidade: 'C', uf: 'SP', cep: '01203001');

    new VippFake()->postarObjeto(postagemExemplo(destinatario: $destinatario));
})->throws(ValidacaoException::class);

it('permite desligar a validação no fake', function (): void {
    $destinatario = new Destinatario(nome: '', endereco: 'Rua', numero: '1', bairro: 'B', cidade: 'C', uf: 'SP', cep: '01203001');

    expect(new VippFake(validar: false)->postarObjeto(postagemExemplo(destinatario: $destinatario))->ehValida())->toBeTrue();
});
