<?php

declare(strict_types=1);

// Configuração do marcelofecchio/vipp-php-sdk no Laravel.
// Publique com: php artisan vendor:publish --tag=vipp-config

return [
    // Mesma URL para homologação e produção: o ambiente é definido pelas credenciais.
    'base_url' => env('VIPP_BASE_URL', 'https://vpsrv.visualset.com.br'),

    // Credenciais fornecidas pela AGF. Homologação (públicas, do PDF): onbiws / 112233 / 9363.
    'usuario' => env('VIPP_USUARIO'),
    'token' => env('VIPP_TOKEN'),
    'id_perfil' => env('VIPP_ID_PERFIL'),

    // Segundos. Vale para o cliente HTTP criado pelo SDK (Guzzle).
    'timeout' => env('VIPP_TIMEOUT', 30),

    // Valida os dados localmente antes de enviar (recomendado).
    'validar_antes_de_enviar' => env('VIPP_VALIDAR_ANTES_DE_ENVIAR', true),
];
