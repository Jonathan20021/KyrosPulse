<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

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
        $next();
    }
}
