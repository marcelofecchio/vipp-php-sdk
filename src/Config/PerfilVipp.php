<?php

declare(strict_types=1);

namespace MarceloFecchio\Vipp\Config;

use MarceloFecchio\Vipp\Exceptions\ConfiguracaoException;
use SensitiveParameter;

/**
 * Credenciais do ViPP + Perfil de Importação (Nota 1). O perfil define contrato, serviço e regras de postagem.
 * O token nunca aparece em `var_dump`/`print_r`.
 */
final readonly class PerfilVipp
{
    public function __construct(
        public string $usuario,
        #[SensitiveParameter]
        public string $token,
        public string $idPerfil,
    ) {
        if (trim($usuario) === '' || trim($token) === '' || trim($idPerfil) === '') {
            throw new ConfiguracaoException('Informe usuário, token e id do perfil do ViPP.');
        }

        if (preg_match('/^\d{1,10}$/', $idPerfil) !== 1) {
            throw new ConfiguracaoException('O id do perfil do ViPP deve ser numérico (até 10 dígitos).');
        }
    }

    /**
     * @return array{Usuario: string, Token: string, IdPerfil: string}
     */
    public function toArray(): array
    {
        return ['Usuario' => $this->usuario, 'Token' => $this->token, 'IdPerfil' => $this->idPerfil];
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['usuario' => $this->usuario, 'token' => '***', 'idPerfil' => $this->idPerfil];
    }
}
