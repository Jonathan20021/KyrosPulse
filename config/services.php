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
];
