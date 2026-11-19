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

    /**
     * Mascara e corta em 2.000 caracteres (para mensagens de exceção).
     */
    public static function corpo(string $corpo): string
    {
        $corpo = self::segredos($corpo);

        return strlen($corpo) > self::LIMITE ? substr($corpo, 0, self::LIMITE).'…' : $corpo;
    }

    /**
     * Mascara sem cortar (para guardar a resposta bruta, como o XML do bloqueio).
     */
    public static function segredos(string $texto): string
    {
        $texto = (string) preg_replace('/("Token"\s*:\s*)"(?:[^"\\\\]|\\\\.)*"/i', '$1"***"', $texto);
        // JSON e XML (inclusive com prefixo, como <v:Token>): o SOAP de bloqueio ecoa o token na resposta.
        $texto = (string) preg_replace('#(<(?:\w+:)?Token>)[^<]*(</(?:\w+:)?Token>)#i', '$1***$2', $texto);

        // A consulta de situação manda a senha na query string, e mensagens de erro do cliente HTTP trazem a URL.
        return (string) preg_replace('/([?&](?:Senha|Token)=)[^&\s"\']*/i', '$1***', $texto);
    }
}
