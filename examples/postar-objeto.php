<?php

declare(strict_types=1);

/**
 * Exemplo em PHP puro. Por padrão usa a homologação (cria uma postagem de teste real).
 *
 *     php examples/postar-objeto.php
 *
 * Para outro perfil, defina VIPP_USUARIO, VIPP_TOKEN e VIPP_ID_PERFIL.
 */

use MarceloFecchio\Vipp\Config\VippConfig;
use MarceloFecchio\Vipp\Enum\ServicoAdicional;
use MarceloFecchio\Vipp\Exceptions\AutenticacaoException;
use MarceloFecchio\Vipp\Exceptions\ValidacaoException;
use MarceloFecchio\Vipp\Exceptions\VippException;
use MarceloFecchio\Vipp\Postagem\DeclaracaoConteudo;
use MarceloFecchio\Vipp\Postagem\Destinatario;
use MarceloFecchio\Vipp\Postagem\ItemConteudo;
use MarceloFecchio\Vipp\Postagem\Postagem;
use MarceloFecchio\Vipp\Postagem\Volume;
use MarceloFecchio\Vipp\VippClient;

require dirname(__DIR__).'/vendor/autoload.php';

$config = getenv('VIPP_USUARIO') !== false
    ? VippConfig::fromArray([
        'usuario' => getenv('VIPP_USUARIO'),
        'token' => getenv('VIPP_TOKEN'),
        'id_perfil' => getenv('VIPP_ID_PERFIL'),
    ])
    : VippConfig::homologacao();

$vipp = new VippClient($config);

$postagem = new Postagem(
    destinatario: new Destinatario(
        nome: 'Maria da Silva',
        endereco: 'Rua Conselheiro Nébias',
        numero: '263',
        bairro: 'Campos Elíseos',
        cidade: 'São Paulo',
        uf: 'SP',
        cep: '01203-001',
        complemento: '7º andar',
        celular: '(11) 93737-8999',
    ),
    volume: new Volume(
        declaracaoConteudo: new DeclaracaoConteudo(
            documentoRemetente: '03.998.581/0001-90',
            documentoDestinatario: '529.982.247-25',
            itens: [new ItemConteudo('Camiseta de algodão', 2, '62,99')],
        ),
        peso: 350,
        altura: 4,
        largura: 11,
        comprimento: 16,
        codigoBarraVolume: 'PEDIDO-'.date('YmdHis'),
        adicionais: [ServicoAdicional::AvisoRecebimento],
    ),
    servicoEct: '03298',
);

try {
    $resultado = $vipp->postarObjeto($postagem);
} catch (ValidacaoException $e) {
    exit("Dados inválidos, nada foi enviado:\n{$e->getMessage()}\n");
} catch (AutenticacaoException $e) {
    exit("Credenciais recusadas: {$e->getMessage()}\n");
} catch (VippException $e) {
    exit('Falha ao falar com o ViPP: '.$e->getMessage()."\n");
}

if (!$resultado->ehValida()) {
    exit("ViPP recusou a postagem:\n- ".implode("\n- ", $resultado->mensagensDeErro())."\n");
}

$volume = $resultado->volume;
printf(
    "Etiqueta %s | postagem R$ %s | prazo %d dia(s) útil(eis)\n",
    $resultado->etiqueta(),
    $volume?->valorPostagem?->formatar() ?? '-',
    $volume?->diasUteisPrazo ?? 0,
);
