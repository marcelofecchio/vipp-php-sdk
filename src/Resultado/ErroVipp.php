<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Resultado;

use MarceloFecchio\Vipp\Support\Leitor;
use Stringable;

/**
 * Item de `ListaErros`. Códigos observados: 91 (NumeroContrato/NumeroCartao obrigatórios no perfil; vem com a
 * descrição vazia, exibida como "Erro sem descrição"), 1000/1010/1020 (credenciais ausentes), 1002 (perfil sem acesso),
 * 1030 (usuário ou senha inválidos), 1310/1320/1330 (nome/endereço/CEP), 1500 (data da NF), 2002 (DV da etiqueta),
 * 2200 (descrição do item), 2222 (valor do item).
 */
final readonly class ErroVipp implements Stringable
{
    public function __construct(
        public ?int $codigoErro,
        public ?string $campo,
        public string $descricao,
        public ?int $tipoErro = null,
        public ?string $descricaoTipoErro = null,
    ) {}

    /**
     * @param array<array-key, mixed> $dados
     */
    public static function fromArray(array $dados): self
    {
        return new self(
            codigoErro: Leitor::inteiro($dados, 'CodigoErro'),
            campo: Leitor::texto($dados, 'Campo'),
            descricao: Leitor::texto($dados, 'Descricao') ?? 'Erro sem descrição',
            tipoErro: Leitor::inteiro($dados, 'TipoErro'),
            descricaoTipoErro: Leitor::texto($dados, 'DescricaoTipoErro'),
        );
    }

    public function __toString(): string
    {
        return sprintf('[%s] %s%s', $this->codigoErro ?? '?', $this->campo === null ? '' : $this->campo.': ', $this->descricao);
    }
}
