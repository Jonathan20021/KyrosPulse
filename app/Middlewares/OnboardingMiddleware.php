<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;

/**
 * Redirige tenants nuevos al wizard de onboarding.
 *
 * Solo dispara en /dashboard (entrada principal post-login). NO en
 * /onboarding/* (evita loop), /admin (super admin no necesita), /logout,
 * y endpoints JSON/API (no son navegacion humana).
 *
 * El tenant debe tener: onboarding_completed_at IS NULL y onboarding_skipped = 0.
 */
final class OnboardingMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): void
    {
        if (!Auth::check()) {
            $next();
            return;
        }
        $path = $request->path();

        // No redirigir desde paginas que ya son parte del onboarding o auth
        $skipPaths = ['/onboarding', '/logout', '/login'];
        foreach ($skipPaths as $p) {
            if (str_starts_with($path, $p)) {
                $next();
                return;
            }
        }
        // No bloquear JSON / API
        if ($request->expectsJson()) {
            $next();
            return;
        }
        // Super admin no necesita onboarding (no tiene tenant)
        if (Auth::isSuperAdmin() || Tenant::id() === null) {
            $next();
            return;
        }

        try {
            $tenant = Tenant::current();
            $needsOnboarding = $tenant
                && empty($tenant['onboarding_completed_at'])
                && empty($tenant['onboarding_skipped']);
            if ($needsOnboarding) {
                Response::redirect(url('/onboarding'));
                return;
            }
        } catch (\Throwable) {
            // Si DB esta intermitente, no bloqueamos la app
        }

        $next();
    }
}
