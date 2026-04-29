<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Tenant;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Message;

final class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $tenantId = Tenant::id();
        if ($tenantId === null) {
            $this->redirect('/login');
            return;
        }

        $tenantData = Tenant::current();

        // Metricas
        $stats = [
            'contacts_total'   => (int) Database::fetchColumn("SELECT COUNT(*) FROM contacts WHERE tenant_id = :t AND deleted_at IS NULL", ['t' => $tenantId]),
            'leads_total'      => (int) Database::fetchColumn("SELECT COUNT(*) FROM leads WHERE tenant_id = :t AND deleted_at IS NULL", ['t' => $tenantId]),
            'leads_open'       => (int) Database::fetchColumn("SELECT COUNT(*) FROM leads WHERE tenant_id = :t AND status = 'open' AND deleted_at IS NULL", ['t' => $tenantId]),
            'leads_won'        => (int) Database::fetchColumn("SELECT COUNT(*) FROM leads WHERE tenant_id = :t AND status = 'won' AND deleted_at IS NULL", ['t' => $tenantId]),
            'pipeline_value'   => Lead::totalValue($tenantId, 'open'),
            'conv_open'        => Conversation::countByStatus($tenantId, 'open') + Conversation::countByStatus($tenantId, 'new') + Conversation::countByStatus($tenantId, 'pending'),
            'conv_closed_30d'  => (int) Database::fetchColumn("SELECT COUNT(*) FROM conversations WHERE tenant_id = :t AND status IN ('resolved','closed') AND closed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", ['t' => $tenantId]),
            'tickets_open'     => (int) Database::fetchColumn("SELECT COUNT(*) FROM tickets WHERE tenant_id = :t AND status NOT IN ('resolved','closed') AND deleted_at IS NULL", ['t' => $tenantId]),
            'campaigns_sent'   => (int) Database::fetchColumn("SELECT COUNT(*) FROM campaigns WHERE tenant_id = :t AND status = 'completed'", ['t' => $tenantId]),
            'msg_sent_24h'     => (int) Database::fetchColumn("SELECT COUNT(*) FROM messages WHERE tenant_id = :t AND direction = 'outbound' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)", ['t' => $tenantId]),
            'msg_received_24h' => (int) Database::fetchColumn("SELECT COUNT(*) FROM messages WHERE tenant_id = :t AND direction = 'inbound' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)", ['t' => $tenantId]),
            'hot_leads'        => (int) Database::fetchColumn("SELECT COUNT(*) FROM leads WHERE tenant_id = :t AND ai_score >= 70 AND status = 'open' AND deleted_at IS NULL", ['t' => $tenantId]),
        ];

        // Tasa de respuesta = msgs salientes / entrantes (cap 100%)
        $stats['response_rate'] = $stats['msg_received_24h'] > 0
            ? min(100, (int) round(($stats['msg_sent_24h'] / $stats['msg_received_24h']) * 100))
            : 0;

        // Ventas estimadas (suma de pipeline ponderada por probabilidad)
        $stats['estimated_sales'] = (float) Database::fetchColumn(
            "SELECT COALESCE(SUM(value * probability / 100), 0) FROM leads
             WHERE tenant_id = :t AND status = 'open' AND deleted_at IS NULL",
            ['t' => $tenantId]
        );

        // Ultimas conversaciones
        $recentConversations = Conversation::listOpen($tenantId, 8);

        // Actividad por dia (ultimos 14 dias)
        $chartData = $this->buildChartData($tenantId);

        // Heatmap: actividad por hora del dia x dia de la semana (ultimos 30 dias)
        $heatmap = $this->buildHeatmap($tenantId);

        // Distribucion de leads por etapa
        $stageDistribution = Database::fetchAll(
            "SELECT s.name, s.color, s.probability,
                    COUNT(l.id) AS total,
                    COALESCE(SUM(l.value), 0) AS value
             FROM pipeline_stages s
             LEFT JOIN leads l ON l.stage_id = s.id AND l.status = 'open' AND l.deleted_at IS NULL
             WHERE s.tenant_id = :t
             GROUP BY s.id
             ORDER BY s.sort_order ASC",
            ['t' => $tenantId]
        );

        // Sentimiento (basado en conversaciones)
        $sentiment = Database::fetchAll(
            "SELECT ai_sentiment, COUNT(*) AS total
             FROM conversations
             WHERE tenant_id = :t AND ai_sentiment IS NOT NULL
                AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY ai_sentiment",
            ['t' => $tenantId]
        );

        // Actividad por agente
        $agentActivity = Database::fetchAll(
            "SELECT u.id, u.first_name, u.last_name, u.avatar,
                    COUNT(DISTINCT c.id) AS conversations,
                    COUNT(DISTINCT m.id) AS messages_sent
             FROM users u
             LEFT JOIN conversations c ON c.assigned_to = u.id AND c.tenant_id = :t1
             LEFT JOIN messages m ON m.user_id = u.id AND m.direction = 'outbound' AND m.tenant_id = :t2 AND m.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             WHERE u.tenant_id = :t3 AND u.deleted_at IS NULL AND u.is_active = 1
             GROUP BY u.id
             ORDER BY messages_sent DESC
             LIMIT 5",
            ['t1' => $tenantId, 't2' => $tenantId, 't3' => $tenantId]
        );

        // Feed reciente: ultimos eventos del tenant (audit_logs)
        $activityFeed = Database::fetchAll(
            "SELECT a.action, a.entity_type, a.entity_id, a.created_at,
                    u.first_name, u.last_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.tenant_id = :t
             ORDER BY a.created_at DESC
             LIMIT 8",
            ['t' => $tenantId]
        );

        // Alertas
        $alerts = [];
        if (!empty($tenantData['trial_ends_at']) && strtotime($tenantData['trial_ends_at']) - time() < 86400 * 3) {
            $alerts[] = ['type' => 'warning', 'msg' => 'Tu periodo de prueba expira pronto. Actualiza tu plan para no interrumpir el servicio.'];
        }
        if (empty($tenantData['wasapi_api_key'])) {
            $alerts[] = ['type' => 'info', 'msg' => 'Conecta tu cuenta de Wasapi para empezar a recibir mensajes de WhatsApp.'];
        }
        $unverified = Auth::user()['email_verified_at'] ?? null;
        if ($unverified === null) {
            $alerts[] = ['type' => 'info', 'msg' => 'Verifica tu correo electronico para desbloquear todas las funcionalidades.'];
        }

        $this->view('dashboard.index', [
            'stats'                => $stats,
            'recentConversations'  => $recentConversations,
            'agentActivity'        => $agentActivity,
            'chartData'            => $chartData,
            'heatmap'              => $heatmap,
            'stageDistribution'    => $stageDistribution,
            'sentiment'            => $sentiment,
            'activityFeed'         => $activityFeed,
            'alerts'               => $alerts,
            'tenantData'           => $tenantData,
            'page'                 => 'dashboard',
        ], 'layouts.app');
    }

    private function buildHeatmap(int $tenantId): array
    {
        $rows = Database::fetchAll(
            "SELECT HOUR(created_at) AS h, DAYOFWEEK(created_at) AS dow, COUNT(*) AS total
             FROM messages
             WHERE tenant_id = :t AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY h, dow",
            ['t' => $tenantId]
        );

        // 7 dias x 24 horas
        $grid = [];
        for ($d = 0; $d < 7; $d++) {
            for ($h = 0; $h < 24; $h++) {
                $grid[$d][$h] = 0;
            }
        }
        $max = 1;
        foreach ($rows as $r) {
            $d = ((int) $r['dow'] - 1) % 7; // 1=Sun ... 7=Sat -> 0..6
            $h = (int) $r['h'];
            $grid[$d][$h] = (int) $r['total'];
            if ($grid[$d][$h] > $max) $max = $grid[$d][$h];
        }
        return ['grid' => $grid, 'max' => $max];
    }

    private function buildChartData(int $tenantId): array
    {
        $rows = Database::fetchAll(
            "SELECT DATE(created_at) AS d,
                    SUM(direction = 'inbound')  AS inbound,
                    SUM(direction = 'outbound') AS outbound
             FROM messages
             WHERE tenant_id = :t AND created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
             GROUP BY DATE(created_at)
             ORDER BY d ASC",
            ['t' => $tenantId]
        );

        $labels = [];
        $inbound = [];
        $outbound = [];

        // Rellenar 14 dias completos
        for ($i = 13; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M d', strtotime($date));
            $found = array_filter($rows, fn($r) => $r['d'] === $date);
            $found = $found ? array_values($found)[0] : null;
            $inbound[]  = $found ? (int) $found['inbound']  : 0;
            $outbound[] = $found ? (int) $found['outbound'] : 0;
        }

        return [
            'labels'   => $labels,
            'inbound'  => $inbound,
            'outbound' => $outbound,
        ];
    }
}
