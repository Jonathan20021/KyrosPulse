<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

final class GuestMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): void
    {
        if (Auth::check()) {
            Response::redirect(url('/dashboard'));
            return;
        }
        $next();
    }
}
