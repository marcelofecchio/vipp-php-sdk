<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Enum;

enum StatusPostagem: string
{
    case Valida = 'Valida';
    case Invalida = 'Invalida';
}
