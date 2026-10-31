<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Exceptions;

use RuntimeException;

/**
 * Falha de rede: DNS, conexão recusada, timeout, TLS. Nenhuma resposta HTTP foi recebida.
 *
 * Atenção: em timeout a postagem pode ter sido processada pelo ViPP. Consulte o ViPP antes de reenviar.
 */
final class TransporteException extends RuntimeException implements VippException {}
