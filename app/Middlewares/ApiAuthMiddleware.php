<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Models\ApiKey;
use App\Models\Tenant as TenantModel;
use App\Services\ApiKeyService;
use App\Services\ApiQuotaService;

/**
 * Auth para /api/v1/*. Acepta token en:
 *   - Header  Authorization: Bearer kp_live_xxx
 *   - Header  X-Api-Key: kp_live_xxx
 *
 * Tras autenticar, fija el tenant activo (App\Core\Tenant) y guarda la key
 * en globals para que controllers puedan inspeccionarla y loggear.
 *
 * Si la ruta declara middleware('apikey:agents.run'), tambien valida el scope.
 */
final class ApiAuthMiddleware implements Middleware
{
    public function __construct(private string $requiredScope = '') {}

    public function handle(Request $request, callable $next): void
    {
        $start = microtime(true);
        $requestId = self::uuid4();
        header('X-Request-Id: ' . $requestId);
        header('Content-Type: application/json; charset=utf-8');

        $bearer = self::extractBearer($request);
        if ($bearer === '') {
            $this->fail($request, $requestId, $start, 401, 'missing_credentials', 'Authorization header is required (Bearer kp_live_...).');
            return;
        }

        $key = ApiKeyService::verify($bearer);
        if (!$key) {
            $this->fail($request, $requestId, $start, 401, 'invalid_credentials', 'Invalid or revoked API key.');
            return;
        }

        // IP whitelist (si aplica)
        if (!ApiKeyService::ipAllowed($key, $request->ip())) {
            $this->fail($request, $requestId, $start, 403, 'ip_not_allowed', 'IP not in this key\'s allowlist.', $key);
            return;
        }

        // Scope check (si la ruta declaro uno)
        if ($this->requiredScope !== '' && !ApiKeyService::hasScope($key, $this->requiredScope)) {
            $this->fail($request, $requestId, $start, 403, 'insufficient_scope', 'API key lacks required scope: ' . $this->requiredScope, $key);
            return;
        }

        // Rate limit por key (global por minuto)
        $rate = ApiKeyService::rateLimit($key['key_hash'], 'global');
        header('X-RateLimit-Limit: '     . $rate['limit']);
        header('X-RateLimit-Remaining: ' . max(0, $rate['remaining']));
        if (!$rate['ok']) {
            header('Retry-After: ' . $rate['retry_after']);
            $this->fail($request, $requestId, $start, 429, 'rate_limit_exceeded', 'Rate limit exceeded. Retry after ' . $rate['retry_after'] . 's.', $key);
            return;
        }

        // Resolver tenant de la key y dejarlo activo
        $tenant = TenantModel::findById((int) $key['tenant_id']);
        if (!$tenant || (isset($tenant['is_active']) && !$tenant['is_active'])) {
            $this->fail($request, $requestId, $start, 403, 'tenant_inactive', 'Tenant is not active.', $key);
            return;
        }
        Tenant::setCurrent($tenant);

        // Cuota mensual del plan: si el tenant excedio, devolvemos 429 con
        // cabeceras informativas. Si no tiene acceso al API en su plan, 403.
        $quota = ApiQuotaService::consume((int) $tenant['id']);
        if (isset($quota['quota'])) {
            header('X-Quota-Limit: '     . $quota['quota']);
            header('X-Quota-Used: '      . ($quota['used']      ?? 0));
            header('X-Quota-Remaining: ' . ($quota['remaining'] ?? 0));
        }
        if (empty($quota['ok'])) {
            $reason = (string) ($quota['reason'] ?? 'quota');
            if ($reason === 'no_api_access') {
                $this->fail($request, $requestId, $start, 403, 'no_api_access', 'Your current plan does not include API access. Upgrade to enable.', $key);
                return;
            }
            $this->fail($request, $requestId, $start, 429, 'quota_exceeded', 'Monthly API quota exceeded. Upgrade your plan or wait until period reset.', $key);
            return;
        }

        // Globals para controllers + audit log
        $GLOBALS['__api_key']        = $key;
        $GLOBALS['__api_request_id'] = $requestId;
        $GLOBALS['__api_started_at'] = $start;

        // Touch usage (ultimo uso) — async-safe (no bloquea respuesta)
        try { ApiKey::touchUsage((int) $key['id'], $request->ip()); } catch (\Throwable) {}

        // Buffer para captura del status de respuesta y log al final
        ob_start();
        try {
            $next();
        } finally {
            $body  = ob_get_clean();
            $status = http_response_code() ?: 200;
            $latency = (int) round((microtime(true) - $start) * 1000);
            echo $body;

            ApiKeyService::logRequest(
                $key,
                $request->method(),
                $request->path(),
                (int) $status,
                $latency,
                $request->ip(),
                $request->userAgent(),
                $requestId,
                null,
                strlen($request->rawBody()),
                strlen((string) $body)
            );
        }
    }

    private function fail(Request $request, string $requestId, float $start, int $status, string $code, string $msg, ?array $key = null): void
    {
        $latency = (int) round((microtime(true) - $start) * 1000);
        ApiKeyService::logRequest(
            $key,
            $request->method(),
            $request->path(),
            $status,
            $latency,
            $request->ip(),
            $request->userAgent(),
            $requestId,
            $code . ': ' . $msg,
            strlen($request->rawBody()),
            0
        );
        Response::json([
            'error' => [
                'code'       => $code,
                'message'    => $msg,
                'request_id' => $requestId,
            ],
        ], $status);
    }

    private static function extractBearer(Request $request): string
    {
        $auth = (string) $request->header('authorization', '');
        if ($auth !== '') {
            if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
                return trim($m[1]);
            }
        }
        $x = (string) $request->header('x-api-key', '');
        if ($x !== '') return trim($x);

        return '';
    }

    private static function uuid4(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
