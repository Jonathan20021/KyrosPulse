<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Tenant;
use App\Services\PlanService;

/**
 * Bloquea rutas cuando el plan del tenant no incluye la feature requerida.
 *
 * Uso en routes:
 *   $router->get('/settings/api-keys', ...)->middleware(['auth','tenant','plan:api_access']);
 *
 * Aliases configurados en Router::resolveMiddleware:
 *   plan:ai_enabled, plan:api_access, plan:advanced_reports.
 *
 * Cuando bloquea:
 *   - Requests HTML: redirect a /settings con flash de upgrade.
 *   - Requests JSON/API: 403 con motivo + nombre de feature.
 *   - Super admin siempre pasa.
 */
final class PlanFeatureMiddleware implements Middleware
{
    public function __construct(private string $feature = '') {}

    public function handle(Request $request, callable $next): void
    {
        if (!Auth::check() || Auth::isSuperAdmin()) {
            $next();
            return;
        }

        $tenantId = Tenant::id();
        if ($tenantId === null) {
            $next();
            return;
        }

        if (PlanService::can($tenantId, $this->feature)) {
            $next();
            return;
        }

        // Bloqueado: respuesta segun tipo de request.
        $message = PlanService::blockedMessage($this->feature);

        if ($request->expectsJson()) {
            Response::json([
                'error'   => 'plan_feature_blocked',
                'feature' => $this->feature,
                'message' => $message,
            ], 403);
            return;
        }

        Session::flash('error', $message);
        // Llevamos al usuario a /settings (plan & billing) para que sepa
        // que tiene que actualizar.
        Response::redirect(url('/settings'));
    }
}
