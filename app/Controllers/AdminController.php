<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Models\Plan;
use App\Models\Tenant;

final class AdminController extends Controller
{
    public function dashboard(Request $request): void
    {
        $stats = [
            'tenants_total'    => (int) Database::fetchColumn("SELECT COUNT(*) FROM tenants WHERE deleted_at IS NULL"),
            'tenants_active'   => (int) Database::fetchColumn("SELECT COUNT(*) FROM tenants WHERE status = 'active' AND deleted_at IS NULL"),
            'tenants_trial'    => (int) Database::fetchColumn("SELECT COUNT(*) FROM tenants WHERE status = 'trial' AND deleted_at IS NULL"),
            'tenants_suspended'=> (int) Database::fetchColumn("SELECT COUNT(*) FROM tenants WHERE status = 'suspended' AND deleted_at IS NULL"),
            'users_total'      => (int) Database::fetchColumn("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL"),
            'contacts_total'   => (int) Database::fetchColumn("SELECT COUNT(*) FROM contacts WHERE deleted_at IS NULL"),
            'messages_30d'     => (int) Database::fetchColumn("SELECT COUNT(*) FROM messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
            'messages_24h'     => (int) Database::fetchColumn("SELECT COUNT(*) FROM messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"),
            'campaigns_total'  => (int) Database::fetchColumn("SELECT COUNT(*) FROM campaigns"),
            'mrr'              => (float) Database::fetchColumn(
                "SELECT COALESCE(SUM(p.price_monthly), 0)
                 FROM tenants t
                 INNER JOIN plans p ON p.id = t.plan_id
                 WHERE t.status = 'active' AND t.deleted_at IS NULL"
            ),
            'new_signups_7d'   => (int) Database::fetchColumn("SELECT COUNT(*) FROM tenants WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND deleted_at IS NULL"),
            'churn_30d'        => (int) Database::fetchColumn("SELECT COUNT(*) FROM tenants WHERE status = 'cancelled' AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
        ];

        $byPlan = Database::fetchAll(
            "SELECT p.name, p.price_monthly, COUNT(t.id) AS total
             FROM plans p
             LEFT JOIN tenants t ON t.plan_id = p.id AND t.deleted_at IS NULL
             GROUP BY p.id ORDER BY p.sort_order ASC"
        );

        $recentTenants = Database::fetchAll(
            "SELECT t.*, p.name AS plan_name FROM tenants t LEFT JOIN plans p ON p.id = t.plan_id WHERE t.deleted_at IS NULL ORDER BY t.created_at DESC LIMIT 10"
        );

        $errors = Database::fetchAll(
            "SELECT * FROM whatsapp_logs WHERE success = 0 ORDER BY created_at DESC LIMIT 10"
        );

        // Volumen de mensajes por dia (ultimos 14 dias) global
        $messagesChart = $this->buildAdminChart();

        // Top tenants por uso de mensajes
        $topTenants = Database::fetchAll(
            "SELECT t.id, t.name, t.email, COUNT(m.id) AS msg_count
             FROM tenants t
             LEFT JOIN messages m ON m.tenant_id = t.id AND m.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             WHERE t.deleted_at IS NULL
             GROUP BY t.id
             ORDER BY msg_count DESC
             LIMIT 8"
        );

        $this->view('admin.dashboard', [
            'page'           => 'admin',
            'stats'          => $stats,
            'byPlan'         => $byPlan,
            'recentTenants'  => $recentTenants,
            'errors'         => $errors,
            'messagesChart'  => $messagesChart,
            'topTenants'     => $topTenants,
        ], 'layouts.admin');
    }

    private function buildAdminChart(): array
    {
        $rows = Database::fetchAll(
            "SELECT DATE(created_at) AS d, COUNT(*) AS total
             FROM messages
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
             GROUP BY DATE(created_at) ORDER BY d ASC"
        );
        $labels = []; $data = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M d', strtotime($date));
            $found = array_filter($rows, fn($r) => $r['d'] === $date);
            $found = $found ? array_values($found)[0] : null;
            $data[] = $found ? (int) $found['total'] : 0;
        }
        return ['labels' => $labels, 'data' => $data];
    }

    public function tenants(Request $request): void
    {
        $tenants = Tenant::listAll();
        $this->view('admin.tenants', [
            'page'    => 'admin',
            'tenants' => $tenants,
            'plans'   => Plan::listActive(),
        ], 'layouts.admin');
    }

    public function tenantUpdate(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $data = $request->only(['plan_id','status','expires_at']);
        if (empty($data['expires_at'])) $data['expires_at'] = null;
        Database::update('tenants', $data, ['id' => $id]);
        Audit::log('admin.tenant.updated', 'tenant', $id, [], $data);
        Session::flash('success', 'Empresa actualizada.');
        $this->redirect('/admin/tenants');
    }

    public function tenantSuspend(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        Database::update('tenants', ['status' => 'suspended'], ['id' => $id]);
        Session::flash('success', 'Empresa suspendida.');
        $this->redirect('/admin/tenants');
    }

    public function tenantActivate(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        Database::update('tenants', ['status' => 'active'], ['id' => $id]);
        Session::flash('success', 'Empresa activada.');
        $this->redirect('/admin/tenants');
    }

    public function plans(Request $request): void
    {
        $plans = Database::fetchAll("SELECT * FROM plans ORDER BY sort_order ASC");
        $this->view('admin.plans', ['page' => 'admin', 'plans' => $plans], 'layouts.admin');
    }

    public function planUpdate(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $data = $request->only([
            'name','description','price_monthly','price_yearly',
            'max_users','max_contacts','max_messages','max_campaigns','max_automations'
        ]);
        $data['ai_enabled']       = !empty($request->input('ai_enabled')) ? 1 : 0;
        $data['advanced_reports'] = !empty($request->input('advanced_reports')) ? 1 : 0;
        $data['api_access']       = !empty($request->input('api_access')) ? 1 : 0;
        $data['is_active']        = !empty($request->input('is_active')) ? 1 : 0;

        Database::update('plans', $data, ['id' => $id]);
        Session::flash('success', 'Plan actualizado.');
        $this->redirect('/admin/plans');
    }

    public function logs(Request $request): void
    {
        $audit = Database::fetchAll(
            "SELECT a.*, u.first_name, u.last_name, u.email
             FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.created_at DESC LIMIT 200"
        );

        $whatsapp = Database::fetchAll(
            "SELECT wl.*, t.name AS tenant_name FROM whatsapp_logs wl
             LEFT JOIN tenants t ON t.id = wl.tenant_id
             ORDER BY wl.created_at DESC LIMIT 100"
        );

        $email = Database::fetchAll(
            "SELECT * FROM email_logs ORDER BY created_at DESC LIMIT 100"
        );

        $ai = Database::fetchAll(
            "SELECT al.*, t.name AS tenant_name FROM ai_logs al
             LEFT JOIN tenants t ON t.id = al.tenant_id
             ORDER BY al.created_at DESC LIMIT 100"
        );

        $this->view('admin.logs', [
            'page'     => 'admin',
            'audit'    => $audit,
            'whatsapp' => $whatsapp,
            'email'    => $email,
            'ai'       => $ai,
        ], 'layouts.admin');
    }
}
