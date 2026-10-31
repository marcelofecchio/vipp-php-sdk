<?php

declare(strict_types=1);

use MarceloFecchio\Vipp\Exceptions\ValidacaoException;
use MarceloFecchio\Vipp\Postagem\Destinatario;
use MarceloFecchio\Vipp\Postagem\ItemConteudo;
use MarceloFecchio\Vipp\Postagem\NotaFiscal;
use MarceloFecchio\Vipp\Postagem\Postagem;
use MarceloFecchio\Vipp\Postagem\Volume;
use MarceloFecchio\Vipp\Validation\ValidadorPostagem;
use MarceloFecchio\Vipp\Validation\Violacao;

/**
 * @return list<string>
 */
function camposViolados(Postagem $postagem): array
{
    return array_map(static fn(Violacao $v): string => $v->campo, new ValidadorPostagem()->validar($postagem));
}

function volumeCom(mixed ...$args): Volume
{
    return new Volume(...['declaracaoConteudo' => declaracaoExemplo(), 'peso' => 1600, ...$args]);
}

it('aprova uma postagem válida', function (): void {
    expect(new ValidadorPostagem()->validar(postagemExemplo()))->toBe([]);
});

it('aprova uma postagem com etiqueta já gerada nos Correios', function (): void {
    expect(camposViolados(postagemExemplo(volumeCom(etiqueta: 'SQ000352886BR'))))->toBe([]);
});

it('exige os campos obrigatórios do destinatário', function (): void {
    $destinatario = new Destinatario(nome: ' ', endereco: '', numero: '', bairro: '', cidade: '', uf: '', cep: '');

    expect(camposViolados(postagemExemplo(destinatario: $destinatario)))->toBe([
        'Destinatario.Nome',
        'Destinatario.Endereco',
        'Destinatario.Numero',
        'Destinatario.Bairro',
        'Destinatario.Cidade',
        'Destinatario.UF',
        'Destinatario.Cep',
    ]);
});

it('valida CEP, UF, celular, documento e e-mail do destinatário', function (): void {
    $destinatario = new Destinatario(
        nome: 'Maria',
        endereco: 'Rua A',
        numero: '1',
        bairro: 'Centro',
        cidade: 'Rio',
        uf: 'XX',
        cep: '1203001',
        cnpjCpf: '111.111.111-11',
        celular: '1137378999',
        email: 'maria@',
    );

    expect(camposViolados(postagemExemplo(destinatario: $destinatario)))->toBe([
        'Destinatario.UF',
        'Destinatario.Cep',
        'Destinatario.CnpjCpf',
        'Destinatario.Celular',
        'Destinatario.Email',
    ]);
});

it('recusa etiqueta com dígito verificador errado (erro 2002 do ViPP)', function (): void {
    $violacoes = new ValidadorPostagem()->validar(postagemExemplo(volumeCom(etiqueta: 'SQ123456786BR')));

    expect($violacoes)->toHaveCount(1)
        ->and((string) $violacoes[0])->toBe('Volumes[0].Etiqueta: dígito verificador inválido.');
});

it('valida peso, medidas, chave de 44 dígitos, posição e adicionais', function (): void {
    $volume = volumeCom(
        peso: 0,
        altura: 100_000,
        codigoBarraCliente: '123',
        posicaoVolume: 1000,
        adicionais: ['A1', 'AR', 'MP', 'AP', 'AD', 'OF', 'AT'],
    );

    expect(camposViolados(postagemExemplo($volume)))->toBe([
        'Volumes[0].Peso',
        'Volumes[0].Altura',
        'Volumes[0].CodigoBarraCliente',
        'Volumes[0].PosicaoVolume',
        'Volumes[0].AdicionaisVolume',
        'Volumes[0].AdicionaisVolume',
    ]);
});

it('aplica as regras da Declaração de Conteúdo (Nota 17)', function (array $itens, string $campo): void {
    $volume = new Volume(declaracaoExemplo($itens), peso: 1600);

    expect(camposViolados(postagemExemplo($volume)))->toContain($campo);
})->with([
    'sem itens' => [[], 'Volumes[0].DeclaracaoConteudo.ItemConteudo'],
    'descrição curta (a homologação recusa 2 caracteres)' => [[new ItemConteudo('ab', 1, 10)], 'Volumes[0].DeclaracaoConteudo.ItemConteudo[0].DescricaoConteudo'],
    'descrição com emoji' => [[new ItemConteudo('Camiseta 😀', 1, 10)], 'Volumes[0].DeclaracaoConteudo.ItemConteudo[0].DescricaoConteudo'],
    'descrição longa' => [[new ItemConteudo(str_repeat('a', 71), 1, 10)], 'Volumes[0].DeclaracaoConteudo.ItemConteudo[0].DescricaoConteudo'],
    'quantidade zero' => [[new ItemConteudo('Camiseta', 0, 10)], 'Volumes[0].DeclaracaoConteudo.ItemConteudo[0].Quantidade'],
    'valor negativo' => [[new ItemConteudo('Camiseta', 1, -1)], 'Volumes[0].DeclaracaoConteudo.ItemConteudo[0].Valor'],
    'item acima de R$ 100 mil' => [[new ItemConteudo('Notebook', 2, '50000,01')], 'Volumes[0].DeclaracaoConteudo.ItemConteudo[0].Valor'],
    'soma acima de R$ 200 mil' => [
        [new ItemConteudo('Notebook', 1, 100_000), new ItemConteudo('Servidor', 1, 100_000), new ItemConteudo('Monitor', 1, '0,01')],
        'Volumes[0].DeclaracaoConteudo.ItemConteudo',
    ],
]);

it('aceita acentos e pontuação simples na descrição', function (): void {
    $volume = new Volume(declaracaoExemplo([new ItemConteudo('Calção de algodão (M) - 100%', 1, 10)]), peso: 1600);

    expect(camposViolados(postagemExemplo($volume)))->toBe([]);
});

it('valida os documentos da declaração', function (): void {
    $volume = new Volume(declaracaoExemplo(remetente: '', destinatario: '123'), peso: 1600);

    expect(camposViolados(postagemExemplo($volume)))->toBe([
        'Volumes[0].DeclaracaoConteudo.DocumentoRemetente',
        'Volumes[0].DeclaracaoConteudo.DocumentoDestinatario',
    ]);
});

it('valida a data da nota fiscal (a homologação recusa DDMMAAAA com erro 1500)', function (string $data, bool $valida): void {
    $postagem = new Postagem(destinatarioExemplo(), volumeCom(), notasFiscais: [new NotaFiscal(numero: '1', data: $data)]);

    expect(camposViolados($postagem) === [])->toBe($valida);
})->with([
    ['15/07/2026', true],
    ['2026-07-15', true],
    ['15072026', false],
    ['31/02/2026', false],
]);

it('lança ValidacaoException com todas as violações em garantir()', function (): void {
    $destinatario = new Destinatario(nome: '', endereco: 'Rua', numero: '1', bairro: 'B', cidade: 'C', uf: 'SP', cep: '123');

    try {
        new ValidadorPostagem()->garantir(postagemExemplo(destinatario: $destinatario));
        $this->fail('Deveria lançar ValidacaoException.');
    } catch (ValidacaoException $e) {
        expect($e->violacoes)->toHaveCount(2)
            ->and($e->getMessage())->toContain('Destinatario.Nome: obrigatório.')
            ->toContain('Destinatario.Cep');
    }
});
