<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Validation;

use MarceloFecchio\Vipp\Exceptions\ValidacaoException;
use MarceloFecchio\Vipp\Postagem\ContratoEct;
use MarceloFecchio\Vipp\Postagem\DeclaracaoConteudo;
use MarceloFecchio\Vipp\Postagem\Destinatario;
use MarceloFecchio\Vipp\Postagem\ItemConteudo;
use MarceloFecchio\Vipp\Postagem\NotaFiscal;
use MarceloFecchio\Vipp\Postagem\Postagem;
use MarceloFecchio\Vipp\Postagem\Volume;
use MarceloFecchio\Vipp\ValueObject\Dinheiro;

/**
 * Regras determinísticas do PDF (tabela de campos e Notas 2 a 17), checadas antes do envio.
 *
 * A homologação aceita muita coisa que os Correios/SEFAZ recusam depois (emoji, item acima de R$ 100 mil,
 * CPF inválido, declaração sem itens). Validar aqui evita postagens que seriam canceladas mais tarde.
 * Regras que dependem do Perfil de Importação (contrato, serviço) ficam a cargo do ViPP.
 */
final class ValidadorPostagem
{
    public const array UFS = [
        'AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA',
        'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO',
    ];

    /** Emoji e símbolos são recusados pela SEFAZ; ver ItemConteudo::CARACTERES_PERMITIDOS. */
    private const string DESCRICAO_PERMITIDA = '/^['.ItemConteudo::CARACTERES_PERMITIDOS.']+$/u';

    private const int LIMITE_ITEM_CENTAVOS = 10_000_000;

    private const int LIMITE_DECLARACAO_CENTAVOS = 20_000_000;

    /** @var list<Violacao> */
    private array $violacoes = [];

    /**
     * @param bool $exigirContrato exige `ContratoEct` com `NrContrato` e `NrCartao`. Em produção, perfis podem
     *                             recusar a postagem sem eles (erro 91), embora o manual os trate como opcionais.
     */
    public function __construct(private readonly bool $exigirContrato = false) {}

    /**
     * @return list<Violacao>
     */
    public function validar(Postagem $postagem): array
    {
        $this->violacoes = [];

        $this->destinatario($postagem->destinatario);
        $this->contrato($postagem->contratoEct);
        $this->maxDigitos('Servico.ServicoECT', $postagem->servicoEct, 6);
        foreach ($postagem->notasFiscais as $i => $nf) {
            $this->notaFiscal("NotasFiscais[{$i}]", $nf);
        }
        $this->volume('Volumes[0]', $postagem->volume);

        return $this->violacoes;
    }

    /**
     * @throws ValidacaoException
     */
    public function garantir(Postagem $postagem): void
    {
        $violacoes = $this->validar($postagem);
        if ($violacoes !== []) {
            throw new ValidacaoException($violacoes);
        }
    }

    private function destinatario(Destinatario $d): void
    {
        $p = 'Destinatario';
        $this->texto("{$p}.Nome", $d->nome, 50, obrigatorio: true);
        $this->texto("{$p}.Endereco", $d->endereco, 90, obrigatorio: true);
        $this->texto("{$p}.Numero", $d->numero, 15, obrigatorio: true);
        $this->texto("{$p}.Bairro", $d->bairro, 90, obrigatorio: true);
        $this->texto("{$p}.Cidade", $d->cidade, 50, obrigatorio: true);
        $this->texto("{$p}.Complemento", $d->complemento, 50);
        $this->texto("{$p}.SegundaLinhaDestinatario", $d->segundaLinhaDestinatario, 50);
        $this->texto("{$p}.IeRg", $d->ieRg, 15);
        $this->texto("{$p}.Email", $d->email, 100);

        if ($d->uf === null || !in_array($d->uf, self::UFS, true)) {
            $this->violar("{$p}.UF", 'informe a sigla de um estado brasileiro (2 letras, ex.: SP).');
        }

        $this->cep("{$p}.Cep", $d->cep);

        if ($d->cnpjCpf !== null && !Documento::valido($d->cnpjCpf)) {
            $this->violar("{$p}.CnpjCpf", 'CPF ou CNPJ inválido.');
        }

        $this->maxDigitos("{$p}.Telefone", $d->telefone, 20);

        if ($d->celular !== null && preg_match('/^[1-9]{2}9\d{8}$/', $d->celular) !== 1) {
            $this->violar("{$p}.Celular", 'use DDD + 9 dígitos, só números (ex.: 11937378999).');
        }

        if ($d->email !== null && filter_var($d->email, FILTER_VALIDATE_EMAIL) === false) {
            $this->violar("{$p}.Email", 'e-mail inválido.');
        }
    }

    private function contrato(?ContratoEct $c): void
    {
        if ($this->exigirContrato) {
            if ($c?->nrContrato === null) {
                $this->violar('ContratoEct.NrContrato', 'obrigatório (exigirContrato ligado): informe o número do contrato dos Correios.');
            }
            if ($c?->nrCartao === null) {
                $this->violar('ContratoEct.NrCartao', 'obrigatório (exigirContrato ligado): informe o cartão de postagem dos Correios.');
            }
        }

        if ($c === null) {
            return;
        }

        // Observado na homologação: cartão sem contrato dá o erro 1100 ("O Número do Cartão É Obrigatóriamente Numérico!").
        if ($c->nrCartao !== null && $c->nrContrato === null && !$this->exigirContrato) {
            $this->violar('ContratoEct.NrContrato', 'informe o contrato junto com o cartão de postagem.');
        }

        $this->maxDigitos('ContratoEct.NrContrato', $c->nrContrato, 10);
        $this->maxDigitos('ContratoEct.CodigoAdministrativo', $c->codigoAdministrativo, 8);
        $this->maxDigitos('ContratoEct.NrCartao', $c->nrCartao, 10);
    }

    private function notaFiscal(string $p, NotaFiscal $nf): void
    {
        $this->texto("{$p}.SerieNotaFiscal", $nf->serie, 5);
        $this->maxDigitos("{$p}.NrNotaFiscal", $nf->numero, 16);
        $this->texto("{$p}.ProtocoloNotaFiscal", $nf->protocolo, 48);
        $this->texto("{$p}.ObservacaoNotaFiscal", $nf->observacao, 1000);
        $this->naoNegativo("{$p}.VlrTotalNota", $nf->valorTotal);

        if ($nf->data !== null && !self::dataValida($nf->data)) {
            $this->violar("{$p}.DtNotaFiscal", 'use o formato DD/MM/AAAA (ou um DateTimeInterface).');
        }
    }

    private function volume(string $p, Volume $v): void
    {
        foreach (['Peso' => $v->peso, 'Altura' => $v->altura, 'Largura' => $v->largura, 'Comprimento' => $v->comprimento] as $campo => $valor) {
            if ($valor !== null && ($valor < 1 || $valor > 99_999)) {
                $this->violar("{$p}.{$campo}", 'deve ser um inteiro entre 1 e 99999 (peso em gramas, medidas em cm).');
            }
        }

        if ($v->etiqueta !== null && ($erro = Etiqueta::erro($v->etiqueta)) !== null) {
            $this->violar("{$p}.Etiqueta", $erro);
        }

        if ($v->codigoBarraCliente !== null && strlen($v->codigoBarraCliente) !== 44) {
            $this->violar("{$p}.CodigoBarraCliente", 'a chave da NF-e/DC-e deve ter 44 dígitos.');
        }

        $this->texto("{$p}.CodigoBarraVolume", $v->codigoBarraVolume, 255);
        $this->texto("{$p}.Conteudo", $v->conteudo, 70);
        $this->texto("{$p}.ContaLote", $v->contaLote, 50);
        $this->texto("{$p}.ChaveRoteamento", $v->chaveRoteamento, 100);
        $this->texto("{$p}.ObservacaoVisual", $v->observacaoVisual, 255);
        $this->texto("{$p}.ObservacaoQuatro", $v->observacaoQuatro, 255);
        $this->texto("{$p}.ObservacaoCinco", $v->observacaoCinco, 255);
        $this->naoNegativo("{$p}.ValorDeclarado", $v->valorDeclarado);
        $this->naoNegativo("{$p}.VlrACobrar", $v->valorACobrar);

        if ($v->posicaoVolume !== null && ($v->posicaoVolume < 1 || $v->posicaoVolume > 999)) {
            $this->violar("{$p}.PosicaoVolume", 'deve estar entre 1 e 999.');
        }

        foreach ($v->adicionais as $sigla) {
            if (preg_match('/^[A-Z]{2}$/', $sigla) !== 1) {
                $this->violar("{$p}.AdicionaisVolume", "sigla \"{$sigla}\" inválida; cada adicional tem 2 letras (ex.: AR).");
            }
        }
        if (strlen(implode(' ', $v->adicionais)) > 18) {
            $this->violar("{$p}.AdicionaisVolume", 'no máximo 18 caracteres (6 adicionais).');
        }

        $this->declaracao("{$p}.DeclaracaoConteudo", $v->declaracaoConteudo);
    }

    private function declaracao(string $p, DeclaracaoConteudo $dc): void
    {
        foreach (['DocumentoRemetente' => $dc->documentoRemetente, 'DocumentoDestinatario' => $dc->documentoDestinatario] as $campo => $doc) {
            if ($doc === null) {
                $this->violar("{$p}.{$campo}", 'obrigatório (CPF ou CNPJ).');
            } elseif (!Documento::valido($doc)) {
                $this->violar("{$p}.{$campo}", 'CPF ou CNPJ inválido.');
            }
        }

        if ($dc->itens === []) {
            $this->violar("{$p}.ItemConteudo", 'informe ao menos 1 item.');

            return;
        }

        foreach ($dc->itens as $i => $item) {
            $c = "{$p}.ItemConteudo[{$i}]";
            $descricao = $item->descricao;

            if ($descricao === null || mb_strlen($descricao) < 3) {
                $this->violar("{$c}.DescricaoConteudo", 'obrigatório, com ao menos 3 caracteres.');
            } elseif (mb_strlen($descricao) > 70) {
                $this->violar("{$c}.DescricaoConteudo", 'no máximo 70 caracteres.');
            } elseif (preg_match(self::DESCRICAO_PERMITIDA, $descricao) !== 1) {
                $this->violar("{$c}.DescricaoConteudo", 'use só letras, números, espaço e . , - / ( ) % : ; + (sem emoji ou símbolos).');
            }

            if ($item->quantidade < 1) {
                $this->violar("{$c}.Quantidade", 'deve ser maior que zero.');
            }

            $this->naoNegativo("{$c}.Valor", $item->valor);

            if ($item->total()->centavos > self::LIMITE_ITEM_CENTAVOS) {
                $this->violar("{$c}.Valor", 'quantidade × valor passa de R$ 100.000,00; a SEFAZ cancela a DC-e.');
            }
        }

        if ($dc->total()->centavos > self::LIMITE_DECLARACAO_CENTAVOS) {
            $this->violar("{$p}.ItemConteudo", 'a soma dos itens passa de R$ 200.000,00; a SEFAZ cancela a DC-e.');
        }
    }

    private function cep(string $campo, ?string $cep): void
    {
        if ($cep === null || preg_match('/^\d{8}$/', $cep) !== 1 || $cep === '00000000') {
            $this->violar($campo, 'obrigatório, com 8 dígitos (inclusive zeros à esquerda).');
        }
    }

    private function texto(string $campo, ?string $valor, int $maximo, bool $obrigatorio = false): void
    {
        if ($valor === null) {
            if ($obrigatorio) {
                $this->violar($campo, 'obrigatório.');
            }

            return;
        }

        if (mb_strlen($valor) > $maximo) {
            $this->violar($campo, "no máximo {$maximo} caracteres.");
        }
    }

    private function maxDigitos(string $campo, ?string $valor, int $maximo): void
    {
        if ($valor !== null && strlen($valor) > $maximo) {
            $this->violar($campo, "no máximo {$maximo} dígitos.");
        }
    }

    private function naoNegativo(string $campo, ?Dinheiro $valor): void
    {
        if ($valor !== null && $valor->centavos < 0) {
            $this->violar($campo, 'não pode ser negativo.');
        }
    }

    private static function dataValida(string $data): bool
    {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $data, $m) !== 1) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[1], (int) $m[3]);
    }

    private function violar(string $campo, string $mensagem): void
    {
        $this->violacoes[] = new Violacao($campo, $mensagem);
    }
}
