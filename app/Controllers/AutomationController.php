<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\Tenant;
use App\Models\Automation;
use App\Models\PipelineStage;
use App\Models\User;

final class AutomationController extends Controller
{
    public function index(Request $request): void
    {
        $tenantId = Tenant::id();
        $this->view('automations.index', [
            'page'        => 'automatizaciones',
            'automations' => Automation::listForTenant($tenantId),
            'triggers'    => Automation::TRIGGERS,
        ], 'layouts.app');
    }

    public function create(Request $request): void
    {
        $tenantId = Tenant::id();
        $this->view('automations.builder', [
            'page'       => 'automatizaciones',
            'automation' => null,
            'errors'     => errors(),
            'triggers'   => Automation::TRIGGERS,
            'conditionTypes' => Automation::CONDITION_TYPES,
            'actionTypes'    => Automation::ACTION_TYPES,
            'agents'         => User::listByTenant($tenantId),
            'stages'         => PipelineStage::listForTenant($tenantId),
        ], 'layouts.app');
    }

    public function store(Request $request): void
    {
        $tenantId = Tenant::id();
        $data = $this->validate($request, [
            'name'          => 'required|min:3|max:150',
            'trigger_event' => 'required',
        ]);

        $conditions = $this->parseRows($request->input('conditions', []));
        $actions    = $this->parseRows($request->input('actions',    []));

        if (empty($actions)) {
            Session::flash('error', 'Agrega al menos una accion.');
            $this->redirect('/automations/create');
            return;
        }

        $id = Automation::create([
            'tenant_id'     => $tenantId,
            'name'          => $data['name'],
            'description'   => $data['description'] ?? null,
            'trigger_event' => $data['trigger_event'],
            'conditions'    => json_encode($conditions, JSON_UNESCAPED_UNICODE),
            'actions'       => json_encode($actions, JSON_UNESCAPED_UNICODE),
            'is_active'     => !empty($data['is_active']) ? 1 : 0,
            'created_by'    => Auth::id(),
        ]);

        Audit::log('automation.created', 'automation', $id);
        Session::flash('success', 'Automatizacion creada.');
        $this->redirect("/automations/$id");
    }

    public function edit(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $auto = Automation::findById($tenantId, $id);
        if (!$auto) $this->abort(404);

        $auto['conditions_arr'] = json_decode((string) $auto['conditions'], true) ?: [];
        $auto['actions_arr']    = json_decode((string) $auto['actions'],    true) ?: [];

        $this->view('automations.builder', [
            'page'           => 'automatizaciones',
            'automation'     => $auto,
            'errors'         => errors(),
            'triggers'       => Automation::TRIGGERS,
            'conditionTypes' => Automation::CONDITION_TYPES,
            'actionTypes'    => Automation::ACTION_TYPES,
            'agents'         => User::listByTenant($tenantId),
            'stages'         => PipelineStage::listForTenant($tenantId),
            'logs'           => Automation::recentLogs($tenantId, $id, 30),
        ], 'layouts.app');
    }

    public function update(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $auto = Automation::findById($tenantId, $id);
        if (!$auto) $this->abort(404);

        $data = $this->validate($request, [
            'name'          => 'required|min:3|max:150',
            'trigger_event' => 'required',
        ]);

        $conditions = $this->parseRows($request->input('conditions', []));
        $actions    = $this->parseRows($request->input('actions', []));

        Automation::update($tenantId, $id, [
            'name'          => $data['name'],
            'description'   => $data['description'] ?? null,
            'trigger_event' => $data['trigger_event'],
            'conditions'    => json_encode($conditions, JSON_UNESCAPED_UNICODE),
            'actions'       => json_encode($actions,    JSON_UNESCAPED_UNICODE),
            'is_active'     => !empty($data['is_active']) ? 1 : 0,
        ]);

        Audit::log('automation.updated', 'automation', $id, $auto, $data);
        Session::flash('success', 'Automatizacion actualizada.');
        $this->redirect("/automations/$id");
    }

    public function toggle(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $auto = Automation::findById($tenantId, $id);
        if (!$auto) $this->abort(404);

        Automation::update($tenantId, $id, ['is_active' => $auto['is_active'] ? 0 : 1]);
        Session::flash('success', $auto['is_active'] ? 'Automatizacion pausada.' : 'Automatizacion activada.');
        $this->redirect('/automations');
    }

    public function destroy(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        Automation::delete($tenantId, $id);
        Session::flash('success', 'Automatizacion eliminada.');
        $this->redirect('/automations');
    }

    /**
     * Acepta un input array de condiciones/acciones del form en la forma:
     *   conditions[0][type]=...
     *   conditions[0][op]=...
     *   conditions[0][value]=...
     *   actions[0][type]=...
     *   actions[0][params][message]=...
     */
    private function parseRows($input): array
    {
        if (!is_array($input)) return [];
        $rows = [];
        foreach ($input as $row) {
            if (!is_array($row)) continue;
            $type = trim((string) ($row['type'] ?? ''));
            if ($type === '') continue;
            $rows[] = [
                'type'   => $type,
                'op'     => $row['op']    ?? 'equals',
                'value'  => $row['value'] ?? null,
                'params' => isset($row['params']) && is_array($row['params']) ? $row['params'] : [],
            ];
        }
        return $rows;
    }
}
