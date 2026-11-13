<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Http;

/**
 * Esconde o token em textos que podem ir para log ou mensagem de exceção (o ViPP ecoa o `PerfilVipp` na resposta).
 *
 * @internal
 */
final class Mascara
{
    private const int LIMITE = 2000;

    public static function corpo(string $corpo): string
    {
        $corpo = (string) preg_replace('/("Token"\s*:\s*)"(?:[^"\\\\]|\\\\.)*"/i', '$1"***"', $corpo);
        $corpo = (string) preg_replace('#(<(?:\w+:)?Token>)[^<]*(</(?:\w+:)?Token>)#i', '$1***$2', $corpo);
        // A consulta de situação manda a senha na query string, e mensagens de erro do cliente HTTP trazem a URL.
        $corpo = (string) preg_replace('/([?&](?:Senha|Token)=)[^&\s"\']*/i', '$1***', $corpo);

        return strlen($corpo) > self::LIMITE ? substr($corpo, 0, self::LIMITE).'…' : $corpo;
    }
}
