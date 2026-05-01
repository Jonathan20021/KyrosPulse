<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Tenant;
use App\Models\Tenant as TenantModel;

final class ReportController extends Controller
{
    public function index(Request $request): void
    {
        $tenantId = Tenant::id();
        $days     = (int) ($request->query('days') ?? 30);
        $days     = min(180, max(7, $days));

        // Mensajes por dia
        $byDay = Database::fetchAll(
            "SELECT DATE(created_at) AS d,
                    SUM(direction='inbound')  AS inbound,
                    SUM(direction='outbound') AS outbound
             FROM messages
             WHERE tenant_id = :t AND created_at >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
             GROUP BY DATE(created_at) ORDER BY d ASC",
            ['t' => $tenantId]
        );
        $labels = []; $inb = []; $outb = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('d/m', strtotime($date));
            $found = array_filter($byDay, fn($r) => $r['d'] === $date);
            $found = $found ? array_values($found)[0] : null;
            $inb[]  = $found ? (int) $found['inbound']  : 0;
            $outb[] = $found ? (int) $found['outbound'] : 0;
        }

        // Por agente
        $agents = Database::fetchAll(
            "SELECT u.first_name, u.last_name,
                    COUNT(DISTINCT c.id) AS conversations,
                    COUNT(m.id) AS messages,
                    AVG(TIMESTAMPDIFF(MINUTE, prev.created_at, m.created_at)) AS avg_response_min
             FROM users u
             LEFT JOIN conversations c ON c.assigned_to = u.id AND c.tenant_id = :t1
             LEFT JOIN messages m ON m.user_id = u.id AND m.direction = 'outbound' AND m.tenant_id = :t2 AND m.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
             LEFT JOIN messages prev ON prev.conversation_id = m.conversation_id AND prev.direction = 'inbound' AND prev.created_at < m.created_at
             WHERE u.tenant_id = :t3 AND u.deleted_at IS NULL
             GROUP BY u.id ORDER BY messages DESC LIMIT 10",
            ['t1' => $tenantId, 't2' => $tenantId, 't3' => $tenantId]
        );

        // Conversion por etapa
        $byStage = Database::fetchAll(
            "SELECT s.name, s.color, COUNT(l.id) AS total, COALESCE(SUM(l.value), 0) AS total_value
             FROM pipeline_stages s
             LEFT JOIN leads l ON l.stage_id = s.id AND l.deleted_at IS NULL AND l.tenant_id = :t
             WHERE s.tenant_id = :t2
             GROUP BY s.id ORDER BY s.sort_order ASC",
            ['t' => $tenantId, 't2' => $tenantId]
        );

        // Sentimiento
        $sentiment = Database::fetchAll(
            "SELECT ai_sentiment, COUNT(*) AS total
             FROM conversations
             WHERE tenant_id = :t AND ai_sentiment IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
             GROUP BY ai_sentiment",
            ['t' => $tenantId]
        );

        // Ticket stats
        $ticketStats = Database::fetch(
            "SELECT COUNT(*) AS total,
                    SUM(status = 'resolved') AS resolved,
                    SUM(status = 'closed')   AS closed,
                    SUM(sla_breached = 1)    AS sla_breached,
                    AVG(satisfaction)        AS avg_satisfaction
             FROM tickets WHERE tenant_id = :t AND deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)",
            ['t' => $tenantId]
        ) ?: [];

        // Distribucion por canal
        $channels = Database::fetchAll(
            "SELECT channel, COUNT(*) AS total
             FROM conversations WHERE tenant_id = :t AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
             GROUP BY channel ORDER BY total DESC",
            ['t' => $tenantId]
        );

        // Activity por hora
        $hourly = Database::fetchAll(
            "SELECT HOUR(created_at) AS h, COUNT(*) AS total
             FROM messages WHERE tenant_id = :t AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
             GROUP BY HOUR(created_at) ORDER BY h ASC",
            ['t' => $tenantId]
        );
        $hours = array_fill(0, 24, 0);
        foreach ($hourly as $r) $hours[(int) $r['h']] = (int) $r['total'];

        // === ANALYTICS DE RESTAURANTE (si aplica) ===
        $tenant = TenantModel::findById($tenantId);
        $restaurant = null;
        if (!empty($tenant['is_restaurant'])) {
            try {
                // Revenue por dia
                $revenueByDay = Database::fetchAll(
                    "SELECT DATE(created_at) AS d,
                            COUNT(*) AS orders,
                            COALESCE(SUM(total), 0) AS revenue,
                            COALESCE(AVG(total), 0) AS avg_ticket,
                            SUM(is_ai_generated = 1) AS ai_orders
                     FROM orders
                     WHERE tenant_id = :t AND created_at >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
                       AND status NOT IN ('cancelled')
                     GROUP BY DATE(created_at) ORDER BY d ASC",
                    ['t' => $tenantId]
                );
                $revLabels = []; $revTotals = []; $revOrders = []; $revAi = [];
                for ($i = $days - 1; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $revLabels[] = date('d/m', strtotime($date));
                    $found = array_filter($revenueByDay, fn ($r) => $r['d'] === $date);
                    $found = $found ? array_values($found)[0] : null;
                    $revTotals[] = $found ? (float) $found['revenue']  : 0;
                    $revOrders[] = $found ? (int)   $found['orders']   : 0;
                    $revAi[]     = $found ? (int)   $found['ai_orders']: 0;
                }

                // Top items
                $topItems = Database::fetchAll(
                    "SELECT oi.name,
                            SUM(oi.qty) AS units,
                            COALESCE(SUM(oi.subtotal), 0) AS revenue,
                            COUNT(DISTINCT oi.order_id) AS orders_count
                     FROM order_items oi
                     INNER JOIN orders o ON o.id = oi.order_id
                     WHERE oi.tenant_id = :t
                       AND o.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
                       AND o.status NOT IN ('cancelled')
                     GROUP BY oi.name
                     ORDER BY units DESC LIMIT 12",
                    ['t' => $tenantId]
                );

                // Distribucion por status
                $statusDist = Database::fetchAll(
                    "SELECT status, COUNT(*) AS total, COALESCE(SUM(total), 0) AS revenue
                     FROM orders WHERE tenant_id = :t AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
                     GROUP BY status",
                    ['t' => $tenantId]
                );

                // Por tipo de entrega
                $byType = Database::fetchAll(
                    "SELECT delivery_type, COUNT(*) AS total, COALESCE(SUM(total), 0) AS revenue, COALESCE(AVG(total), 0) AS avg
                     FROM orders WHERE tenant_id = :t AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY) AND status NOT IN ('cancelled')
                     GROUP BY delivery_type",
                    ['t' => $tenantId]
                );

                // Resumen general
                $summary = Database::fetch(
                    "SELECT COUNT(*) AS total_orders,
                            COALESCE(SUM(total), 0) AS total_revenue,
                            COALESCE(AVG(total), 0) AS avg_ticket,
                            SUM(is_ai_generated = 1) AS ai_orders,
                            SUM(status = 'cancelled') AS cancelled,
                            SUM(status = 'delivered') AS completed
                     FROM orders WHERE tenant_id = :t AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)",
                    ['t' => $tenantId]
                ) ?: [];

                // Conversion: conversaciones unicas con mensaje inbound vs ordenes creadas
                $totalConvs = (int) Database::fetchColumn(
                    "SELECT COUNT(DISTINCT c.id) FROM conversations c
                     INNER JOIN messages m ON m.conversation_id = c.id
                     WHERE c.tenant_id = :t AND m.direction = 'inbound'
                       AND m.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)",
                    ['t' => $tenantId]
                );
                $totalOrders = (int) ($summary['total_orders'] ?? 0);
                $convRate = $totalConvs > 0 ? round(($totalOrders / $totalConvs) * 100, 1) : 0;

                $restaurant = [
                    'rev_labels'  => $revLabels,
                    'rev_totals'  => $revTotals,
                    'rev_orders'  => $revOrders,
                    'rev_ai'      => $revAi,
                    'top_items'   => $topItems,
                    'status_dist' => $statusDist,
                    'by_type'     => $byType,
                    'summary'     => $summary,
                    'total_convs' => $totalConvs,
                    'conv_rate'   => $convRate,
                    'currency'    => (string) ($tenant['currency'] ?? 'USD'),
                ];
            } catch (\Throwable) {}
        }

        $this->view('reports.index', [
            'page'        => 'reportes',
            'days'        => $days,
            'labels'      => $labels,
            'inbound'     => $inb,
            'outbound'    => $outb,
            'agents'      => $agents,
            'byStage'     => $byStage,
            'sentiment'   => $sentiment,
            'ticketStats' => $ticketStats,
            'channels'    => $channels,
            'hours'       => $hours,
            'tenant'      => $tenant,
            'restaurant'  => $restaurant,
        ], 'layouts.app');
    }

    public function exportCsv(Request $request): void
    {
        $tenantId = Tenant::id();
        $days = (int) ($request->query('days') ?? 30);
        $rows = Database::fetchAll(
            "SELECT DATE(created_at) AS dia, direction, COUNT(*) AS total
             FROM messages WHERE tenant_id = :t AND created_at >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
             GROUP BY DATE(created_at), direction ORDER BY dia ASC",
            ['t' => $tenantId]
        );

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="reporte-' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Dia', 'Direccion', 'Total']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['dia'], $r['direction'], $r['total']]);
        }
        fclose($out);
        exit;
    }
}
