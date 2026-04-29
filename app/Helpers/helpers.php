<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Session;
use App\Core\Tenant;

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim((string) Config::get('app.url', ''), '/');
        if ($path === '' || $path === '/') {
            return $base;
        }
        if (preg_match('#^https?://#', $path)) {
            return $path;
        }
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): string
    {
        return e((string) Session::getOldInput($key, $default));
    }
}

if (!function_exists('flash')) {
    function flash(string $key, mixed $default = null): mixed
    {
        return Session::flash($key) ?? $default;
    }
}

if (!function_exists('errors')) {
    function errors(): array
    {
        return (array) (Session::flash('errors') ?? []);
    }
}

if (!function_exists('error_for')) {
    function error_for(string $field, array $errors): ?string
    {
        if (!isset($errors[$field])) return null;
        $err = $errors[$field];
        return is_array($err) ? ($err[0] ?? null) : (string) $err;
    }
}

if (!function_exists('auth')) {
    function auth(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('auth_id')) {
    function auth_id(): ?int
    {
        return Auth::id();
    }
}

if (!function_exists('tenant')) {
    function tenant(): ?array
    {
        return Tenant::current();
    }
}

if (!function_exists('tenant_id')) {
    function tenant_id(): ?int
    {
        return Tenant::id();
    }
}

if (!function_exists('current_url')) {
    function current_url(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        return "$scheme://$host$uri";
    }
}

if (!function_exists('uuid4')) {
    function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (!function_exists('slugify')) {
    function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text) ?: '';
        $text = iconv('utf-8', 'us-ascii//TRANSLIT//IGNORE', $text);
        $text = preg_replace('~[^-\w]+~', '', (string) $text) ?: '';
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text) ?: '';
        $text = strtolower($text);
        return $text === '' ? 'item' : $text;
    }
}

if (!function_exists('format_currency')) {
    function format_currency(float $amount, string $currency = 'USD'): string
    {
        $symbol = match (strtoupper($currency)) {
            'USD' => '$',
            'EUR' => '€',
            'DOP' => 'RD$',
            'MXN' => 'MX$',
            'COP' => 'COL$',
            default => $currency . ' ',
        };
        return $symbol . number_format($amount, 2, '.', ',');
    }
}

if (!function_exists('time_ago')) {
    function time_ago(string $datetime): string
    {
        $time = strtotime($datetime);
        if (!$time) return '';
        $diff = time() - $time;
        if ($diff < 60) return 'hace un momento';
        if ($diff < 3600) return 'hace ' . floor($diff / 60) . ' min';
        if ($diff < 86400) return 'hace ' . floor($diff / 3600) . ' h';
        if ($diff < 2592000) return 'hace ' . floor($diff / 86400) . ' d';
        return date('Y-m-d', $time);
    }
}

if (!function_exists('json_response')) {
    function json_response(array $data, int $status = 200): never
    {
        \App\Core\Response::json($data, $status);
        exit;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $to, int $status = 302): never
    {
        \App\Core\Response::redirect(url($to), $status);
        exit;
    }
}
