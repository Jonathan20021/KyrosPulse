<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Wrapper seguro de sesion con regeneracion y flash messages.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $cfg = Config::get('app.session');
        session_name((string) ($cfg['name'] ?? 'kyros_pulse_session'));

        $params = [
            'lifetime' => (int) ($cfg['lifetime'] ?? 120) * 60,
            'path'     => '/',
            'domain'   => '',
            'secure'   => (bool) ($cfg['secure'] ?? false),
            'httponly' => (bool) ($cfg['httponly'] ?? true),
            'samesite' => (string) ($cfg['samesite'] ?? 'Lax'),
        ];

        session_set_cookie_params($params);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();

        // Regenerar ID periodicamente
        if (!isset($_SESSION['_session_started'])) {
            session_regenerate_id(true);
            $_SESSION['_session_started'] = time();
        }
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function flash(string $key, mixed $value = null): mixed
    {
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }
        $val = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $val;
    }

    public static function setOldInput(array $input): void
    {
        $_SESSION['_old'] = $input;
    }

    public static function getOldInput(string $key, mixed $default = ''): mixed
    {
        $val = $_SESSION['_old'][$key] ?? $default;
        return $val;
    }

    public static function clearOldInput(): void
    {
        unset($_SESSION['_old']);
    }
}
