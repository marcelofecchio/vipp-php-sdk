<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Enum;

/**
 * Siglas de serviços adicionais (Nota 15). A lista do PDF é só de exemplos: o SDK também aceita
 * siglas de 2 letras que não estejam aqui.
 */
enum ServicoAdicional: string
{
    case AvisoRecebimento = 'AR';
    case MaoPropria = 'MP';
    case ArtigosPerigosos = 'AP';
    case AvisoRecebimentoDigital = 'AD';
    case OutrosFormatos = 'OF';
    case AvisoRecebimentoEletronico = 'AT';
    case ProdutoControlado = 'EE';
    case EntregaNoVizinho = 'EV';
    case RegistroModico = 'RM';
    case RemessaDePlantas = 'RP';
    case RemessaDeSementes = 'RS';
    /** Valor Declarado: o ViPP inclui sozinho quando `ValorDeclarado` é informado (observado na homologação). */
    case ValorDeclarado = 'VD';
}
