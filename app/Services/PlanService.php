<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

/**
 * Control central de licencias / planes.
 *
 * Responde tres preguntas:
 *   1) "Que plan tiene este tenant?"           -> currentPlan(tenantId)
 *   2) "Puede usar esta feature?"               -> can(tenantId, feature)
 *   3) "Cuanto ha usado vs su limite de X?"    -> usage(tenantId, resource)
 *
 * Features booleanas (plans.*):
 *   ai_enabled        - Agentes IA, autopilot, sugerencias IA.
 *   api_access        - API publica + webhooks salientes.
 *   advanced_reports  - Reportes ejecutivos + workflows v2.
 *
 * Limites cuantitativos (plans.max_*):
 *   max_users         - Cuantos miembros del equipo (users del tenant).
 *   max_contacts      - Cuantos contactos en el CRM.
 *   max_messages      - Mensajes WhatsApp / IA por mes.
 *   max_campaigns     - Cuantas campanas masivas en total.
 *   max_automations   - Cuantos workflows / automatizaciones activas.
 *
 * Super admin nunca esta limitado.
 */
final class PlanService
{
    /** Las features booleanas que sabemos enforcear. */
    public const BOOLEAN_FEATURES = [
        'ai_enabled',
        'api_access',
        'advanced_reports',
    ];

    /** Los resources cuantitativos. */
    public const QUANTITY_RESOURCES = [
        'users',
        'contacts',
        'messages',
        'campaigns',
        'automations',
    ];

    /** Etiquetas amistosas para la UI de upgrade. */
    public const FEATURE_LABELS = [
        'ai_enabled'       => 'Agentes IA',
        'api_access'       => 'Acceso API + webhooks salientes',
        'advanced_reports' => 'Reportes avanzados + workflows',
        'users'            => 'Usuarios del equipo',
        'contacts'         => 'Contactos en el CRM',
        'messages'         => 'Mensajes por mes',
        'campaigns'        => 'Campanas masivas',
        'automations'      => 'Automatizaciones / workflows',
    ];

    /** Cache por request: tenantId => plan row (o null). */
    private static array $planCache = [];

    /** Plan minimo recomendado para cada feature, para mensajes de upgrade. */
    public const FEATURE_MIN_PLAN = [
        'ai_enabled'       => 'Professional',
        'api_access'       => 'Business',
        'advanced_reports' => 'Business',
        'users'            => 'Professional',
        'contacts'         => 'Professional',
        'messages'         => 'Professional',
        'campaigns'        => 'Professional',
        'automations'      => 'Professional',
    ];

    // ------------------------------------------------------------------
    // Lookup del plan
    // ------------------------------------------------------------------

    public static function currentPlan(int $tenantId): ?array
    {
        if (array_key_exists($tenantId, self::$planCache)) {
            return self::$planCache[$tenantId];
        }
        try {
            $row = Database::fetch(
                "SELECT p.*
                   FROM tenants t
                   LEFT JOIN plans p ON p.id = t.plan_id
                  WHERE t.id = :id AND t.deleted_at IS NULL
                  LIMIT 1",
                ['id' => $tenantId]
            );
            // Si el tenant existe pero no tiene plan asignado, fallback al plan
            // mas barato activo.
            if (!$row || empty($row['id'])) {
                $row = Database::fetch(
                    "SELECT * FROM plans WHERE is_active = 1
                      ORDER BY price_monthly ASC, sort_order ASC LIMIT 1"
                );
            }
        } catch (\Throwable) {
            $row = null;
        }
        return self::$planCache[$tenantId] = $row ?: null;
    }

    /** Util: limpia el cache (tests, cambios de plan en super admin, etc). */
    public static function flushCache(): void
    {
        self::$planCache = [];
    }

    // ------------------------------------------------------------------
    // Feature booleana (ai_enabled, api_access, advanced_reports)
    // ------------------------------------------------------------------

    /**
     * Retorna true si el plan del tenant incluye la feature.
     * Super admin siempre puede.
     */
    public static function can(int $tenantId, string $feature): bool
    {
        if (Auth::isSuperAdmin()) return true;
        if (!in_array($feature, self::BOOLEAN_FEATURES, true)) {
            return false;
        }
        $plan = self::currentPlan($tenantId);
        if (!$plan) return false;
        return !empty($plan[$feature]);
    }

    /**
     * Helper inverso: "no tiene esta feature". Util para vistas que muestran
     * un upsell en lugar del contenido.
     */
    public static function cannot(int $tenantId, string $feature): bool
    {
        return !self::can($tenantId, $feature);
    }

    // ------------------------------------------------------------------
    // Limites cuantitativos
    // ------------------------------------------------------------------

    /**
     * Devuelve el limite numerico del plan para el recurso.
     * 0 = sin acceso, valores muy grandes (999+) = practicamente sin limite.
     */
    public static function limit(int $tenantId, string $resource): int
    {
        $plan = self::currentPlan($tenantId);
        if (!$plan) return 0;
        $col = 'max_' . $resource;
        if (!array_key_exists($col, $plan)) return 0;
        return (int) $plan[$col];
    }

    /**
     * Cuenta actual de uso para un recurso (por tenant).
     */
    public static function usage(int $tenantId, string $resource): int
    {
        try {
            return match ($resource) {
                'users'       => (int) Database::fetchColumn(
                    "SELECT COUNT(*) FROM users WHERE tenant_id = :t AND deleted_at IS NULL",
                    ['t' => $tenantId]
                ),
                'contacts'    => LicenseService::countClients($tenantId),
                'campaigns'   => (int) Database::fetchColumn(
                    "SELECT COUNT(*) FROM campaigns WHERE tenant_id = :t",
                    ['t' => $tenantId]
                ),
                'automations' => (int) Database::fetchColumn(
                    "SELECT COUNT(*) FROM automations WHERE tenant_id = :t",
                    ['t' => $tenantId]
                ),
                // Mensajes los maneja ApiQuotaService por ventana mensual.
                'messages'    => 0,
                default       => 0,
            };
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Snapshot util para mostrar barras de progreso en /settings y /admin.
     *
     * @return array{limit:int, used:int, remaining:int, percent:int, full:bool}
     */
    public static function snapshot(int $tenantId, string $resource): array
    {
        $limit = self::limit($tenantId, $resource);
        $used  = self::usage($tenantId, $resource);
        $remaining = $limit > 0 ? max(0, $limit - $used) : 0;
        $percent   = $limit > 0 ? (int) min(100, round(($used / $limit) * 100)) : 0;
        return [
            'limit'     => $limit,
            'used'      => $used,
            'remaining' => $remaining,
            'percent'   => $percent,
            'full'      => $limit > 0 && $used >= $limit,
        ];
    }

    /**
     * Verifica si el tenant puede agregar N items mas del recurso.
     * Super admin siempre puede.
     */
    public static function canAdd(int $tenantId, string $resource, int $n = 1): bool
    {
        if (Auth::isSuperAdmin()) return true;
        $limit = self::limit($tenantId, $resource);
        if ($limit <= 0) return false;            // 0 = sin acceso al recurso
        if ($limit >= 999000) return true;        // marcador de "ilimitado"
        $used = self::usage($tenantId, $resource);
        return ($used + $n) <= $limit;
    }

    // ------------------------------------------------------------------
    // Mensajeria UI
    // ------------------------------------------------------------------

    /** Mensaje legible para una feature/limite bloqueado. */
    public static function blockedMessage(string $featureOrResource): string
    {
        $label = self::FEATURE_LABELS[$featureOrResource] ?? $featureOrResource;
        $minPlan = self::FEATURE_MIN_PLAN[$featureOrResource] ?? 'Business';
        return sprintf(
            'Tu plan actual no incluye "%s". Actualiza al plan %s o superior para desbloquear esta funcion.',
            $label,
            $minPlan
        );
    }
}
