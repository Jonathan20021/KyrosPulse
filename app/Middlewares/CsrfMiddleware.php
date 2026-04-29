<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class CsrfMiddleware implements Middleware
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, callable $next): void
    {
        if (in_array($request->method(), self::SAFE_METHODS, true)) {
            $next();
            return;
        }

        // Para webhooks externos, bypass via cabecera o ruta
        $path = $request->path();
        if (str_starts_with($path, '/webhooks/')) {
            $next();
            return;
        }

        $token = (string) ($request->post('_csrf') ?? $request->header('x-csrf-token', ''));
        if (!Csrf::validate($token)) {
            if ($request->expectsJson()) {
                Response::json(['error' => 'Token CSRF invalido.'], 419);
                return;
            }
            Session::flash('error', 'Token de seguridad expirado o invalido. Por favor, vuelve a intentarlo.');
            Response::back();
            return;
        }

        $next();
    }
}
