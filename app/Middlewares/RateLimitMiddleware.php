<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

/**
 * Rate limit basico almacenado en MySQL.
 * Uso: middleware('rate:login') o middleware('rate:api')
 */
final class RateLimitMiddleware implements Middleware
{
    public function __construct(private string $bucket = 'api') {}

    public function handle(Request $request, callable $next): void
    {
        $config = (array) config('app.rate_limit', []);
        $max    = (int) ($config[$this->bucket] ?? 60);
        $window = (int) ($config[$this->bucket . '_window'] ?? 60);

        $key  = $this->bucket . '|' . $request->ip() . '|' . $request->path();
        $hash = hash('sha256', $key);

        // Limpiar expirados
        Database::run("DELETE FROM `rate_limits` WHERE expires_at < NOW()");

        $row = Database::fetch("SELECT * FROM `rate_limits` WHERE key_hash = :h", ['h' => $hash]);

        if ($row) {
            $attempts = (int) $row['attempts'] + 1;
            if ($attempts > $max) {
                $retry = max(1, strtotime($row['expires_at']) - time());
                if ($request->expectsJson()) {
                    header("Retry-After: $retry");
                    Response::json(['error' => 'Demasiadas peticiones. Intenta mas tarde.'], 429);
                    return;
                }
                http_response_code(429);
                echo '<h1>429 - Demasiadas solicitudes</h1><p>Espera unos segundos y vuelve a intentar.</p>';
                return;
            }
            Database::run(
                "UPDATE `rate_limits` SET attempts = :a WHERE key_hash = :h",
                ['a' => $attempts, 'h' => $hash]
            );
        } else {
            Database::run(
                "INSERT INTO `rate_limits` (key_hash, attempts, expires_at) VALUES (:h, 1, :e)",
                ['h' => $hash, 'e' => date('Y-m-d H:i:s', time() + $window)]
            );
        }

        $next();
    }
}
