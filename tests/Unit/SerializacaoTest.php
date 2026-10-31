<?php

declare(strict_types=1);

use MarceloFecchio\Vipp\Enum\ServicoAdicional;
use MarceloFecchio\Vipp\Postagem\ContratoEct;
use MarceloFecchio\Vipp\Postagem\Destinatario;
use MarceloFecchio\Vipp\Postagem\ItemConteudo;
use MarceloFecchio\Vipp\Postagem\NotaFiscal;
use MarceloFecchio\Vipp\Postagem\Postagem;
use MarceloFecchio\Vipp\Postagem\Volume;

it('monta o payload completo no formato do ViPP', function (): void {
    $postagem = new Postagem(
        destinatario: new Destinatario(
            nome: '  Maria   da Silva ',
            endereco: 'Rua das Flores',
            numero: '100',
            bairro: 'Centro',
            cidade: 'São Paulo',
            uf: 'sp',
            cep: '01203-001',
            cnpjCpf: '529.982.247-25',
            telefone: '(11) 3737-8999',
            celular: '(11) 93737-8999',
            email: 'maria@example.com',
        ),
        volume: new Volume(
            declaracaoConteudo: declaracaoExemplo([
                new ItemConteudo('Camiseta', 2, 62.5),
                new ItemConteudo('Boné', 1, '1.234,00'),
            ]),
            peso: 1600,
            altura: 4,
            largura: 11,
            comprimento: 16,
            etiqueta: ' sq000352886br ',
            codigoBarraVolume: 'PED-123',
            valorDeclarado: '150',
            adicionais: [ServicoAdicional::AvisoRecebimento, 'mp', 'AR'],
            posicaoVolume: 1,
        ),
        servicoEct: '03298',
        notasFiscais: [new NotaFiscal(numero: '49906', serie: '1', data: '2026-07-15', valorTotal: 1604)],
        contratoEct: new ContratoEct(nrContrato: '9912329784', nrCartao: '0067335344'),
    );

    expect($postagem->toArray())->toBe([
        'ContratoEct' => ['NrContrato' => '9912329784', 'NrCartao' => '0067335344'],
        'Destinatario' => [
            'CnpjCpf' => '52998224725',
            'Nome' => 'Maria da Silva',
            'Endereco' => 'Rua das Flores',
            'Numero' => '100',
            'Bairro' => 'Centro',
            'Cidade' => 'São Paulo',
            'UF' => 'SP',
            'Cep' => '01203001',
            'Telefone' => '1137378999',
            'Celular' => '11937378999',
            'Email' => 'maria@example.com',
        ],
        'Servico' => ['ServicoECT' => '03298'],
        'NotasFiscais' => [[
            'DtNotaFiscal' => '15/07/2026',
            'SerieNotaFiscal' => '1',
            'NrNotaFiscal' => '49906',
            'VlrTotalNota' => '1604,00',
        ]],
        'Volumes' => [[
            'Peso' => '1600',
            'Altura' => '4',
            'Largura' => '11',
            'Comprimento' => '16',
            'CodigoBarraVolume' => 'PED-123',
            'PosicaoVolume' => '1',
            'DeclaracaoConteudo' => [
                'ItemConteudo' => [
                    ['DescricaoConteudo' => 'Camiseta', 'Quantidade' => 2, 'Valor' => '62,50'],
                    ['DescricaoConteudo' => 'Boné', 'Quantidade' => 1, 'Valor' => '1234,00'],
                ],
                'DocumentoRemetente' => '03998581000190',
                'DocumentoDestinatario' => '52998224725',
            ],
            'ValorDeclarado' => '150,00',
            'AdicionaisVolume' => 'AR MP',
            'Etiqueta' => 'SQ000352886BR',
        ]],
    ]);
});

it('omite blocos e campos não informados', function (): void {
    $payload = postagemExemplo()->toArray();

    expect($payload)->toHaveKeys(['Destinatario', 'Volumes'])
        ->not->toHaveKeys(['ContratoEct', 'Servico', 'NotasFiscais'])
        ->and($payload['Volumes'])->toHaveCount(1)
        ->and($payload['Volumes'][0])->not->toHaveKeys(['Etiqueta', 'ValorDeclarado', 'AdicionaisVolume']);
});

it('formata datas DateTimeInterface como DD/MM/AAAA', function (): void {
    $nf = new NotaFiscal(data: new DateTimeImmutable('2026-01-05'));

    expect($nf->toArray())->toBe(['DtNotaFiscal' => '05/01/2026']);
});
