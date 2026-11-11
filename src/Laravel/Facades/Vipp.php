<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use MarceloFecchio\Vipp\Testing\VippFake;
use MarceloFecchio\Vipp\VippClientInterface;

/**
 * @method static \MarceloFecchio\Vipp\Resultado\ResultadoPostagem postarObjeto(\MarceloFecchio\Vipp\Postagem\Postagem $postagem, ?\MarceloFecchio\Vipp\Config\PerfilVipp $perfil = null)
 * @method static list<\MarceloFecchio\Vipp\Validation\Violacao> validar(\MarceloFecchio\Vipp\Postagem\Postagem $postagem)
 *
 * @see VippClientInterface
 */
final class Vipp extends Facade
{
    /**
     * Troca o cliente do container por um `VippFake` (inclusive para quem recebe `VippClientInterface` por injeção).
     */
    public static function fake(bool $validar = true, bool $exigirContrato = false): VippFake
    {
        $fake = new VippFake($validar, $exigirContrato);
        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return VippClientInterface::class;
    }
}
