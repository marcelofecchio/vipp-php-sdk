<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Enum;

/**
 * Campo usado por `situacaoPostagem()` para localizar a postagem (parâmetro `BuscarPor` do `GetSituacaoPostagem`).
 */
enum BuscarPor: string
{
    /** Número da etiqueta (`Volume::$etiqueta`). */
    case Etiqueta = 'EtiquetaPostagem';

    /** Sua referência interna (`Volume::$codigoBarraVolume`), que o ViPP devolve como `ObservacaoUm`. */
    case Referencia = 'ObservacaoUm';

    /** Listado pela API como opção válida; não foi exercitado. */
    case ReferenciaDois = 'ObservacaoDois';
}
