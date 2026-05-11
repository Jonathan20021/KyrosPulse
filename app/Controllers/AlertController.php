<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Tenant;
use App\Services\AlertService;

/**
 * Admin UI para alertas inteligentes.
 *
 *   GET    /settings/alerts                     listar reglas + history
 *   POST   /settings/alerts/{slug}/toggle       activar/pausar
 *   POST   /settings/alerts/{slug}/cooldown     ajustar cooldown_minutes
 *   POST   /settings/alerts/test                disparar una alerta de prueba
 */
final class AlertController extends Controller
{
    public function index(Request $request): void
    {
        $tenantId = Tenant::id();

        $rules = [];
        try { $rules = AlertService::listForTenant($tenantId); }
        catch (\Throwable $e) { \App\Core\Logger::warning('AlertService::listForTenant fallo', ['msg' => $e->getMessage()]); }

        $history = [];
        try { $history = AlertService::historyForTenant($tenantId, 50); }
        catch (\Throwable $e) { \App\Core\Logger::warning('AlertService::historyForTenant fallo', ['msg' => $e->getMessage()]); }

        $destCount = 0;
        try {
            $destCount = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM `notification_destinations` nd
                 WHERE nd.tenant_id = :t AND nd.is_active = 1
                   AND (nd.events IS NULL
                        OR JSON_CONTAINS(nd.events, JSON_QUOTE('alert.fired'))
                        OR JSON_CONTAINS(nd.events, JSON_QUOTE('*')))",
                ['t' => $tenantId]
            );
        } catch (\Throwable $e) {
            \App\Core\Logger::warning('destCount fallo', ['msg' => $e->getMessage()]);
        }

        $this->view('settings.alerts', [
            'page'     => 'configuracion',
            'tab'      => 'alerts',
            'rules'    => $rules,
            'history'  => $history,
            'destCount'=> $destCount,
        ], 'layouts.app');
    }

    public function toggle(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $slug = (string) ($params['slug'] ?? '');
        $active = !empty($request->input('active'));
        AlertService::toggle($tenantId, $slug, $active);
        Audit::log('alert.toggle', 'alert_rule', 0, [], ['slug' => $slug, 'active' => $active]);
        Session::flash('success', $active ? 'Regla activada.' : 'Regla pausada.');
        $this->redirect('/settings/alerts');
    }

    public function cooldown(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $slug = (string) ($params['slug'] ?? '');
        $minutes = max(5, min(10080, (int) $request->input('minutes', 60)));

        // Si la regla es builtin (no tenant override), clonamos primero
        $row = Database::fetch(
            "SELECT * FROM `alert_rules` WHERE `tenant_id` = :t AND `slug` = :s LIMIT 1",
            ['t' => $tenantId, 's' => $slug]
        );
        if (!$row) {
            $builtin = Database::fetch(
                "SELECT * FROM `alert_rules` WHERE `tenant_id` IS NULL AND `slug` = :s LIMIT 1",
                ['s' => $slug]
            );
            if ($builtin) {
                Database::insert('alert_rules', [
                    'tenant_id'        => $tenantId,
                    'slug'             => $builtin['slug'],
                    'name'             => $builtin['name'],
                    'description'      => $builtin['description'],
                    'rule_type'        => $builtin['rule_type'],
                    'config'           => $builtin['config'],
                    'severity'         => $builtin['severity'],
                    'cooldown_minutes' => $minutes,
                    'is_active'        => $builtin['is_active'],
                ]);
            }
        } else {
            Database::update('alert_rules', ['cooldown_minutes' => $minutes], ['id' => (int) $row['id']]);
        }
        Audit::log('alert.cooldown', 'alert_rule', 0, [], ['slug' => $slug, 'minutes' => $minutes]);
        Session::flash('success', 'Cooldown actualizado a ' . $minutes . ' min.');
        $this->redirect('/settings/alerts');
    }

    public function test(Request $request): void
    {
        $tenantId = Tenant::id();
        $ok = AlertService::fire('api.quota.80', $tenantId, [
            'pct'       => 85,
            'used'      => 850,
            'quota'     => 1000,
            'plan'      => 'free',
            'resets_at' => date('Y-m-d', strtotime('+10 days')),
        ]);
        Session::flash($ok ? 'success' : 'error', $ok
            ? 'Alerta de prueba enviada a destinations + registrada en history.'
            : 'No se disparo (regla pausada o en cooldown).'
        );
        $this->redirect('/settings/alerts');
    }
}
