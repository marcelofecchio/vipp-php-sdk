<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Http;

use DOMDocument;
use DOMElement;

/**
 * Monta e lê envelopes SOAP 1.1 do ViPP sem depender da extensão `soap` do PHP.
 *
 * @internal
 */
final class Soap
{
    public const string NAMESPACE = 'http://www.visualset.inf.br/';

    private const string ENVELOPE = 'http://schemas.xmlsoap.org/soap/envelope/';

    /**
     * Valores nulos são omitidos. A ordem dos campos importa: o .NET segue a sequência do WSDL.
     *
     * @param array<string, mixed> $campos string ou array aninhado (ex.: `PerfilVipp`)
     */
    public static function envelope(string $operacao, string $parametro, array $campos): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<soap:Envelope xmlns:soap="'.self::ENVELOPE.'" xmlns:v="'.self::NAMESPACE.'"><soap:Body>'
            ."<v:{$operacao}><v:{$parametro}>".self::elementos($campos)."</v:{$parametro}></v:{$operacao}>"
            .'</soap:Body></soap:Envelope>';
    }

    /**
     * Conteúdo do elemento de resultado (ex.: `BloquearDownloadConhecimentoResult`) como array, ou null se não houver.
     * `ListaErros` vira uma lista de erros.
     *
     * @return array<string, mixed>|null
     */
    public static function resultado(string $xml, string $elemento): ?array
    {
        $no = self::documento($xml)?->getElementsByTagNameNS(self::NAMESPACE, $elemento)->item(0);

        return $no instanceof DOMElement ? self::paraArray($no) : null;
    }

    /**
     * `faultstring` de um SOAP Fault, ou null se a resposta não for uma falha.
     */
    public static function falha(string $xml): ?string
    {
        $fault = self::documento($xml)?->getElementsByTagNameNS(self::ENVELOPE, 'Fault')->item(0);
        if (!$fault instanceof DOMElement) {
            return null;
        }

        $texto = $fault->getElementsByTagName('faultstring')->item(0)?->textContent;

        return $texto === null ? 'SOAP Fault sem descrição' : trim($texto);
    }

    /**
     * @param array<string, mixed> $campos
     */
    private static function elementos(array $campos): string
    {
        $xml = '';
        foreach ($campos as $nome => $valor) {
            if (is_array($valor)) {
                /** @var array<string, mixed> $valor */
                $xml .= "<v:{$nome}>".self::elementos($valor)."</v:{$nome}>";
            } elseif (is_string($valor)) {
                $xml .= "<v:{$nome}>".htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8')."</v:{$nome}>";
            }
        }

        return $xml;
    }

    private static function documento(string $xml): ?DOMDocument
    {
        if (trim($xml) === '') {
            return null;
        }

        $anterior = libxml_use_internal_errors(true);
        $documento = new DOMDocument();
        // Sem LIBXML_NOENT (entidades não são expandidas) e sem acesso à rede.
        $ok = $documento->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        return $ok ? $documento : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function paraArray(DOMElement $elemento): array
    {
        $dados = [];
        foreach ($elemento->childNodes as $filho) {
            if (!$filho instanceof DOMElement) {
                continue;
            }

            $nome = $filho->localName;

            if ($nome === 'ListaErros') {
                $erros = [];
                foreach ($filho->childNodes as $erro) {
                    if ($erro instanceof DOMElement) {
                        $erros[] = self::paraArray($erro);
                    }
                }
                $dados[$nome] = $erros;

                continue;
            }

            $dados[$nome] = self::temElementos($filho) ? self::paraArray($filho) : $filho->textContent;
        }

        return $dados;
    }

    private static function temElementos(DOMElement $elemento): bool
    {
        foreach ($elemento->childNodes as $filho) {
            if ($filho instanceof DOMElement) {
                return true;
            }
        }

        return false;
    }
}
