<?php

declare(strict_types=1);

use MarceloFecchio\Vipp\Laravel\Facades\Vipp;
use MarceloFecchio\Vipp\Postagem\Postagem;
use MarceloFecchio\Vipp\Resultado\ResultadoPostagem;
use MarceloFecchio\Vipp\Testing\VippFake;
use MarceloFecchio\Vipp\VippClientInterface;

/**
 * Simula um serviço do marcelo-envios que recebe o cliente por injeção de dependência.
 */
final readonly class ExportadorDeEtiquetas
{
    public function __construct(private VippClientInterface $vipp) {}

    public function exportar(Postagem $postagem): ResultadoPostagem
    {
        return $this->vipp->postarObjeto($postagem);
    }
}

it('Vipp::fake() troca o cliente para a Facade e para a injeção de dependência', function (): void {
    $fake = Vipp::fake();

    $pelaFacade = Vipp::postarObjeto(postagemExemplo());
    $pelaInjecao = app(ExportadorDeEtiquetas::class)->exportar(postagemExemplo());

    expect(app(VippClientInterface::class))->toBeInstanceOf(VippFake::class)
        ->and($pelaFacade->ehValida())->toBeTrue()
        ->and($pelaInjecao->ehValida())->toBeTrue()
        ->and($fake->postagens())->toHaveCount(2);
});

it('expõe a validação local pela Facade', function (): void {
    expect(Vipp::validar(postagemExemplo()))->toBe([]);
});

it('funciona dentro de um job enfileirado', function (): void {
    $fake = Vipp::fake();
    $postagem = postagemExemplo();

    dispatch_sync(static function () use ($postagem): void {
        app(VippClientInterface::class)->postarObjeto($postagem);
    });

    expect($fake->ultimaPostagem())->toBe($postagem);
});

it('Vipp::fake() aceita exigirContrato', function (): void {
    Vipp::fake(exigirContrato: true);

    expect(Vipp::validar(postagemExemplo()))->toHaveCount(2);
});

it('Vipp::excluirPostagem() usa o fake', function (): void {
    $fake = Vipp::fake();

    expect(Vipp::excluirPostagem('SQ000352886BR')->excluida())->toBeTrue()
        ->and($fake->exclusoes())->toBe(['SQ000352886BR']);
});
