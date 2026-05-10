<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;

/**
 * Agrega KPIs cross-feature para el dashboard ejecutivo unificado:
 *   - Comercial: ordenes, ingresos, tickets criticos, conversaciones abiertas
 *   - IA: agent runs hoy, costo USD periodo, tokens, ratio exitos
 *   - Automatizacion: workflows activos, runs hoy, fallidos, webhook deliveries
 *   - Seguridad: logins fallidos 24h, sesiones activas, eventos criticos 7d
 *   - Tendencias: sparkline 7 dias para ordenes, agent_runs, costo IA
 *   - Activity feed: ultimas N cosas que pasaron (cross-source)
 *
 * Cache de 60 segundos por tenant en filesystem (storage/cache/) para no
 * pegar 30 queries en cada render. Si la BD esta intermitente, devuelve
 * el cache stale en lugar de fallar.
 */
final class ExecutiveDashboardService
{
    private const CACHE_TTL = 60; // segundos

    public function __construct(private int $tenantId) {}

    /** Pull principal — devuelve toda la data para la vista ejecutiva. */
    public function snapshot(bool $bypassCache = false): array
    {
        if (!$bypassCache) {
            $cached = $this->readCache();
            if ($cached !== null) return $cached;
        }

        $data = [
            'generated_at' => date('c'),
            'kpis'         => $this->kpis(),
            'commercial'   => $this->commercial(),
            'ai'           => $this->ai(),
            'automation'   => $this->automation(),
            'security'     => $this->security(),
            'trends'       => $this->trends(),
            'activity'     => $this->activityFeed(20),
        ];

        $this->writeCache($data);
        return $data;
    }

    // ============================================================
    // KPIs principales (top row del dashboard)
    // ============================================================

    private function kpis(): array
    {
        $now = $this->fetchSafe(function () {
            return [
                'orders_today'    => (int) Database::fetchColumn(
                    "SELECT COUNT(*) FROM `orders` WHERE `tenant_id` = :t AND DATE(`created_at`) = CURDATE()",
                    ['t' => $this->tenantId]
                ),
                'revenue_today'   => (float) Database::fetchColumn(
                    "SELECT COALESCE(SUM(total), 0) FROM `orders`
                     WHERE `tenant_id` = :t AND DATE(`created_at`) = CURDATE()
                       AND `status` NOT IN ('cancelled')",
                    ['t' => $this->tenantId]
                ),
                'agent_runs_today'=> (int) Database::fetchColumn(
                    "SELECT COUNT(*) FROM `agent_runs`
                     WHERE `tenant_id` = :t AND DATE(`created_at`) = CURDATE()",
                    ['t' => $this->tenantId]
                ),
                'ai_cost_today'   => (float) Database::fetchColumn(
                    "SELECT COALESCE(SUM(cost_usd), 0) FROM `agent_runs`
                     WHERE `tenant_id` = :t AND DATE(`created_at`) = CURDATE()",
                    ['t' => $this->tenantId]
                ),
                'conv_open'       => (int) Database::fetchColumn(
                    "SELECT COUNT(*) FROM `conversations`
                     WHERE `tenant_id` = :t AND `status` IN ('open','new','pending')",
                    ['t' => $this->tenantId]
                ),
                'workflows_active'=> (int) Database::fetchColumn(
                    "SELECT COUNT(*) FROM `workflows` WHERE `tenant_id` = :t AND `is_active` = 1",
                    ['t' => $this->tenantId]
                ),
            ];
        }, []);

        // Comparativa vs ayer
        $yest = $this->fetchSafe(function () {
            return [
                'orders'  => (int) Database::fetchColumn(
                    "SELECT COUNT(*) FROM `orders` WHERE `tenant_id` = :t AND DATE(`created_at`) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)",
                    ['t' => $this->tenantId]
                ),
                'revenue' => (float) Database::fetchColumn(
                    "SELECT COALESCE(SUM(total), 0) FROM `orders`
                     WHERE `tenant_id` = :t AND DATE(`created_at`) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                       AND `status` NOT IN ('cancelled')",
                    ['t' => $this->tenantId]
                ),
            ];
        }, []);

        return [
            'orders_today'     => (int)   ($now['orders_today']    ?? 0),
            'orders_yesterday' => (int)   ($yest['orders']         ?? 0),
            'revenue_today'    => (float) ($now['revenue_today']   ?? 0),
            'revenue_yesterday'=> (float) ($yest['revenue']        ?? 0),
            'agent_runs_today' => (int)   ($now['agent_runs_today']?? 0),
            'ai_cost_today'    => (float) ($now['ai_cost_today']   ?? 0),
            'conv_open'        => (int)   ($now['conv_open']       ?? 0),
            'workflows_active' => (int)   ($now['workflows_active']?? 0),
        ];
    }

    // ============================================================
    // Bloque comercial
    // ============================================================

    private function commercial(): array
    {
        return $this->fetchSafe(function () {
            $byStatus = Database::fetchAll(
                "SELECT `status`, COUNT(*) AS n, COALESCE(SUM(total),0) AS revenue
                 FROM `orders`
                 WHERE `tenant_id` = :t AND `created_at` >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY `status`",
                ['t' => $this->tenantId]
            );
            $tickets_critical = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM `tickets`
                 WHERE `tenant_id` = :t AND `priority` = 'critical'
                   AND `status` NOT IN ('resolved','closed') AND `deleted_at` IS NULL",
                ['t' => $this->tenantId]
            );
            $contacts_30d = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM `contacts`
                 WHERE `tenant_id` = :t AND `created_at` >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                   AND `deleted_at` IS NULL",
                ['t' => $this->tenantId]
            );
            $avg_order = (float) Database::fetchColumn(
                "SELECT COALESCE(AVG(total), 0) FROM `orders`
                 WHERE `tenant_id` = :t AND `created_at` >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                   AND `status` NOT IN ('cancelled')",
                ['t' => $this->tenantId]
            );
            return [
                'orders_by_status' => $byStatus,
                'tickets_critical' => $tickets_critical,
                'contacts_30d'     => $contacts_30d,
                'avg_order_30d'    => round($avg_order, 2),
            ];
        }, [
            'orders_by_status' => [],
            'tickets_critical' => 0,
            'contacts_30d'     => 0,
            'avg_order_30d'    => 0,
        ]);
    }

    // ============================================================
    // Bloque IA
    // ============================================================

    private function ai(): array
    {
        return $this->fetchSafe(function () {
            $runs = Database::fetch(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'succeeded' THEN 1 ELSE 0 END) AS ok,
                    SUM(CASE WHEN status = 'failed'    THEN 1 ELSE 0 END) AS failed,
                    SUM(tokens_in)  AS tokens_in,
                    SUM(tokens_out) AS tokens_out,
                    SUM(cost_usd)   AS cost,
                    AVG(latency_ms) AS avg_latency
                 FROM `agent_runs`
                 WHERE `tenant_id` = :t AND `created_at` >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                ['t' => $this->tenantId]
            ) ?: [];

            $topAgents = Database::fetchAll(
                "SELECT r.`agent_id`, a.`name`,
                        COUNT(*) AS runs, COALESCE(SUM(r.cost_usd), 0) AS cost
                 FROM `agent_runs` r
                 LEFT JOIN `ai_agents` a ON a.id = r.agent_id
                 WHERE r.`tenant_id` = :t AND r.`created_at` >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 GROUP BY r.`agent_id`, a.`name`
                 ORDER BY runs DESC
                 LIMIT 5",
                ['t' => $this->tenantId]
            );

            $total = (int) ($runs['total'] ?? 0);
            $ok    = (int) ($runs['ok']    ?? 0);
            return [
                'runs_7d'      => $total,
                'success_rate' => $total > 0 ? round(($ok / $total) * 100, 1) : 0.0,
                'failed_7d'    => (int) ($runs['failed'] ?? 0),
                'tokens_in'    => (int) ($runs['tokens_in']  ?? 0),
                'tokens_out'   => (int) ($runs['tokens_out'] ?? 0),
                'cost_7d'      => round((float) ($runs['cost'] ?? 0), 4),
                'avg_latency'  => (int) round((float) ($runs['avg_latency'] ?? 0)),
                'top_agents'   => $topAgents,
            ];
        }, [
            'runs_7d' => 0, 'success_rate' => 0, 'failed_7d' => 0,
            'tokens_in' => 0, 'tokens_out' => 0, 'cost_7d' => 0,
            'avg_latency' => 0, 'top_agents' => [],
        ]);
    }

    // ============================================================
    // Bloque automatizacion
    // ============================================================

    private function automation(): array
    {
        return $this->fetchSafe(function () {
            $wf = Database::fetch(
                "SELECT
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS paused,
                    SUM(runs_count) AS total_runs
                 FROM `workflows` WHERE `tenant_id` = :t",
                ['t' => $this->tenantId]
            ) ?: [];

            $runs7d = Database::fetch(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'succeeded' THEN 1 ELSE 0 END) AS ok,
                    SUM(CASE WHEN status = 'failed'    THEN 1 ELSE 0 END) AS failed,
                    SUM(CASE WHEN status = 'waiting'   THEN 1 ELSE 0 END) AS waiting
                 FROM `workflow_runs`
                 WHERE `tenant_id` = :t AND `created_at` >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                ['t' => $this->tenantId]
            ) ?: [];

            $webhooks = Database::fetch(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS ok,
                    SUM(CASE WHEN status IN ('failed','dead') THEN 1 ELSE 0 END) AS failed,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending
                 FROM `webhook_deliveries`
                 WHERE `tenant_id` = :t AND `created_at` >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                ['t' => $this->tenantId]
            ) ?: [];

            $apiCalls = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM `api_request_logs`
                 WHERE `tenant_id` = :t AND `created_at` >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                ['t' => $this->tenantId]
            );

            return [
                'workflows_active' => (int) ($wf['active'] ?? 0),
                'workflows_paused' => (int) ($wf['paused'] ?? 0),
                'workflow_runs_7d' => (int) ($runs7d['total']  ?? 0),
                'workflow_failed_7d' => (int) ($runs7d['failed'] ?? 0),
                'workflow_waiting' => (int) ($runs7d['waiting'] ?? 0),
                'webhook_deliveries_7d' => (int) ($webhooks['total']  ?? 0),
                'webhook_failed_7d'     => (int) ($webhooks['failed'] ?? 0),
                'webhook_pending'       => (int) ($webhooks['pending']?? 0),
                'api_calls_7d'          => $apiCalls,
            ];
        }, [
            'workflows_active' => 0, 'workflows_paused' => 0,
            'workflow_runs_7d' => 0, 'workflow_failed_7d' => 0, 'workflow_waiting' => 0,
            'webhook_deliveries_7d' => 0, 'webhook_failed_7d' => 0, 'webhook_pending' => 0,
            'api_calls_7d' => 0,
        ]);
    }

    // ============================================================
    // Bloque seguridad
    // ============================================================

    private function security(): array
    {
        return $this->fetchSafe(function () {
            $failed24h = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM `login_attempts`
                 WHERE `success` = 0 AND `created_at` >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                   AND `user_id` IN (SELECT `id` FROM `users` WHERE `tenant_id` = :t)",
                ['t' => $this->tenantId]
            );
            $critical7d = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM `security_events`
                 WHERE `tenant_id` = :t AND `severity` = 'critical'
                   AND `created_at` >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                ['t' => $this->tenantId]
            );
            $warning7d = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM `security_events`
                 WHERE `tenant_id` = :t AND `severity` = 'warning'
                   AND `created_at` >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                ['t' => $this->tenantId]
            );
            $sessions = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM `user_sessions`
                 WHERE `tenant_id` = :t AND `revoked_at` IS NULL
                   AND `last_seen_at` >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                ['t' => $this->tenantId]
            );

            // 2FA coverage del tenant
            $tenantUsers = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM `users` WHERE `tenant_id` = :t AND `is_active` = 1 AND `deleted_at` IS NULL",
                ['t' => $this->tenantId]
            );
            $with2fa = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM `user_2fa`
                 WHERE `enabled` = 1 AND `user_id` IN (SELECT `id` FROM `users` WHERE `tenant_id` = :t)",
                ['t' => $this->tenantId]
            );
            $coveragePct = $tenantUsers > 0 ? (int) round(($with2fa / $tenantUsers) * 100) : 0;

            $activeApiKeys = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM `api_keys`
                 WHERE `tenant_id` = :t AND `revoked_at` IS NULL
                   AND (`expires_at` IS NULL OR `expires_at` > NOW())",
                ['t' => $this->tenantId]
            );

            return [
                'logins_failed_24h' => $failed24h,
                'critical_7d'       => $critical7d,
                'warning_7d'        => $warning7d,
                'active_sessions'   => $sessions,
                'tenant_users'      => $tenantUsers,
                'users_with_2fa'    => $with2fa,
                'twofa_coverage'    => $coveragePct,
                'active_api_keys'   => $activeApiKeys,
            ];
        }, [
            'logins_failed_24h' => 0, 'critical_7d' => 0, 'warning_7d' => 0,
            'active_sessions' => 0, 'tenant_users' => 0, 'users_with_2fa' => 0,
            'twofa_coverage' => 0, 'active_api_keys' => 0,
        ]);
    }

    // ============================================================
    // Sparklines: ultimos 7 dias (incluyendo hoy) para tendencia visual
    // ============================================================

    private function trends(): array
    {
        return $this->fetchSafe(function () {
            $days = self::lastNDays(7);

            $orders = $this->dailySeries(
                "SELECT DATE(created_at) d, COUNT(*) n FROM `orders`
                 WHERE `tenant_id` = :t AND `created_at` >= :since AND `status` NOT IN ('cancelled')
                 GROUP BY DATE(created_at)",
                $days
            );
            $revenue = $this->dailySeries(
                "SELECT DATE(created_at) d, COALESCE(SUM(total),0) n FROM `orders`
                 WHERE `tenant_id` = :t AND `created_at` >= :since AND `status` NOT IN ('cancelled')
                 GROUP BY DATE(created_at)",
                $days
            );
            $runs = $this->dailySeries(
                "SELECT DATE(created_at) d, COUNT(*) n FROM `agent_runs`
                 WHERE `tenant_id` = :t AND `created_at` >= :since
                 GROUP BY DATE(created_at)",
                $days
            );
            $cost = $this->dailySeries(
                "SELECT DATE(created_at) d, COALESCE(SUM(cost_usd),0) n FROM `agent_runs`
                 WHERE `tenant_id` = :t AND `created_at` >= :since
                 GROUP BY DATE(created_at)",
                $days
            );

            return [
                'days'    => $days,
                'orders'  => $orders,
                'revenue' => array_map(fn($v) => round((float) $v, 2), $revenue),
                'runs'    => $runs,
                'cost'    => array_map(fn($v) => round((float) $v, 4), $cost),
            ];
        }, [
            'days' => self::lastNDays(7), 'orders' => array_fill(0, 7, 0),
            'revenue' => array_fill(0, 7, 0), 'runs' => array_fill(0, 7, 0),
            'cost' => array_fill(0, 7, 0),
        ]);
    }

    /** Ejecuta query con GROUP BY DATE y rellena los dias faltantes con 0. */
    private function dailySeries(string $sql, array $days): array
    {
        $rows = Database::fetchAll($sql, [
            't'     => $this->tenantId,
            'since' => $days[0] . ' 00:00:00',
        ]);
        $byDate = [];
        foreach ($rows as $r) $byDate[$r['d']] = $r['n'];

        $series = [];
        foreach ($days as $d) {
            $series[] = isset($byDate[$d]) ? (float) $byDate[$d] : 0;
        }
        return $series;
    }

    private static function lastNDays(int $n): array
    {
        $out = [];
        $today = strtotime('today');
        for ($i = $n - 1; $i >= 0; $i--) {
            $out[] = date('Y-m-d', strtotime("-{$i} days", $today));
        }
        return $out;
    }

    // ============================================================
    // Activity feed unificado
    // ============================================================

    /**
     * Une eventos relevantes de multiples fuentes y los ordena por tiempo
     * descendente. Cada entrada: { type, title, subtitle, time, severity, link? }
     */
    public function activityFeed(int $limit = 20): array
    {
        $items = [];

        // Agent runs (cualquier status)
        $rows = $this->fetchSafe(fn() => Database::fetchAll(
            "SELECT r.uuid, r.status, r.created_at, r.cost_usd, a.name AS agent_name
             FROM `agent_runs` r
             LEFT JOIN `ai_agents` a ON a.id = r.agent_id
             WHERE r.tenant_id = :t
             ORDER BY r.id DESC LIMIT 10",
            ['t' => $this->tenantId]
        ), []);
        foreach ($rows as $r) {
            $sev = $r['status'] === 'failed' ? 'warning' : ($r['status'] === 'succeeded' ? 'info' : 'info');
            $items[] = [
                'type'     => 'agent_run',
                'icon'     => '🧠',
                'title'    => 'Agente IA ' . $r['status'] . ($r['agent_name'] ? ' · ' . $r['agent_name'] : ''),
                'subtitle' => $r['cost_usd'] ? '$' . number_format((float) $r['cost_usd'], 4) : '',
                'time'     => $r['created_at'],
                'severity' => $sev,
            ];
        }

        // Workflow runs recientes
        $rows = $this->fetchSafe(fn() => Database::fetchAll(
            "SELECT wr.uuid, wr.status, wr.created_at, w.name AS wf_name
             FROM `workflow_runs` wr
             LEFT JOIN `workflows` w ON w.id = wr.workflow_id
             WHERE wr.tenant_id = :t
             ORDER BY wr.id DESC LIMIT 10",
            ['t' => $this->tenantId]
        ), []);
        foreach ($rows as $r) {
            $sev = in_array($r['status'], ['failed','cancelled'], true) ? 'warning' : 'info';
            $items[] = [
                'type'     => 'workflow_run',
                'icon'     => '🪄',
                'title'    => 'Workflow ' . $r['status'] . ($r['wf_name'] ? ' · ' . $r['wf_name'] : ''),
                'subtitle' => '',
                'time'     => $r['created_at'],
                'severity' => $sev,
            ];
        }

        // Webhook deliveries fallidas/dead
        $rows = $this->fetchSafe(fn() => Database::fetchAll(
            "SELECT d.event, d.status, d.created_at, e.name AS endpoint_name
             FROM `webhook_deliveries` d
             LEFT JOIN `webhook_endpoints` e ON e.id = d.endpoint_id
             WHERE d.tenant_id = :t AND d.status IN ('failed','dead')
             ORDER BY d.id DESC LIMIT 5",
            ['t' => $this->tenantId]
        ), []);
        foreach ($rows as $r) {
            $items[] = [
                'type'     => 'webhook_failed',
                'icon'     => '🪝',
                'title'    => 'Webhook ' . $r['status'] . ' · ' . $r['event'],
                'subtitle' => $r['endpoint_name'] ? 'a ' . $r['endpoint_name'] : '',
                'time'     => $r['created_at'],
                'severity' => 'warning',
            ];
        }

        // Security events warning/critical
        $rows = $this->fetchSafe(fn() => Database::fetchAll(
            "SELECT event, severity, created_at, ip
             FROM `security_events`
             WHERE tenant_id = :t AND severity IN ('warning','critical')
             ORDER BY id DESC LIMIT 10",
            ['t' => $this->tenantId]
        ), []);
        foreach ($rows as $r) {
            $items[] = [
                'type'     => 'security_event',
                'icon'     => $r['severity'] === 'critical' ? '🚨' : '⚠',
                'title'    => 'Seguridad: ' . $r['event'],
                'subtitle' => $r['ip'] ? 'desde ' . $r['ip'] : '',
                'time'     => $r['created_at'],
                'severity' => $r['severity'],
            ];
        }

        // Ordenes recientes (info)
        $rows = $this->fetchSafe(fn() => Database::fetchAll(
            "SELECT code, status, created_at, total, currency
             FROM `orders`
             WHERE tenant_id = :t
             ORDER BY id DESC LIMIT 5",
            ['t' => $this->tenantId]
        ), []);
        foreach ($rows as $r) {
            $items[] = [
                'type'     => 'order',
                'icon'     => '🛒',
                'title'    => 'Orden ' . $r['code'] . ' · ' . $r['status'],
                'subtitle' => ($r['currency'] ?? 'USD') . ' ' . number_format((float) $r['total'], 2),
                'time'     => $r['created_at'],
                'severity' => 'info',
            ];
        }

        // Ordenar por tiempo desc
        usort($items, fn($a, $b) => strcmp((string) $b['time'], (string) $a['time']));
        return array_slice($items, 0, $limit);
    }

    // ============================================================
    // Cache helpers
    // ============================================================

    private function cachePath(): string
    {
        $base = (string) Config::get('app.paths.cache');
        return $base . '/exec_dashboard_' . $this->tenantId . '.json';
    }

    private function readCache(): ?array
    {
        $p = $this->cachePath();
        if (!is_file($p)) return null;
        if (time() - filemtime($p) > self::CACHE_TTL) return null;
        $raw = @file_get_contents($p);
        if (!$raw) return null;
        $d = json_decode($raw, true);
        return is_array($d) ? $d : null;
    }

    private function writeCache(array $data): void
    {
        $p = $this->cachePath();
        $dir = dirname($p);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents($p, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Ejecuta una closure de DB; si falla devuelve $fallback en lugar de propagar
     * (queremos que el dashboard cargue siempre, aunque alguna parte falle).
     */
    private function fetchSafe(callable $fn, mixed $fallback): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            Logger::warning('ExecutiveDashboard partial query fail', ['msg' => $e->getMessage()]);
            return $fallback;
        }
    }
}
