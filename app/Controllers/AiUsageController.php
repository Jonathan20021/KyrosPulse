<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Tenant;
use App\Services\AiProviderService;

/**
 * Dashboard de Token Economy: muestra al tenant cuanto gasto en IA, en que
 * features, su trend diario, top conversaciones por costo, ROI (costo IA -> total
 * generado en ordenes atribuidas) y permite ajustar el budget mensual + umbral
 * de alerta.
 */
final class AiUsageController extends Controller
{
    public function index(Request $request): void
    {
        $tenantId = Tenant::id();

        $summary = (new AiProviderService($tenantId))->tokenSummary();

        // KPIs del periodo actual (mes calendario)
        $periodStart = (string) ($summary['period_start'] ?? date('Y-m-01'));
        $kpis = Database::fetch(
            "SELECT
                COUNT(*) AS calls,
                COALESCE(SUM(tokens_input + tokens_output), 0) AS tokens,
                COALESCE(SUM(cost), 0) AS cost_usd,
                COALESCE(AVG(duration_ms), 0) AS avg_latency_ms,
                COALESCE(SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END), 0) AS successes,
                COALESCE(SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END), 0) AS failures
             FROM ai_logs
             WHERE tenant_id = :t AND created_at >= :s",
            ['t' => $tenantId, 's' => $periodStart]
        ) ?: ['calls' => 0, 'tokens' => 0, 'cost_usd' => 0, 'avg_latency_ms' => 0, 'successes' => 0, 'failures' => 0];

        // Trend diario ultimos 30 dias (para grafico)
        $daily = Database::fetchAll(
            "SELECT DATE(created_at) AS day,
                    COUNT(*) AS calls,
                    SUM(tokens_input + tokens_output) AS tokens,
                    SUM(cost) AS cost_usd
             FROM ai_logs
             WHERE tenant_id = :t
               AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             GROUP BY DATE(created_at)
             ORDER BY day ASC",
            ['t' => $tenantId]
        );

        // Top features por costo en el periodo
        // El feature viene como "auto_reply:claude:global" -> nos quedamos con el prefijo
        $byFeature = Database::fetchAll(
            "SELECT
                SUBSTRING_INDEX(feature, ':', 1) AS feature_base,
                COUNT(*) AS calls,
                SUM(tokens_input + tokens_output) AS tokens,
                SUM(cost) AS cost_usd
             FROM ai_logs
             WHERE tenant_id = :t AND created_at >= :s
             GROUP BY feature_base
             ORDER BY cost_usd DESC
             LIMIT 10",
            ['t' => $tenantId, 's' => $periodStart]
        );

        // Top modelos
        $byModel = Database::fetchAll(
            "SELECT model, COUNT(*) AS calls,
                    SUM(tokens_input) AS tokens_in,
                    SUM(tokens_output) AS tokens_out,
                    SUM(cost) AS cost_usd
             FROM ai_logs
             WHERE tenant_id = :t AND created_at >= :s AND model IS NOT NULL AND model <> ''
             GROUP BY model
             ORDER BY cost_usd DESC
             LIMIT 10",
            ['t' => $tenantId, 's' => $periodStart]
        );

        // ROI: costo IA por ordenes generadas
        $roi = Database::fetch(
            "SELECT
                COUNT(DISTINCT al.order_id) AS orders_generated,
                COALESCE(SUM(al.cost), 0) AS ai_cost,
                COALESCE(SUM(o.total), 0) AS orders_total
             FROM ai_logs al
             INNER JOIN orders o ON o.id = al.order_id AND o.tenant_id = al.tenant_id
             WHERE al.tenant_id = :t
               AND al.order_id IS NOT NULL
               AND al.created_at >= :s",
            ['t' => $tenantId, 's' => $periodStart]
        ) ?: ['orders_generated' => 0, 'ai_cost' => 0, 'orders_total' => 0];

        // Ultimos 50 calls para tabla de actividad. Aliasamos cost->cost_usd y
        // duration_ms->latency_ms para que la vista use nombres semanticamente
        // claros sin tener que tocar mas codigo.
        $recent = Database::fetchAll(
            "SELECT al.id, al.feature, al.model, al.tokens_input, al.tokens_output,
                    al.cost AS cost_usd, al.duration_ms AS latency_ms,
                    al.success, al.error_message,
                    al.conversation_id, al.order_id, al.created_at,
                    o.code AS order_code, o.total AS order_total, o.currency AS order_currency
             FROM ai_logs al
             LEFT JOIN orders o ON o.id = al.order_id AND o.tenant_id = al.tenant_id
             WHERE al.tenant_id = :t
             ORDER BY al.id DESC
             LIMIT 50",
            ['t' => $tenantId]
        );

        $this->view('ai.usage', [
            'page'        => 'ai_usage',
            'tab'         => 'ai_usage',
            'summary'     => $summary,
            'kpis'        => $kpis,
            'daily'       => $daily,
            'byFeature'   => $byFeature,
            'byModel'     => $byModel,
            'roi'         => $roi,
            'recent'      => $recent,
            'periodStart' => $periodStart,
        ], 'layouts.app');
    }

    public function updateBudget(Request $request): void
    {
        $tenantId = Tenant::id();

        $rawBudget = $request->input('ai_budget_usd');
        // Vacio o 0 = sin cap (NULL en DB)
        $budget = ($rawBudget === null || $rawBudget === '' || (float) $rawBudget <= 0)
            ? null
            : round((float) $rawBudget, 4);

        $thresholdRaw = (int) ($request->input('ai_alert_threshold_pct') ?: 80);
        $threshold = max(50, min(95, $thresholdRaw));

        Database::run(
            "UPDATE tenants
                SET ai_budget_usd = :b, ai_alert_threshold_pct = :th
              WHERE id = :id",
            ['b' => $budget, 'th' => $threshold, 'id' => $tenantId]
        );

        Audit::log('ai.budget_updated', 'tenant', $tenantId, [], [
            'budget_usd' => $budget,
            'threshold'  => $threshold,
        ]);

        Session::flash('success', $budget === null
            ? 'Budget sin tope. Solo se rastrea el uso (sin pausa automatica).'
            : 'Budget actualizado a $' . number_format($budget, 2) . ' / mes.'
        );
        $this->redirect('/ai/usage');
    }
}
