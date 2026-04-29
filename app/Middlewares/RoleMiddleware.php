<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

final class RoleMiddleware implements Middleware
{
    public function __construct(private string $roles = '') {}

    public function handle(Request $request, callable $next): void
    {
        $allowed = array_filter(array_map('trim', explode(',', $this->roles)));

        if (Auth::isSuperAdmin()) {
            $next();
            return;
        }

        if (!$allowed || !Auth::hasAnyRole($allowed)) {
            if ($request->expectsJson()) {
                Response::json(['error' => 'No autorizado.'], 403);
                return;
            }
            http_response_code(403);
            echo '<h1>403 - No tienes permisos para acceder a esta seccion</h1>';
            return;
        }

        $next();
    }
}
