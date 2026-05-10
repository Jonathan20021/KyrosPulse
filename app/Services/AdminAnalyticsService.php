<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

/**
 * Analytics cross-tenant para el super admin. Pulla:
 *   - KPIs globales: tenants totales, activos, trial, MRR estimado
 *   - Distribucion por plan
 *   - Crecimiento mensual (sparkline 12m)
 *   - Top tenants por uso (API, agent runs, costo IA, ordenes)
 *   - Tenants en riesgo de churn (no login 30+ dias, drop en activity)
 *
 * Cache de 5 min en filesystem porque las queries son mas pesadas que el
 * dashboard ejecutivo (agregaciones cross-tenant).
 */
final class AdminAnalyticsService
{
    private const CACHE_TTL = 300; // 5 minutos

    public static function snapshot(bool $bypassCache = false): array
    {
        if (!$bypassCache) {
            $cached = self::readCache();
            if ($cached !== null) return $cached;
        }

        $data = [
            'generated_at' => date('c'),
            'kpis'         => self::kpis(),
            'plan_breakdown' => self::planBreakdown(),
            'growth'       => self::growth12m(),
            'top_tenants'  => self::topTenants(10),
            'at_risk'      => self::atRiskTenants(10),
        ];

        self::writeCache($data);
        return $data;
    }

    private static function kpis(): array
    {
        return self::safe(function () {
            $totalTenants = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM `tenants` WHERE `deleted_at` IS NULL"
            );
            $activeTenants = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM `tenants` WHERE `status` = 'active' AND `deleted_at` IS NULL"
            );
            $trialTenants = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM `tenants` WHERE `status` = 'trial' AND `deleted_at` IS NULL"
            );
            $suspended = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM `tenants` WHERE `status` IN ('suspended','cancelled','expired') AND `deleted_at` IS NULL"
            );
            $newThisMonth = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM `tenants`
                 WHERE `created_at` >= DATE_FORMAT(NOW(), '%Y-%m-01')
                   AND `deleted_at` IS NULL"
            );
            $mrr = (float) Database::fetchColumn(
                "SELECT COALESCE(SUM(p.price_monthly), 0)
                 FROM `tenants` t
                 INNER JOIN `plans` p ON p.id = t.plan_id
                 WHERE t.status = 'active' AND t.deleted_at IS NULL"
            );
            $orders30d = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM `orders`
                 WHERE `created_at` >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                   AND `status` NOT IN ('cancelled')"
            );
            $aiCost30d = (float) Database::fetchColumn(
                "SELECT COALESCE(SUM(cost_usd), 0) FROM `agent_runs`
                 WHERE `created_at` >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );

            return [
                'total_tenants'  => $totalTenants,
                'active_tenants' => $activeTenants,
                'trial_tenants'  => $trialTenants,
                'suspended_tenants' => $suspended,
                'new_this_month' => $newThisMonth,
                'mrr_usd'        => round($mrr, 2),
                'arr_usd'        => round($mrr * 12, 2),
                'orders_30d'     => $orders30d,
                'ai_cost_30d'    => round($aiCost30d, 4),
            ];
        }, [
            'total_tenants' => 0, 'active_tenants' => 0, 'trial_tenants' => 0,
            'suspended_tenants' => 0, 'new_this_month' => 0, 'mrr_usd' => 0,
            'arr_usd' => 0, 'orders_30d' => 0, 'ai_cost_30d' => 0,
        ]);
    }

    private static function planBreakdown(): array
    {
        return self::safe(fn() => Database::fetchAll(
            "SELECT p.id, p.name, p.slug, p.price_monthly, p.api_quota_monthly,
                    COUNT(t.id) AS tenants_count
             FROM `plans` p
             LEFT JOIN `tenants` t ON t.plan_id = p.id AND t.deleted_at IS NULL AND t.status = 'active'
             WHERE p.is_active = 1
             GROUP BY p.id, p.name, p.slug, p.price_monthly, p.api_quota_monthly
             ORDER BY p.sort_order ASC, p.price_monthly ASC"
        ), []);
    }

    /** Tenants creados por mes en los ultimos 12 meses. */
    private static function growth12m(): array
    {
        return self::safe(function () {
            $months = [];
            for ($i = 11; $i >= 0; $i--) {
                $months[] = date('Y-m', strtotime("-{$i} months"));
            }
            $rows = Database::fetchAll(
                "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS n
                 FROM `tenants`
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                   AND deleted_at IS NULL
                 GROUP BY DATE_FORMAT(created_at, '%Y-%m')"
            );
            $byMonth = [];
            foreach ($rows as $r) $byMonth[$r['ym']] = (int) $r['n'];

            $series = [];
            $labels = [];
            foreach ($months as $m) {
                $series[] = $byMonth[$m] ?? 0;
                $labels[] = date('M', strtotime($m . '-01'));
            }
            return ['months' => $months, 'labels' => $labels, 'series' => $series];
        }, ['months' => [], 'labels' => [], 'series' => []]);
    }

    /** Top tenants por uso 30d (API calls + agent runs + ordenes + costo IA). */
    private static function topTenants(int $limit = 10): array
    {
        return self::safe(fn() => Database::fetchAll(
            "SELECT
                t.id, t.name, t.slug, t.status, t.created_at,
                p.name AS plan_name, p.slug AS plan_slug,
                COALESCE((SELECT COUNT(*) FROM `api_request_logs` r WHERE r.tenant_id = t.id AND r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS api_calls_30d,
                COALESCE((SELECT COUNT(*)         FROM `agent_runs`  a WHERE a.tenant_id = t.id AND a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS agent_runs_30d,
                COALESCE((SELECT SUM(cost_usd)    FROM `agent_runs`  a WHERE a.tenant_id = t.id AND a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS ai_cost_30d,
                COALESCE((SELECT COUNT(*)         FROM `orders`      o WHERE o.tenant_id = t.id AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND o.status NOT IN ('cancelled')), 0) AS orders_30d
             FROM `tenants` t
             LEFT JOIN `plans` p ON p.id = t.plan_id
             WHERE t.deleted_at IS NULL
             ORDER BY (api_calls_30d + agent_runs_30d * 5 + orders_30d * 10) DESC
             LIMIT $limit"
        ), []);
    }

    /**
     * Tenants en riesgo de churn:
     * - No actividad de mensajes en >=21 dias
     * - O drop fuerte (>70%) vs mes anterior en agent_runs
     * - Excluye suspendidos/cancelados (ya no son riesgo, son perdida)
     */
    private static function atRiskTenants(int $limit = 10): array
    {
        return self::safe(fn() => Database::fetchAll(
            "SELECT
                t.id, t.name, t.status, t.created_at,
                p.name AS plan_name,
                COALESCE((SELECT MAX(created_at) FROM `messages` m WHERE m.tenant_id = t.id), '1970-01-01') AS last_msg_at,
                COALESCE((SELECT COUNT(*) FROM `agent_runs` a WHERE a.tenant_id = t.id AND a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS runs_30d,
                COALESCE((SELECT COUNT(*) FROM `agent_runs` a WHERE a.tenant_id = t.id AND a.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND a.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS runs_prev_30d
             FROM `tenants` t
             LEFT JOIN `plans` p ON p.id = t.plan_id
             WHERE t.deleted_at IS NULL
               AND t.status IN ('active','trial')
             HAVING (
                last_msg_at < DATE_SUB(NOW(), INTERVAL 21 DAY)
                OR (runs_prev_30d > 5 AND runs_30d < runs_prev_30d * 0.3)
             )
             ORDER BY last_msg_at ASC
             LIMIT $limit"
        ), []);
    }

    // ============================================================
    // Per-tenant detail (super admin tenant_detail page)
    // ============================================================

    public static function tenantDetail(int $tenantId): array
    {
        return self::safe(function () use ($tenantId) {
            $tenant = Database::fetch(
                "SELECT t.*, p.name AS plan_name, p.slug AS plan_slug, p.api_quota_monthly AS plan_quota
                 FROM `tenants` t LEFT JOIN `plans` p ON p.id = t.plan_id
                 WHERE t.id = :i", ['i' => $tenantId]
            );
            if (!$tenant) return ['tenant' => null];

            $quota = ApiQuotaService::snapshot($tenantId);

            $stats = [
                'users'           => (int) Database::fetchColumn("SELECT COUNT(*) FROM `users` WHERE tenant_id = :t AND deleted_at IS NULL", ['t' => $tenantId]),
                'contacts'        => (int) Database::fetchColumn("SELECT COUNT(*) FROM `contacts` WHERE tenant_id = :t AND deleted_at IS NULL", ['t' => $tenantId]),
                'orders_30d'      => (int) Database::fetchColumn("SELECT COUNT(*) FROM `orders` WHERE tenant_id = :t AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND status NOT IN ('cancelled')", ['t' => $tenantId]),
                'revenue_30d'     => (float) Database::fetchColumn("SELECT COALESCE(SUM(total),0) FROM `orders` WHERE tenant_id = :t AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND status NOT IN ('cancelled')", ['t' => $tenantId]),
                'agent_runs_30d'  => (int) Database::fetchColumn("SELECT COUNT(*) FROM `agent_runs` WHERE tenant_id = :t AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", ['t' => $tenantId]),
                'ai_cost_30d'     => (float) Database::fetchColumn("SELECT COALESCE(SUM(cost_usd),0) FROM `agent_runs` WHERE tenant_id = :t AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", ['t' => $tenantId]),
                'workflows_active'=> (int) Database::fetchColumn("SELECT COUNT(*) FROM `workflows` WHERE tenant_id = :t AND is_active = 1", ['t' => $tenantId]),
                'webhook_endpoints'=> (int) Database::fetchColumn("SELECT COUNT(*) FROM `webhook_endpoints` WHERE tenant_id = :t AND is_active = 1", ['t' => $tenantId]),
                'last_login'      => Database::fetchColumn("SELECT MAX(last_login_at) FROM `users` WHERE tenant_id = :t", ['t' => $tenantId]),
            ];

            return ['tenant' => $tenant, 'quota' => $quota, 'stats' => $stats];
        }, ['tenant' => null]);
    }

    private static function safe(callable $fn, mixed $fallback): mixed
    {
        try { return $fn(); }
        catch (\Throwable $e) {
            Logger::warning('AdminAnalytics query fail', ['msg' => $e->getMessage()]);
            return $fallback;
        }
    }

    private static function cachePath(): string
    {
        $base = (string) \App\Core\Config::get('app.paths.cache');
        return $base . '/admin_analytics.json';
    }

    private static function readCache(): ?array
    {
        $p = self::cachePath();
        if (!is_file($p)) return null;
        if (time() - filemtime($p) > self::CACHE_TTL) return null;
        $raw = @file_get_contents($p);
        if (!$raw) return null;
        $d = json_decode($raw, true);
        return is_array($d) ? $d : null;
    }

    private static function writeCache(array $data): void
    {
        $p = self::cachePath();
        $dir = dirname($p);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents($p, json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
