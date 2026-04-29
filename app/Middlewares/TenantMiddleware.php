<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Tenant;

final class TenantMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): void
    {
        if (Auth::isSuperAdmin()) {
            $next();
            return;
        }

        if (!Auth::check() || Tenant::id() === null) {
            if ($request->expectsJson()) {
                Response::json(['error' => 'Tenant no resuelto.'], 403);
                return;
            }
            Session::flash('error', 'Tu cuenta no esta asociada a una empresa.');
            Response::redirect(url('/login'));
            return;
        }

        $tenant = Tenant::current();
        if (!$tenant || in_array($tenant['status'] ?? '', ['suspended', 'cancelled', 'expired'], true)) {
            if ($request->expectsJson()) {
                Response::json(['error' => 'La empresa esta suspendida o expirada.'], 403);
                return;
            }
            Session::flash('error', 'Tu cuenta de empresa no esta activa.');
            Auth::logout();
            Response::redirect(url('/login'));
            return;
        }

        $next();
    }
}
