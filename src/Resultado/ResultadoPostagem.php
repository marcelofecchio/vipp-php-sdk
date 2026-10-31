<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Resultado;

use MarceloFecchio\Vipp\Enum\StatusPostagem;
use MarceloFecchio\Vipp\Support\Leitor;

/**
 * Resultado de `postarObjeto()`. Uma postagem "Invalida" é um resultado, não uma exceção:
 * consulte `ehValida()` e `erros`.
 */
final readonly class ResultadoPostagem
{
    /**
     * @param list<ErroVipp> $erros
     * @param array<array-key, mixed> $resposta resposta bruta, sem o bloco `PerfilVipp` (o ViPP devolve o token)
     */
    public function __construct(
        public StatusPostagem $status,
        public ?VolumePostado $volume,
        public array $erros,
        public ?string $etiquetaEnviada,
        public ?string $servicoEct,
        public int $httpStatus,
        public array $resposta = [],
    ) {}

    /**
     * @param array<array-key, mixed> $resposta
     */
    public static function fromResposta(array $resposta, int $httpStatus, ?string $etiquetaEnviada = null): self
    {
        unset($resposta['PerfilVipp']);

        $volumes = Leitor::lista($resposta, 'Volumes');
        $status = StatusPostagem::tryFrom(Leitor::texto($resposta, 'StatusPostagem') ?? '')
            ?? (Leitor::inteiro($resposta, 'StPostagem') === 1 ? StatusPostagem::Valida : StatusPostagem::Invalida);

        return new self(
            status: $status,
            volume: $volumes === [] ? null : VolumePostado::fromArray($volumes[0]),
            erros: array_map(ErroVipp::fromArray(...), Leitor::lista($resposta, 'ListaErros')),
            etiquetaEnviada: $etiquetaEnviada,
            servicoEct: Leitor::texto(Leitor::objeto($resposta, 'Servico'), 'ServicoECT'),
            httpStatus: $httpStatus,
            resposta: $resposta,
        );
    }

    public function ehValida(): bool
    {
        return $this->status === StatusPostagem::Valida;
    }

    /**
     * Etiqueta registrada no ViPP (null se a postagem for inválida).
     */
    public function etiqueta(): ?string
    {
        return $this->ehValida() ? $this->volume?->etiqueta : null;
    }

    /**
     * True quando foi enviada uma etiqueta e o ViPP registrou OUTRA. Aconteceu na homologação com o perfil 9363,
     * que gera o número sozinho; em produção depende da configuração do perfil. Trate como alerta.
     */
    public function etiquetaDivergente(): bool
    {
        $registrada = $this->etiqueta();

        return $this->etiquetaEnviada !== null && $registrada !== null && $registrada !== $this->etiquetaEnviada;
    }

    /**
     * Mensagens de erro prontas para exibir ou registrar em log.
     *
     * @return list<string>
     */
    public function mensagensDeErro(): array
    {
        return array_map(static fn(ErroVipp $e): string => (string) $e, $this->erros);
    }
}
