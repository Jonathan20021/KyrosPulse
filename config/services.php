<?php
/**
 * Configuracion de integraciones externas.
 */

return [
    'wasapi' => [
        'base_url'       => rtrim(env('WASAPI_BASE_URL', 'https://api-ws.wasapi.io/api/v1'), '/'),
        'api_key'        => env('WASAPI_API_KEY', ''),
        'webhook_secret' => env('WASAPI_WEBHOOK_SECRET', ''),
        'timeout'        => 30,
        'retry'          => 3,
    ],

    'resend' => [
        'api_key'    => env('RESEND_API_KEY', ''),
        'from'       => env('RESEND_FROM_EMAIL', 'no-reply@kyrosrd.com'),
        'reply_to'   => env('RESEND_REPLY_TO', ''),
        'api_url'    => 'https://api.resend.com/emails',
        'timeout'    => 20,
    ],

    'claude' => [
        'api_key'     => env('CLAUDE_API_KEY', ''),
        'model'       => env('CLAUDE_MODEL', 'claude-sonnet-4-6'),
        'api_url'     => env('CLAUDE_API_URL', 'https://api.anthropic.com/v1/messages'),
        'version'     => env('CLAUDE_VERSION', '2023-06-01'),
        'max_tokens'  => (int) env('CLAUDE_MAX_TOKENS', 2048),
        'timeout'     => 60,
    ],

    'openai' => [
        'api_key'     => env('OPENAI_API_KEY', ''),
        'model'       => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'api_url'     => env('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions'),
        'organization'=> env('OPENAI_ORG', ''),
        'max_tokens'  => (int) env('OPENAI_MAX_TOKENS', 2048),
        'timeout'     => 60,
    ],

    'ai' => [
        'default_provider' => env('AI_PROVIDER', 'claude'),
    ],

    /*
     * Tarifas por modelo en USD por millon de tokens (input / output).
     * Mantener actualizado cuando Anthropic / OpenAI ajusten precios.
     * Si un modelo no aparece aqui se asume costo 0 (tokens igual se cuentan).
     */
    'ai_pricing' => [
        // Claude 4.x
        'claude-haiku-4-5'             => ['in' => 1.00,  'out' => 5.00],
        'claude-haiku-4-5-20251001'    => ['in' => 1.00,  'out' => 5.00],
        'claude-sonnet-4-6'            => ['in' => 3.00,  'out' => 15.00],
        'claude-opus-4-7'              => ['in' => 15.00, 'out' => 75.00],
        // Claude 3.x (legacy)
        'claude-3-5-sonnet'            => ['in' => 3.00,  'out' => 15.00],
        'claude-3-5-haiku'             => ['in' => 0.80,  'out' => 4.00],
        'claude-3-opus'                => ['in' => 15.00, 'out' => 75.00],
        // OpenAI
        'gpt-4o-mini'                  => ['in' => 0.15,  'out' => 0.60],
        'gpt-4o'                       => ['in' => 2.50,  'out' => 10.00],
        'gpt-4-turbo'                  => ['in' => 10.00, 'out' => 30.00],
    ],
];
