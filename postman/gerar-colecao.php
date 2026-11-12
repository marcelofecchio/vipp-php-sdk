<?php

declare(strict_types=1);

/**
 * Gera a coleção e o ambiente Postman a partir dos DTOs do SDK, para que os payloads sejam
 * exatamente os que o SDK envia.
 *
 *     php postman/gerar-colecao.php
 */

use MarceloFecchio\Vipp\Config\VippConfig;
use MarceloFecchio\Vipp\Enum\ServicoAdicional;
use MarceloFecchio\Vipp\Postagem\DeclaracaoConteudo;
use MarceloFecchio\Vipp\Postagem\Destinatario;
use MarceloFecchio\Vipp\Postagem\ItemConteudo;
use MarceloFecchio\Vipp\Postagem\NotaFiscal;
use MarceloFecchio\Vipp\Postagem\Postagem;
use MarceloFecchio\Vipp\Postagem\Volume;

require dirname(__DIR__).'/vendor/autoload.php';

$perfil = ['Usuario' => '{{usuario}}', 'Token' => '{{token}}', 'IdPerfil' => '{{idPerfil}}'];

$destinatario = new Destinatario(
    nome: 'Teste de WebService',
    endereco: 'R Conselheiro Nebias',
    numero: '263',
    bairro: 'Campos Eliseos',
    cidade: 'Sao Paulo',
    uf: 'SP',
    cep: '01203-001',
    complemento: '7 Andar',
    celular: '11937378999',
    email: 'teste@example.com',
);

$declaracao = new DeclaracaoConteudo('03998581000190', '52998224725', [
    new ItemConteudo('Camiseta algodao', 2, '125,98'),
]);

$volume = static fn(?string $etiqueta = null, ?string $codigo = null): Volume => new Volume(
    declaracaoConteudo: $declaracao,
    peso: 1600,
    altura: 4,
    largura: 11,
    comprimento: 16,
    etiqueta: $etiqueta,
    codigoBarraVolume: $codigo ?? 'PEDIDO-POSTMAN',
    valorDeclarado: '150,00',
    adicionais: [ServicoAdicional::AvisoRecebimento],
);

/**
 * @param array<string, mixed> $payload
 * @param list<string> $testes
 *
 * @return array<string, mixed>
 */
$requisicao = static function (string $nome, string $descricao, array $payload, array $testes): array {
    return [
        'name' => $nome,
        'event' => [[
            'listen' => 'test',
            'script' => ['type' => 'text/javascript', 'exec' => $testes],
        ]],
        'request' => [
            'method' => 'POST',
            'description' => $descricao,
            'header' => [
                ['key' => 'Content-Type', 'value' => 'application/json'],
                ['key' => 'Accept-Encoding', 'value' => 'UTF-8'],
            ],
            'body' => [
                'mode' => 'raw',
                'raw' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'options' => ['raw' => ['language' => 'json']],
            ],
            'url' => [
                'raw' => '{{baseUrl}}'.VippConfig::CAMINHO_POSTAR_OBJETO,
                'host' => ['{{baseUrl}}'],
                'path' => explode('/', trim(VippConfig::CAMINHO_POSTAR_OBJETO, '/')),
            ],
        ],
    ];
};

/**
 * @return list<string>
 */
$testesErro = static fn(int $status, int $codigo): array => [
    "pm.test('HTTP {$status}', () => pm.response.to.have.status({$status}));",
    'const corpo = pm.response.json();',
    "pm.test('StatusPostagem = Invalida', () => pm.expect(corpo.StatusPostagem).to.eql('Invalida'));",
    "pm.test('ListaErros contém o erro {$codigo}', () => pm.expect(corpo.ListaErros.map(e => e.CodigoErro)).to.include({$codigo}));",
];

$valida = (new Postagem($destinatario, $volume(), '03298', [
    new NotaFiscal(numero: '49906', serie: '1', data: '2026-07-15', valorTotal: '251,96'),
]))->toArray();

$comEtiqueta = (new Postagem($destinatario, $volume('AA123456785BR', 'PEDIDO-COM-ETIQUETA')))->toArray();
$comEtiqueta['Volumes'][0]['Etiqueta'] = '{{etiquetaInformada}}';

$dvInvalido = (new Postagem($destinatario, $volume()))->toArray();
$dvInvalido['Volumes'][0]['Etiqueta'] = 'SQ123456786BR';

$dataSemBarras = (new Postagem($destinatario, $volume()))->toArray();
$dataSemBarras['NotasFiscais'] = [['DtNotaFiscal' => '15072026', 'NrNotaFiscal' => '49906']];

// Em produção, perfis exigem o contrato e o cartão (erro 91); a homologação ignora os valores e usa o do perfil.
$contrato = ['NrContrato' => '{{nrContrato}}', 'NrCartao' => '{{nrCartao}}'];
$valida = ['ContratoEct' => $contrato] + $valida;
$comEtiqueta = ['ContratoEct' => $contrato] + $comEtiqueta;

$cartaoSemContrato = ['ContratoEct' => ['NrCartao' => '{{nrCartao}}']] + (new Postagem($destinatario, $volume()))->toArray();

$colecao = [
    'info' => [
        'name' => 'ViPP — Web Service de Postagem (vipp-php-sdk)',
        'description' => "Gerada por postman/gerar-colecao.php a partir dos DTOs do marcelofecchio/vipp-php-sdk.\n\n"
            .'ATENÇÃO: as requisições válidas criam postagens reais no ambiente configurado. Use o ambiente de homologação.',
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    ],
    'item' => [
        $requisicao(
            '01 Postagem válida (ViPP gera a etiqueta)',
            'Payload completo montado pelo SDK: destinatário, NF, volume com declaração de conteúdo, valor declarado e AR.',
            ['PerfilVipp' => $perfil] + $valida,
            [
                "pm.test('HTTP 200', () => pm.response.to.have.status(200));",
                'const corpo = pm.response.json();',
                "pm.test('StatusPostagem = Valida', () => pm.expect(corpo.StatusPostagem).to.eql('Valida'));",
                'const volume = corpo.Volumes[0];',
                "pm.test('Etiqueta no formato dos Correios', () => pm.expect(volume.Etiqueta).to.match(/^[A-Z]{2}\\d{9}BR$/));",
                "pm.test('Valores e prazo presentes', () => { pm.expect(volume.ValorPostagem).to.be.a('string'); pm.expect(volume.DiasUteisPrazo).to.be.a('string'); });",
                "pm.collectionVariables.set('ultimaEtiqueta', volume.Etiqueta);",
            ],
        ),
        $requisicao(
            '02 Postagem com etiqueta já gerada nos Correios',
            'Caso de uso do marcelo-envios (Nota 16). Na homologação (perfil 9363) o ViPP ignora a etiqueta e gera outra; o teste registra a divergência no console.',
            ['PerfilVipp' => $perfil] + $comEtiqueta,
            [
                "pm.test('HTTP 200', () => pm.response.to.have.status(200));",
                'const corpo = pm.response.json();',
                "pm.test('StatusPostagem = Valida', () => pm.expect(corpo.StatusPostagem).to.eql('Valida'));",
                "const enviada = pm.environment.get('etiquetaInformada') || pm.collectionVariables.get('etiquetaInformada');",
                'const registrada = corpo.Volumes[0].Etiqueta;',
                "console.log(registrada === enviada ? 'ViPP manteve a etiqueta enviada: ' + enviada : 'ViPP registrou OUTRA etiqueta: enviada ' + enviada + ', registrada ' + registrada);",
                "pm.test('Etiqueta registrada no formato dos Correios', () => pm.expect(registrada).to.match(/^[A-Z]{2}\\d{9}BR$/));",
            ],
        ),
        $requisicao(
            '03 Campos obrigatórios ausentes (400)',
            'Destinatário vazio e item sem descrição: erros 1310, 1320, 1330 e 2200.',
            [
                'PerfilVipp' => $perfil,
                'Destinatario' => (object) [],
                'Volumes' => [['DeclaracaoConteudo' => ['ItemConteudo' => [['DescricaoConteudo' => '', 'Quantidade' => 1, 'Valor' => '']]]]],
            ],
            [...$testesErro(400, 1310), "pm.test('Erros de endereço e CEP', () => pm.expect(pm.response.json().ListaErros.map(e => e.CodigoErro)).to.include.members([1320, 1330]));"],
        ),
        $requisicao('04 Etiqueta com dígito verificador inválido (400 / 2002)', 'Nota 16.', ['PerfilVipp' => $perfil] + $dvInvalido, $testesErro(400, 2002)),
        $requisicao('05 Data da NF sem barras (400 / 1500)', 'A Nota 8 do PDF cita DDMMAAAA, mas o ViPP exige DD/MM/AAAA.', ['PerfilVipp' => $perfil] + $dataSemBarras, $testesErro(400, 1500)),
        $requisicao(
            '06 Credenciais inválidas (401 / 1030)',
            'Token errado: HTTP 401. No SDK vira AutenticacaoException.',
            ['PerfilVipp' => ['Usuario' => '{{usuario}}', 'Token' => 'token-invalido', 'IdPerfil' => '{{idPerfil}}']] + $valida,
            $testesErro(401, 1030),
        ),
        $requisicao(
            '07 Cartão sem contrato (400 / 1100)',
            'Observado na homologação: enviar NrCartao sem NrContrato dá o erro 1100 ("O Número do Cartão É Obrigatóriamente Numérico!"), mesmo com o cartão numérico.',
            ['PerfilVipp' => $perfil] + $cartaoSemContrato,
            $testesErro(400, 1100),
        ),
    ],
    'variable' => [
        ['key' => 'etiquetaInformada', 'value' => 'AA123456785BR'],
        ['key' => 'ultimaEtiqueta', 'value' => ''],
    ],
];

$ambiente = [
    'name' => 'ViPP — Homologação',
    'values' => [
        ['key' => 'baseUrl', 'value' => VippConfig::URL_PADRAO, 'type' => 'default', 'enabled' => true],
        ['key' => 'usuario', 'value' => VippConfig::CREDENCIAIS_HOMOLOGACAO['usuario'], 'type' => 'default', 'enabled' => true],
        ['key' => 'token', 'value' => VippConfig::CREDENCIAIS_HOMOLOGACAO['token'], 'type' => 'secret', 'enabled' => true],
        ['key' => 'idPerfil', 'value' => VippConfig::CREDENCIAIS_HOMOLOGACAO['id_perfil'], 'type' => 'default', 'enabled' => true],
        // Fictícios: a homologação ignora e usa o contrato do perfil. Em produção, use os dados dos Correios.
        ['key' => 'nrContrato', 'value' => '9912345678', 'type' => 'default', 'enabled' => true],
        ['key' => 'nrCartao', 'value' => '0067345678', 'type' => 'default', 'enabled' => true],
        ['key' => 'etiquetaInformada', 'value' => 'AA123456785BR', 'type' => 'default', 'enabled' => true],
    ],
    '_postman_variable_scope' => 'environment',
];

$json = static fn(array $dados): string => json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";

file_put_contents(__DIR__.'/ViPP.postman_collection.json', $json($colecao));
file_put_contents(__DIR__.'/Homologacao.postman_environment.json', $json($ambiente));

echo "Coleção e ambiente gerados em postman/.\n";
