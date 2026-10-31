<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Http;

use GuzzleHttp\Client as GuzzleClient;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;

/**
 * Cliente PSR-18 padrão quando o consumidor não injeta um: Guzzle (com timeout) se instalado,
 * senão qualquer implementação encontrada pelo php-http/discovery.
 */
final class HttpClientFactory
{
    public static function criar(float $timeout): ClientInterface
    {
        if (class_exists(GuzzleClient::class)) {
            return new GuzzleClient([
                'timeout' => $timeout,
                'connect_timeout' => min(10.0, $timeout),
                'http_errors' => false,
            ]);
        }

        return Psr18ClientDiscovery::find();
    }
}
