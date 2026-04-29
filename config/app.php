<?php
/**
 * Configuracion principal de la aplicacion Kyros Pulse.
 */

return [
    'name'      => env('APP_NAME', 'Kyros Pulse'),
    'env'       => env('APP_ENV', 'production'),
    'debug'     => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
    'url'       => rtrim(env('APP_URL', 'http://localhost'), '/'),
    'timezone'  => env('APP_TIMEZONE', 'America/Santo_Domingo'),
    'locale'    => env('APP_LOCALE', 'es'),
    'key'       => env('APP_KEY', ''),

    'paths' => [
        'root'      => dirname(__DIR__),
        'app'       => dirname(__DIR__) . '/app',
        'views'     => dirname(__DIR__) . '/app/Views',
        'storage'   => dirname(__DIR__) . '/storage',
        'uploads'   => dirname(__DIR__) . '/storage/uploads',
        'logs'      => dirname(__DIR__) . '/storage/logs',
        'cache'     => dirname(__DIR__) . '/storage/cache',
    ],

    'session' => [
        'lifetime'  => (int) env('SESSION_LIFETIME', 120),
        'name'      => env('SESSION_NAME', 'kyros_pulse_session'),
        'secure'    => filter_var(env('SESSION_SECURE', false), FILTER_VALIDATE_BOOLEAN),
        'httponly'  => filter_var(env('SESSION_HTTPONLY', true), FILTER_VALIDATE_BOOLEAN),
        'samesite'  => env('SESSION_SAMESITE', 'Lax'),
    ],

    'upload' => [
        'max_size'      => (int) env('UPLOAD_MAX_SIZE', 20971520),
        'allowed_mime'  => array_map('trim', explode(',', env('UPLOAD_ALLOWED_MIME', ''))),
    ],

    'rate_limit' => [
        'login'         => (int) env('RATE_LIMIT_LOGIN', 5),
        'login_window'  => (int) env('RATE_LIMIT_LOGIN_WINDOW', 300),
        'api'           => (int) env('RATE_LIMIT_API', 120),
        'api_window'    => (int) env('RATE_LIMIT_API_WINDOW', 60),
    ],

    'log' => [
        'level'     => env('LOG_LEVEL', 'debug'),
        'channel'   => env('LOG_CHANNEL', 'daily'),
    ],
];
