<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

final class SuperAdminMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): void
    {
        if (!Auth::check() || !Auth::isSuperAdmin()) {
            if ($request->expectsJson()) {
                Response::json(['error' => 'Acceso restringido.'], 403);
                return;
            }
            http_response_code(403);
            echo '<h1>403 - Acceso restringido al Super Admin</h1>';
            return;
        }
        $next();
    }
}
