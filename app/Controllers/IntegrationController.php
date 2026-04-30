<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Tenant;
use App\Models\Integration;
use App\Models\Tenant as TenantModel;

final class IntegrationController extends Controller
{
    public function index(Request $request): void
    {
        $tenantId = Tenant::id();
        $catalog  = Integration::catalog();
        $byTenant = Integration::listForTenant($tenantId);

        // Categoria filter
        $cat = (string) $request->query('category', '');
        if ($cat !== '') {
            $catalog = array_values(array_filter($catalog, fn ($e) => $e['category'] === $cat));
        }

        // Search filter
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $catalog = array_values(array_filter($catalog, function ($e) use ($needle) {
                return str_contains(mb_strtolower($e['name']), $needle)
                    || str_contains(mb_strtolower((string) ($e['description'] ?? '')), $needle)
                    || str_contains(mb_strtolower($e['slug']), $needle);
            }));
        }

        $tenant = TenantModel::findById($tenantId);
        $planSlug = '';
        if (!empty($tenant['plan_id'])) {
            $plan = Database::fetch("SELECT slug FROM plans WHERE id = :id", ['id' => (int) $tenant['plan_id']]);
            $planSlug = (string) ($plan['slug'] ?? '');
        }

        // KPIs rapidos
        $totals = [
            'total'      => count(Integration::catalog()),
            'connected'  => 0,
            'disconnected' => 0,
            'errors'     => 0,
        ];
        foreach ($byTenant as $row) {
            if ($row['status'] === 'connected') $totals['connected']++;
            elseif ($row['status'] === 'error') $totals['errors']++;
            else $totals['disconnected']++;
        }
        $totals['disconnected'] = $totals['total'] - $totals['connected'] - $totals['errors'];

        $this->view('settings.integrations_catalog', [
            'page'       => 'configuracion',
            'tab'        => 'integrations',
            'catalog'    => $catalog,
            'tenantInts' => $byTenant,
            'categories' => Integration::CATEGORIES,
            'tenant'     => $tenant,
            'planSlug'   => $planSlug,
            'filterCat'  => $cat,
            'q'          => $q,
            'totals'     => $totals,
        ], 'layouts.app');
    }

    public function show(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $slug = (string) ($params['slug'] ?? '');
        $entry = Integration::findCatalog($slug);
        if (!$entry) $this->abort(404);

        $existing = Integration::findForTenant($tenantId, $slug);
        $existingCreds = [];
        if ($existing && !empty($existing['credentials'])) {
            $decoded = json_decode((string) $existing['credentials'], true);
            if (is_array($decoded)) $existingCreds = $decoded;
        }

        $webhookUrl = url('/webhooks/integration/' . $slug . '/' . (Database::fetchColumn(
            "SELECT uuid FROM tenants WHERE id = :id", ['id' => $tenantId]
        ) ?? ''));

        $this->view('settings.integration_detail', [
            'page'        => 'configuracion',
            'tab'         => 'integrations',
            'entry'       => $entry,
            'existing'    => $existing,
            'creds'       => $existingCreds,
            'webhookUrl'  => $webhookUrl,
            'logs'        => $existing ? Database::fetchAll(
                "SELECT * FROM integration_logs WHERE tenant_id = :t AND slug = :s ORDER BY id DESC LIMIT 25",
                ['t' => $tenantId, 's' => $slug]
            ) : [],
        ], 'layouts.app');
    }

    public function connect(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $slug = (string) ($params['slug'] ?? '');
        $entry = Integration::findCatalog($slug);
        if (!$entry) $this->abort(404);

        $creds = [];
        $config = [];
        foreach ($entry['fields'] ?? [] as $field) {
            $val = (string) $request->input($field['key'], '');
            if (!empty($field['required']) && $val === '') {
                Session::flash('error', 'Falta el campo requerido: ' . $field['label']);
                $this->redirect('/settings/integrations/' . $slug);
                return;
            }
            // Distinguir credenciales vs config publica
            if (in_array($field['type'] ?? '', ['password', 'textarea'], true) || str_contains($field['key'], 'token') || str_contains($field['key'], 'secret') || str_contains($field['key'], 'key')) {
                if ($val !== '') $creds[$field['key']] = $val;
            } else {
                if ($val !== '') $config[$field['key']] = $val;
            }
        }

        // Mantener creds previas si el usuario dejo en blanco campos password
        $existing = Integration::findForTenant($tenantId, $slug);
        if ($existing && !empty($existing['credentials'])) {
            $prev = json_decode((string) $existing['credentials'], true);
            if (is_array($prev)) {
                foreach ($prev as $k => $v) {
                    if (!isset($creds[$k]) || $creds[$k] === '') $creds[$k] = $v;
                }
            }
        }

        Integration::upsert($tenantId, $slug, [
            'is_enabled'   => 1,
            'status'       => 'connected',
            'config'       => json_encode($config, JSON_UNESCAPED_UNICODE),
            'credentials'  => json_encode($creds, JSON_UNESCAPED_UNICODE),
            'connected_at' => date('Y-m-d H:i:s'),
            'connected_by' => Auth::id(),
            'last_error'   => null,
        ]);

        Integration::logEvent($tenantId, $slug, 'connect', ['success' => true]);
        Audit::log('integration.connected', 'integration', null, [], ['slug' => $slug]);

        Session::flash('success', 'Integracion ' . $entry['name'] . ' conectada.');
        $this->redirect('/settings/integrations/' . $slug);
    }

    public function disconnect(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $slug = (string) ($params['slug'] ?? '');
        Integration::disconnect($tenantId, $slug);
        Integration::logEvent($tenantId, $slug, 'disconnect', ['success' => true]);
        Audit::log('integration.disconnected', 'integration', null, [], ['slug' => $slug]);
        Session::flash('success', 'Integracion desconectada.');
        $this->redirect('/settings/integrations');
    }

    public function test(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $slug = (string) ($params['slug'] ?? '');
        $existing = Integration::findForTenant($tenantId, $slug);
        if (!$existing) {
            $this->json(['success' => false, 'error' => 'Integracion no configurada.']);
            return;
        }

        // Test generico: consideramos exitoso si hay credenciales y status connected.
        $hasCreds = !empty($existing['credentials']) && $existing['credentials'] !== '[]';
        $ok = $hasCreds && $existing['status'] === 'connected';

        Integration::logEvent($tenantId, $slug, 'test', ['success' => $ok]);

        if ($ok) {
            $this->json(['success' => true, 'message' => 'Configurada y lista.']);
        } else {
            $this->json(['success' => false, 'error' => 'Faltan credenciales o esta desconectada.']);
        }
    }
}
