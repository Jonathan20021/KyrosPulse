<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\SecurityService;

final class AuthMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): void
    {
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                Response::json(['error' => 'No autenticado.'], 401);
                return;
            }
            Session::flash('error', 'Inicia sesion para continuar.');
            Response::redirect(url('/login'));
            return;
        }

        // Session revocation check: si la session fue revocada desde otro device,
        // forzar logout. Tambien actualiza last_seen_at oportunisticamente.
        try {
            $sid = session_id();
            if ($sid && SecurityService::isSessionRevoked($sid)) {
                Auth::logout();
                Session::flash('error', 'Tu sesion fue cerrada remotamente. Inicia sesion de nuevo.');
                if ($request->expectsJson()) {
                    Response::json(['error' => 'session_revoked'], 401);
                    return;
                }
                Response::redirect(url('/login'));
                return;
            }
            // Touch last_seen (max 1x cada 60s para no spammear DB)
            $last = (int) Session::get('_session_touch_at', 0);
            if (time() - $last > 60) {
                SecurityService::touchSession($sid);
                Session::set('_session_touch_at', time());
            }
        } catch (\Throwable) {
            // No bloquear la request si la BD esta intermitente
        }

        $next();
    }
}
