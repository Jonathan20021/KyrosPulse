<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Encapsula el request HTTP entrante.
 */
final class Request
{
    private string $method;
    private string $path;
    private array $query;
    private array $body;
    private array $files;
    private array $headers;
    private array $server;
    private ?string $rawBody = null;

    public function __construct()
    {
        $this->method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->path    = $this->parsePath();
        $this->query   = $_GET ?? [];
        $this->body    = $_POST ?? [];
        $this->files   = $_FILES ?? [];
        $this->server  = $_SERVER ?? [];
        $this->headers = $this->parseHeaders();

        // Method spoofing via _method
        if ($this->method === 'POST' && isset($this->body['_method'])) {
            $spoofed = strtoupper((string) $this->body['_method']);
            if (in_array($spoofed, ['PUT', 'PATCH', 'DELETE'], true)) {
                $this->method = $spoofed;
            }
        }

        // JSON body
        if ($this->isJson()) {
            $raw = $this->rawBody();
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $this->body = array_merge($this->body, $decoded);
                }
            }
        }
    }

    private function parsePath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // SCRIPT_NAME apunta a public/index.php. Calculamos dos posibles "bases":
        //   1. Directorio del script (.../KyrosPulse/public)  -> cuando se entra directo a /KyrosPulse/public/...
        //   2. Padre cuando termina en /public (.../KyrosPulse) -> cuando .htaccess raiz reescribe /KyrosPulse/... a /KyrosPulse/public/...
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptDir  = str_replace('\\', '/', dirname($scriptName));

        $candidates = [];
        if ($scriptDir !== '' && $scriptDir !== '/') {
            $candidates[] = $scriptDir;
            if (str_ends_with($scriptDir, '/public')) {
                $candidates[] = substr($scriptDir, 0, -strlen('/public'));
            }
        }

        foreach ($candidates as $base) {
            if ($base !== '' && $base !== '/' && str_starts_with($path, $base)) {
                $path = substr($path, strlen($base));
                break;
            }
        }

        $path = '/' . trim($path, '/');
        return $path === '' ? '/' : $path;
    }

    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = strtolower(str_replace('_', '-', $key));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    public function method(): string { return $this->method; }
    public function path(): string { return $this->path; }
    public function query(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) return $this->query;
        return $this->query[$key] ?? $default;
    }

    public function input(string $key = null, mixed $default = null): mixed
    {
        $merged = array_merge($this->query, $this->body);
        if ($key === null) return $merged;
        return $merged[$key] ?? $default;
    }

    public function post(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) return $this->body;
        return $this->body[$key] ?? $default;
    }

    public function only(array $keys): array
    {
        $all = $this->input();
        return array_intersect_key($all, array_flip($keys));
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function header(string $name, mixed $default = null): mixed
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function isJson(): bool
    {
        $ct = (string) $this->header('content-type', '');
        return str_contains(strtolower($ct), 'application/json');
    }

    public function expectsJson(): bool
    {
        $accept = (string) $this->header('accept', '');
        return str_contains($accept, 'application/json') || $this->isJson() || $this->isAjax();
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('x-requested-with', '')) === 'xmlhttprequest';
    }

    public function rawBody(): string
    {
        if ($this->rawBody === null) {
            $this->rawBody = (string) file_get_contents('php://input');
        }
        return $this->rawBody;
    }

    public function ip(): string
    {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            $_SERVER['HTTP_X_REAL_IP'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ];
        foreach ($candidates as $ip) {
            if (!$ip) continue;
            $first = trim(explode(',', $ip)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }
        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        return (string) ($this->server['HTTP_USER_AGENT'] ?? '');
    }
}
