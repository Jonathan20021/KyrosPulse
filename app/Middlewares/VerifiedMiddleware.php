<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class VerifiedMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): void
    {
        $user = Auth::user();
        if (!$user) {
            Response::redirect(url('/login'));
            return;
        }

        if (empty($user['email_verified_at'])) {
            Session::flash('warning', 'Verifica tu correo electronico para acceder a esta seccion.');
            Response::redirect(url('/email/verify-notice'));
            return;
        }

        $next();
    }
}
