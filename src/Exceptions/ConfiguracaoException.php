<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Exceptions;

use InvalidArgumentException;

/**
 * Configuração ausente ou inválida (credenciais, URL base, timeout).
 */
final class ConfiguracaoException extends InvalidArgumentException implements VippException {}
