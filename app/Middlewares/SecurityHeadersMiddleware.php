<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Config;
use App\Core\Request;

/**
 * Endurece las respuestas HTTP con headers de seguridad estandar.
 *
 *   - Content-Security-Policy: limita orígenes de scripts/estilos/iframes.
 *   - Strict-Transport-Security: fuerza HTTPS por 6 meses (solo si secure session).
 *   - X-Frame-Options: previene clickjacking via iframe.
 *   - X-Content-Type-Options: previene MIME sniffing.
 *   - Referrer-Policy: limita info enviada a sitios cross-origin.
 *   - Permissions-Policy: deshabilita APIs sensibles del browser por defecto.
 *
 * Aplicado globalmente en index.php (todos los responses HTML/JSON).
 * En endpoints publicos (webhooks, menu) el middleware es transparente —
 * los headers no rompen JSON ni renderizado.
 */
final class SecurityHeadersMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): void
    {
        $this->apply($request);
        $next();
    }

    /**
     * Aplica headers tambien fuera del pipeline (llamado desde index.php
     * antes del dispatch para cubrir incluso 404 antes de cualquier route).
     */
    public static function apply(Request $request): void
    {
        if (headers_sent()) return;

        $secureCookies = (bool) Config::get('app.session.secure', false);
        $isHttps = ($_SERVER['HTTPS'] ?? '') === 'on'
                || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
                || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;

        // CSP: permisivo con CDNs ya usados (Tailwind, Alpine, SortableJS, fonts, Leaflet).
        // Si esto rompe algo, mejor relajamos puntos especificos en lugar de
        // desactivar la politica entera.
        $csp = "default-src 'self'; "
             . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://unpkg.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
             . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://unpkg.com https://cdn.jsdelivr.net; "
             . "font-src 'self' https://fonts.gstatic.com data:; "
             . "img-src 'self' data: https: blob:; "
             . "connect-src 'self' https:; "
             . "frame-ancestors 'self'; "
             . "base-uri 'self'; "
             . "form-action 'self' https://wa.me; "
             . "object-src 'none'";
        header('Content-Security-Policy: ' . $csp);

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        // geolocation=(self) permite que el portal del driver y el tracking
        // publico (mismo origen) lean GPS para reportar ubicacion en vivo.
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(self), payment=(), usb=(), interest-cohort=()');

        if ($isHttps || $secureCookies) {
            // 6 meses; includeSubDomains se omite por defecto para no romper
            // subdominios que aun no esten en HTTPS (cambia a 'includeSubDomains'
            // cuando todo este 100% en HTTPS y agrega 'preload' si quieres).
            header('Strict-Transport-Security: max-age=15552000');
        }

        // Endpoints API JSON: aseguramos no caching para evitar leaks
        $path = $request->path();
        if (str_starts_with($path, '/api/') || str_contains($path, '.json')) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }
    }
}
