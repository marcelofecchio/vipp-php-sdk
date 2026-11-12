<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Resultado;

use MarceloFecchio\Vipp\Support\Leitor;
use Stringable;

/**
 * Item de `ListaErros`. Códigos observados: 91 (NumeroContrato/NumeroCartao obrigatórios no perfil, visto em produção,
 * sempre com a descrição vazia), 1000/1010/1020 (credenciais ausentes), 1002 (perfil sem acesso), 1030 (usuário ou
 * senha inválidos), 1100 (cartão enviado sem contrato), 1310/1320/1330 (nome/endereço/CEP), 1500 (data da NF),
 * 2002 (DV da etiqueta), 2200 (descrição do item), 2222 (valor do item).
 *
 * Quando o ViPP manda a descrição vazia, `descricao` recebe um texto do SDK e `semDescricao` fica true.
 */
final readonly class ErroVipp implements Stringable
{
    public const int CONTRATO_OBRIGATORIO = 91;

    /** Campos que o ViPP devolve com o erro 91 (nomes diferentes das tags `NrContrato`/`NrCartao` do payload). */
    public const array CAMPOS_CONTRATO = ['NumeroContrato', 'NumeroCartao'];

    public function __construct(
        public ?int $codigoErro,
        public ?string $campo,
        public string $descricao,
        public ?int $tipoErro = null,
        public ?string $descricaoTipoErro = null,
        public bool $semDescricao = false,
    ) {}

    /**
     * @param array<array-key, mixed> $dados
     */
    public static function fromArray(array $dados): self
    {
        $codigo = Leitor::inteiro($dados, 'CodigoErro');
        $descricao = Leitor::texto($dados, 'Descricao');

        return new self(
            codigoErro: $codigo,
            campo: Leitor::texto($dados, 'Campo'),
            descricao: $descricao ?? self::descricaoPadrao($codigo),
            tipoErro: Leitor::inteiro($dados, 'TipoErro'),
            descricaoTipoErro: Leitor::texto($dados, 'DescricaoTipoErro'),
            semDescricao: $descricao === null,
        );
    }

    /**
     * Erro 91 em `NumeroContrato`/`NumeroCartao`: o perfil exige `ContratoEct` com contrato e cartão.
     */
    public function ehContratoObrigatorio(): bool
    {
        return $this->codigoErro === self::CONTRATO_OBRIGATORIO && in_array($this->campo, self::CAMPOS_CONTRATO, true);
    }

    public function __toString(): string
    {
        return sprintf('[%s] %s%s', $this->codigoErro ?? '?', $this->campo === null ? '' : $this->campo.': ', $this->descricao);
    }

    private static function descricaoPadrao(?int $codigo): string
    {
        return $codigo === self::CONTRATO_OBRIGATORIO
            ? 'obrigatório neste perfil (erro 91 do ViPP, sem descrição); informe ContratoEct com NrContrato e NrCartao.'
            : 'Erro sem descrição';
    }
}
