<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp;

use MarceloFecchio\Vipp\Config\PerfilVipp;
use MarceloFecchio\Vipp\Enum\BuscarPor;
use MarceloFecchio\Vipp\Exceptions\AutenticacaoException;
use MarceloFecchio\Vipp\Exceptions\HttpException;
use MarceloFecchio\Vipp\Exceptions\RespostaInvalidaException;
use MarceloFecchio\Vipp\Exceptions\TransporteException;
use MarceloFecchio\Vipp\Exceptions\ValidacaoException;
use MarceloFecchio\Vipp\Postagem\Postagem;
use MarceloFecchio\Vipp\Resultado\ResultadoBloqueio;
use MarceloFecchio\Vipp\Resultado\ResultadoConexao;
use MarceloFecchio\Vipp\Resultado\ResultadoExclusao;
use MarceloFecchio\Vipp\Resultado\ResultadoPostagem;
use MarceloFecchio\Vipp\Resultado\SituacaoPostagem;
use MarceloFecchio\Vipp\Validation\Violacao;

/**
 * Contrato do cliente ViPP. Dependa desta interface (e não de `VippClient`) para poder trocar pelo `VippFake` nos testes.
 */
interface VippClientInterface
{
    /**
     * Envia uma postagem ao ViPP.
     *
     * @param PerfilVipp|null $perfil sobrescreve o perfil da configuração só nesta chamada
     *
     * @throws ValidacaoException dados reprovados na validação local (nada foi enviado)
     * @throws AutenticacaoException usuário, token ou perfil recusados (HTTP 401)
     * @throws HttpException outros erros HTTP (429, 5xx etc.)
     * @throws TransporteException falha de rede
     * @throws RespostaInvalidaException resposta fora do formato esperado
     */
    public function postarObjeto(Postagem $postagem, ?PerfilVipp $perfil = null): ResultadoPostagem;

    /**
     * Exclui uma postagem pela etiqueta (`DeleteConhecimento`). O ViPP só exclui objetos pré-postados com status
     * "aguardando liberação"; para qualquer outra etiqueta responde 200 com tudo zerado (`excluida()` falso).
     * A postagem criada pelo `PostarObjeto` fica "Liberado para download": bloqueie antes com `bloquearPostagem()`.
     * A etiqueta excluída não volta a ficar livre: é preciso refazer a postagem.
     *
     * @param PerfilVipp|null $perfil sobrescreve o perfil da configuração só nesta chamada (usuário e token)
     *
     * @throws ValidacaoException etiqueta com formato ou dígito verificador inválido (nada foi enviado)
     * @throws AutenticacaoException usuário ou token recusados (HTTP 401)
     * @throws HttpException outros erros HTTP
     * @throws TransporteException falha de rede
     * @throws RespostaInvalidaException resposta fora do formato esperado
     */
    public function excluirPostagem(string $etiqueta, ?PerfilVipp $perfil = null): ResultadoExclusao;

    /**
     * Bloqueia o download da postagem (SOAP `BloquearDownloadConhecimento`), o que a deixa "aguardando liberação" e
     * permite excluí-la com `excluirPostagem()` (fluxo confirmado em produção com o bloqueio feito na tela do ViPP).
     * `confirmado()` só diz que o ViPP aceitou o pedido: confira o efeito tentando a exclusão.
     *
     * @param string|null $descricao motivo opcional, gravado no ViPP
     * @param PerfilVipp|null $perfil sobrescreve o perfil da configuração só nesta chamada
     *
     * @throws ValidacaoException etiqueta com formato ou dígito verificador inválido (nada foi enviado)
     * @throws AutenticacaoException credencial recusada (o SOAP responde HTTP 200 com o erro em `ListaErros`)
     * @throws HttpException SOAP Fault (HTTP 500) e outros erros HTTP
     * @throws TransporteException falha de rede
     * @throws RespostaInvalidaException resposta fora do formato esperado
     */
    public function bloquearPostagem(string $etiqueta, ?string $descricao = null, ?PerfilVipp $perfil = null): ResultadoBloqueio;

    /**
     * Libera de novo o download de uma postagem bloqueada (SOAP `LiberarDownloadConhecimento`). Uma postagem liberada
     * não pode ser excluída.
     *
     * @throws ValidacaoException etiqueta com formato ou dígito verificador inválido (nada foi enviado)
     * @throws AutenticacaoException credencial recusada
     * @throws HttpException SOAP Fault (HTTP 500) e outros erros HTTP
     * @throws TransporteException falha de rede
     * @throws RespostaInvalidaException resposta fora do formato esperado
     */
    public function liberarPostagem(string $etiqueta, ?PerfilVipp $perfil = null): ResultadoBloqueio;

    /**
     * Confere usuário e token sem criar postagem (usa a consulta `GetSituacaoPostagem` com uma etiqueta que não existe).
     * Credencial recusada volta em `ResultadoConexao::$autenticado` falso, não como exceção. O `IdPerfil` NÃO é conferido.
     *
     * @param PerfilVipp|null $perfil confere outro perfil em vez do da configuração
     *
     * @throws HttpException outros erros HTTP (429, 5xx etc.)
     * @throws TransporteException falha de rede
     */
    public function verificarCredenciais(?PerfilVipp $perfil = null): ResultadoConexao;

    /**
     * Consulta a situação de uma postagem (`GetSituacaoPostagem`, somente leitura). Devolve lista vazia quando o ViPP não
     * encontra nada. Aceita UM valor por chamada.
     *
     * Útil para conferir uma exportação que ficou sem resposta (timeout): consulte pela referência que você enviou em
     * `Volume::$codigoBarraVolume`, com `BuscarPor::Referencia`.
     *
     * @param bool $completo true traz também os dados da postagem (`PostagensRastreio`, `idConhecimento`)
     * @param PerfilVipp|null $perfil sobrescreve o perfil da configuração só nesta chamada (usuário e token)
     *
     * @return list<SituacaoPostagem>
     *
     * @throws ValidacaoException valor vazio ou etiqueta com formato ou dígito verificador inválido
     * @throws AutenticacaoException usuário ou token recusados (HTTP 401)
     * @throws HttpException outros erros HTTP
     * @throws TransporteException falha de rede
     * @throws RespostaInvalidaException resposta fora do formato esperado
     */
    public function situacaoPostagem(
        string $valor,
        BuscarPor $buscarPor = BuscarPor::Etiqueta,
        bool $completo = false,
        ?PerfilVipp $perfil = null,
    ): array;

    /**
     * Roda só a validação local, sem enviar nada.
     *
     * @return list<Violacao>
     */
    public function validar(Postagem $postagem): array;
}
