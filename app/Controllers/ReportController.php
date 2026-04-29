<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Tenant;

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
