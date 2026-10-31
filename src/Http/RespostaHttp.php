<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Http;

/**
 * @internal
 */
final readonly class RespostaHttp
{
    /**
     * @param array<array-key, mixed>|null $dados JSON decodificado, ou null se o corpo não for um objeto JSON
     */
    public function __construct(
        public int $status,
        public string $corpo,
        public ?array $dados,
    ) {}
}
