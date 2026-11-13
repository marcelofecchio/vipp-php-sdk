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
