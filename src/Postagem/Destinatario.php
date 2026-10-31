<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Postagem;

use MarceloFecchio\Vipp\Support\Normaliza;

/**
 * Destinatário do objeto. Nome, endereço, número, bairro, cidade, UF e CEP são obrigatórios (tabela "Destinatario" do PDF).
 * CEP, telefones e CPF/CNPJ podem vir formatados: o construtor remove a pontuação.
 */
final readonly class Destinatario
{
    public ?string $nome;
    public ?string $endereco;
    public ?string $numero;
    public ?string $bairro;
    public ?string $cidade;
    public ?string $uf;
    public ?string $cep;
    public ?string $complemento;
    public ?string $cnpjCpf;
    public ?string $ieRg;
    public ?string $segundaLinhaDestinatario;
    public ?string $telefone;
    public ?string $celular;
    public ?string $email;

    public function __construct(
        string $nome,
        string $endereco,
        string $numero,
        string $bairro,
        string $cidade,
        string $uf,
        string $cep,
        ?string $complemento = null,
        ?string $cnpjCpf = null,
        ?string $ieRg = null,
        ?string $segundaLinhaDestinatario = null,
        ?string $telefone = null,
        ?string $celular = null,
        ?string $email = null,
    ) {
        $this->nome = Normaliza::texto($nome);
        $this->endereco = Normaliza::texto($endereco);
        $this->numero = Normaliza::texto($numero);
        $this->bairro = Normaliza::texto($bairro);
        $this->cidade = Normaliza::texto($cidade);
        $this->uf = Normaliza::codigo($uf);
        $this->cep = Normaliza::digitos($cep);
        $this->complemento = Normaliza::texto($complemento);
        $this->cnpjCpf = Normaliza::documento($cnpjCpf);
        $this->ieRg = Normaliza::texto($ieRg);
        $this->segundaLinhaDestinatario = Normaliza::texto($segundaLinhaDestinatario);
        $this->telefone = Normaliza::digitos($telefone);
        $this->celular = Normaliza::digitos($celular);
        $this->email = Normaliza::texto($email);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'CnpjCpf' => $this->cnpjCpf,
            'IeRg' => $this->ieRg,
            'Nome' => $this->nome,
            'SegundaLinhaDestinatario' => $this->segundaLinhaDestinatario,
            'Endereco' => $this->endereco,
            'Numero' => $this->numero,
            'Complemento' => $this->complemento,
            'Bairro' => $this->bairro,
            'Cidade' => $this->cidade,
            'UF' => $this->uf,
            'Cep' => $this->cep,
            'Telefone' => $this->telefone,
            'Celular' => $this->celular,
            'Email' => $this->email,
        ], static fn(?string $v): bool => $v !== null);
    }
}
