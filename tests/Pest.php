<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use MarceloFecchio\Vipp\Postagem\DeclaracaoConteudo;
use MarceloFecchio\Vipp\Postagem\Destinatario;
use MarceloFecchio\Vipp\Postagem\ItemConteudo;
use MarceloFecchio\Vipp\Postagem\Postagem;
use MarceloFecchio\Vipp\Postagem\Volume;
use MarceloFecchio\Vipp\Tests\Laravel\TestCase;

uses(TestCase::class)->in('Laravel');

function destinatarioExemplo(string $cep = '01203-001', ?string $celular = null): Destinatario
{
    return new Destinatario(
        nome: 'Teste de WebService',
        endereco: 'R Conselheiro Nebias',
        numero: '263',
        bairro: 'Campos Eliseos',
        cidade: 'Sao Paulo',
        uf: 'sp',
        cep: $cep,
        complemento: '7 Andar',
        celular: $celular,
    );
}

/**
 * @param list<ItemConteudo>|null $itens
 */
function declaracaoExemplo(?array $itens = null, string $remetente = '03.998.581/0001-90', string $destinatario = '529.982.247-25'): DeclaracaoConteudo
{
    return new DeclaracaoConteudo($remetente, $destinatario, $itens ?? [new ItemConteudo('Camiseta algodao', 2, '125,98')]);
}

function postagemExemplo(?Volume $volume = null, ?Destinatario $destinatario = null): Postagem
{
    return new Postagem(
        destinatario: $destinatario ?? destinatarioExemplo(),
        volume: $volume ?? new Volume(declaracaoExemplo(), peso: 1600, altura: 4, largura: 11, comprimento: 16),
    );
}

function fixtureVipp(string $nome): string
{
    return (string) file_get_contents(__DIR__."/Fixtures/{$nome}.json");
}

function respostaFixture(int $status, string $nome): Response
{
    return new Response($status, ['Content-Type' => 'application/json; charset=utf-8'], fixtureVipp($nome));
}
