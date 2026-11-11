<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Postagem;

use MarceloFecchio\Vipp\Support\Normaliza;
use MarceloFecchio\Vipp\ValueObject\Dinheiro;

/**
 * Item da Declaração de Conteúdo. `valor` é o valor UNITÁRIO: a SEFAZ calcula quantidade × valor (Nota 17).
 */
final readonly class ItemConteudo
{
    /** Classe de caracteres aceita na descrição: letras (com acento), dígitos, espaço e pontuação simples. */
    public const string CARACTERES_PERMITIDOS = '\p{L}\p{N} .,\-\/()%:;+';

    public const int TAMANHO_MAXIMO_DESCRICAO = 70;

    public ?string $descricao;
    public Dinheiro $valor;

    public function __construct(
        string $descricao,
        public int $quantidade,
        Dinheiro|int|float|string $valor,
    ) {
        $this->descricao = Normaliza::texto($descricao);
        $this->valor = Dinheiro::de($valor, 'ItemConteudo.Valor');
    }

    /**
     * Deixa uma descrição livre (ex.: produto da NF-e) no formato aceito pela SEFAZ: troca os caracteres
     * não aceitos (`#`, `&`, aspas, `*`, emoji…) por espaço, junta espaços e corta em 70 caracteres.
     * Não garante o mínimo de 3 caracteres: a validação continua conferindo.
     */
    public static function limparDescricao(string $descricao): string
    {
        $limpa = (string) preg_replace('/[^'.self::CARACTERES_PERMITIDOS.']/u', ' ', $descricao);
        $limpa = trim((string) preg_replace('/\s+/u', ' ', $limpa));

        return rtrim(mb_substr($limpa, 0, self::TAMANHO_MAXIMO_DESCRICAO));
    }

    public function total(): Dinheiro
    {
        return $this->valor->multiplicar($this->quantidade);
    }

    /**
     * @return array{DescricaoConteudo: string, Quantidade: int, Valor: string}
     */
    public function toArray(): array
    {
        return [
            'DescricaoConteudo' => $this->descricao ?? '',
            'Quantidade' => $this->quantidade,
            'Valor' => $this->valor->formatar(),
        ];
    }
}
