<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Exceptions;

use Throwable;

/**
 * Marcador comum a todas as exceções do SDK: `catch (VippException $e)` captura qualquer falha do pacote.
 */
interface VippException extends Throwable {}
