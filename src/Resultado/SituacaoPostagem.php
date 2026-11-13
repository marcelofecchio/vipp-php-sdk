<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Resultado;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use MarceloFecchio\Vipp\Support\Leitor;
use MarceloFecchio\Vipp\ValueObject\Dinheiro;

/**
 * Situação de uma postagem no ViPP (`GetSituacaoPostagem`). `referencia` é o `CodigoBarraVolume` que você enviou
 * (o ViPP o devolve como `ObservacaoUm`). Campos que o ViPP não devolveu ficam nulos; o dado bruto está em `dados`.
 */
final readonly class SituacaoPostagem
{
    /**
     * @param array<array-key, mixed> $dados item bruto da resposta
     */
    public function __construct(
        public ?string $etiqueta,
        public ?string $referencia,
        public ?DateTimeImmutable $dataEntrada,
        public ?Dinheiro $valorPostagem,
        public ?int $prazoEntrega,
        public ?bool $prazoEstaContando,
        public ?bool $entregaDomiciliar,
        public ?bool $entregaSabados,
        public ?string $codigoServico,
        public ?string $descricaoServico,
        public ?string $nomeDestinatario,
        public ?string $cepDestinatario,
        public ?int $pesoReal,
        public ?string $unidadePostadora,
        public ?string $resultadoAfericao,
        public ?bool $etiquetaImpressa,
        public ?bool $arImpresso,
        public ?bool $declaracaoImpressa,
        public ?int $idConhecimento,
        public array $dados = [],
    ) {}

    /**
     * @param array<array-key, mixed> $dados
     */
    public static function fromArray(array $dados): self
    {
        $servico = Leitor::inteiro($dados, 'CodigoFinanceiroECT');
        $cep = Leitor::inteiro($dados, 'CEPDestinatario');

        return new self(
            etiqueta: Leitor::texto($dados, 'Etiqueta'),
            referencia: Leitor::texto($dados, 'ObservacaoUm'),
            dataEntrada: self::data(Leitor::texto($dados, 'DataDeEntradaNoVipp')),
            valorPostagem: Leitor::dinheiro($dados, 'ValorDaPostagem'),
            prazoEntrega: Leitor::inteiro($dados, 'PrazoDeEntrega'),
            prazoEstaContando: Leitor::simNao($dados, 'PrazoDeEntregaEstaContando'),
            entregaDomiciliar: Leitor::simNao($dados, 'EntegaNoDomicilio'),
            entregaSabados: Leitor::simNao($dados, 'EntegaAosSabados'),
            codigoServico: $servico === null ? null : str_pad((string) $servico, 5, '0', STR_PAD_LEFT),
            descricaoServico: Leitor::texto($dados, 'DescricaoFinanceiroECT'),
            nomeDestinatario: Leitor::texto($dados, 'NomeDestinatario'),
            cepDestinatario: $cep === null ? null : str_pad((string) $cep, 8, '0', STR_PAD_LEFT),
            pesoReal: Leitor::inteiro($dados, 'PesoReal'),
            unidadePostadora: Leitor::texto($dados, 'NomeFantasiaUnidadePostadora'),
            resultadoAfericao: Leitor::texto($dados, 'ResultadoAfericaoAgencia'),
            etiquetaImpressa: Leitor::simNao($dados, 'EtiquetaImpressa'),
            arImpresso: Leitor::simNao($dados, 'ARImpresso'),
            declaracaoImpressa: Leitor::simNao($dados, 'DeclaracaoDeConteudoImpressa'),
            idConhecimento: Leitor::inteiro(Leitor::objeto($dados, 'PostagensRastreio'), 'IdConhecimento'),
            dados: $dados,
        );
    }

    private static function data(?string $texto): ?DateTimeImmutable
    {
        if ($texto === null) {
            return null;
        }

        try {
            return new DateTimeImmutable($texto, new DateTimeZone('America/Sao_Paulo'));
        } catch (Exception) {
            return null;
        }
    }
}
