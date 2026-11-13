<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp;

use MarceloFecchio\Vipp\Config\PerfilVipp;
use MarceloFecchio\Vipp\Exceptions\AutenticacaoException;
use MarceloFecchio\Vipp\Exceptions\HttpException;
use MarceloFecchio\Vipp\Exceptions\RespostaInvalidaException;
use MarceloFecchio\Vipp\Exceptions\TransporteException;
use MarceloFecchio\Vipp\Exceptions\ValidacaoException;
use MarceloFecchio\Vipp\Postagem\Postagem;
use MarceloFecchio\Vipp\Resultado\ResultadoExclusao;
use MarceloFecchio\Vipp\Resultado\ResultadoPostagem;
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
     * Roda só a validação local, sem enviar nada.
     *
     * @return list<Violacao>
     */
    public function validar(Postagem $postagem): array;
}
