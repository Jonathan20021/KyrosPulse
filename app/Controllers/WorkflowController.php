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
use App\Models\Workflow;
use App\Models\WorkflowTemplate;
use App\Services\WorkflowActionCatalog;
use App\Services\WorkflowEngine;
use App\Services\WorkflowTemplateService;

/**
 * Admin UI para workflows.
 *
 *   GET    /workflows                              listar
 *   POST   /workflows                              crear (devuelve id, redirige a edit)
 *   GET    /workflows/{id}                         editar (steps + runs recientes)
 *   POST   /workflows/{id}                         actualizar metadatos
 *   POST   /workflows/{id}/toggle                  activar/pausar
 *   POST   /workflows/{id}/delete                  eliminar
 *   POST   /workflows/{id}/run-now                 disparar manualmente
 *   POST   /workflows/{id}/steps                   agregar step
 *   POST   /workflows/{id}/steps/{step_id}/update  editar step
 *   POST   /workflows/{id}/steps/{step_id}/delete  borrar step
 *   GET    /workflows/{id}/runs/{run_id}           ver detalle de un run
 */
final class WorkflowController extends Controller
{
    public function index(Request $request): void
    {
        $tenantId = Tenant::id();
        $rows = [];
        try { $rows = Workflow::listForTenant($tenantId); }
        catch (\Throwable $e) { \App\Core\Logger::warning('Workflow list fallo', ['msg' => $e->getMessage()]); }

        $featuredTemplates = [];
        try { $featuredTemplates = array_slice(WorkflowTemplate::listAvailable($tenantId), 0, 3); }
        catch (\Throwable $e) { \App\Core\Logger::warning('WorkflowTemplate list fallo', ['msg' => $e->getMessage()]); }

        $this->view('workflows.index', [
            'page'              => 'automations',
            'workflows'         => $rows,
            'featuredTemplates' => $featuredTemplates,
        ], 'layouts.app');
    }

    /** GET /workflows/templates — galeria del marketplace. */
    public function templatesGallery(Request $request): void
    {
        $tenantId = Tenant::id();
        $category = (string) $request->query('category', '');

        $templates = [];
        $categories = [];
        try {
            $templates = WorkflowTemplate::listAvailable($tenantId, $category !== '' ? $category : null);
            $categories = WorkflowTemplate::listCategories($tenantId);
        } catch (\Throwable $e) {
            \App\Core\Logger::warning('WorkflowTemplate listing fallo', ['msg' => $e->getMessage()]);
        }

        // Marca cuales templates cumplen requires
        foreach ($templates as &$t) {
            $req = $t['requires'] ? json_decode((string) $t['requires'], true) : null;
            $t['_meets']    = WorkflowTemplateService::tenantMeetsRequires($tenantId, is_array($req) ? $req : null);
            $t['_requires'] = is_array($req) ? $req : [];
            // Pre-parse steps_count para preview
            $def = json_decode((string) $t['definition'], true);
            $t['_steps_count'] = is_array($def) && isset($def['steps']) && is_array($def['steps']) ? count($def['steps']) : 0;
            $t['_trigger_type'] = is_array($def) ? (string) ($def['trigger_type'] ?? 'manual') : 'manual';
        }
        unset($t);

        $this->view('workflows.templates', [
            'page'        => 'automations',
            'templates'   => $templates,
            'categories'  => $categories,
            'active_cat'  => $category,
        ], 'layouts.app');
    }

    /** GET /workflows/templates/{id} — preview detalle de template. */
    public function templateShow(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $tpl = WorkflowTemplate::findById($tenantId, $id);
        if (!$tpl) {
            Session::flash('error', 'Template no encontrado.');
            $this->redirect('/workflows/templates');
            return;
        }
        $def = json_decode((string) $tpl['definition'], true) ?: [];
        $req = $tpl['requires'] ? json_decode((string) $tpl['requires'], true) : null;
        $tpl['_meets']    = WorkflowTemplateService::tenantMeetsRequires($tenantId, is_array($req) ? $req : null);
        $tpl['_requires'] = is_array($req) ? $req : [];

        $this->view('workflows.template_show', [
            'page'        => 'automations',
            'template'    => $tpl,
            'definition'  => $def,
        ], 'layouts.app');
    }

    /** POST /workflows/templates/{id}/use — clona template y redirige al editor. */
    public function templateUse(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $tpl = WorkflowTemplate::findById($tenantId, $id);
        if (!$tpl) {
            Session::flash('error', 'Template no encontrado.');
            $this->redirect('/workflows/templates');
            return;
        }
        $req = $tpl['requires'] ? json_decode((string) $tpl['requires'], true) : null;
        if (!WorkflowTemplateService::tenantMeetsRequires($tenantId, is_array($req) ? $req : null)) {
            Session::flash('error', 'Tu cuenta no cumple los requisitos para usar este template.');
            $this->redirect('/workflows/templates');
            return;
        }
        try {
            $name = trim((string) $request->input('name', '')) ?: null;
            $wfId = WorkflowTemplateService::clone($tenantId, $id, Auth::id(), $name);
            Session::flash('success', 'Template clonado. Revisa los steps, edita lo que necesites y activa el workflow.');
            $this->redirect('/workflows/' . $wfId);
        } catch (\Throwable $e) {
            Session::flash('error', 'No se pudo clonar: ' . $e->getMessage());
            $this->redirect('/workflows/templates');
        }
    }

    public function store(Request $request): void
    {
        $tenantId = Tenant::id();
        $name = trim((string) $request->input('name', ''));
        $triggerType = (string) $request->input('trigger_type', 'event');
        if (!in_array($triggerType, ['event','schedule','webhook','manual'], true)) {
            Session::flash('error', 'Trigger invalido.');
            $this->redirect('/workflows');
            return;
        }
        if ($name === '') {
            Session::flash('error', 'Nombre requerido.');
            $this->redirect('/workflows');
            return;
        }

        $cfg = [];
        switch ($triggerType) {
            case 'event':
                $cfg['event'] = trim((string) $request->input('trigger_event', ''));
                if ($cfg['event'] === '') $cfg['event'] = '*';
                break;
            case 'schedule':
                $cfg['cron'] = trim((string) $request->input('trigger_cron', '0 9 * * *'));
                break;
            case 'webhook':
            case 'manual':
                break;
        }

        $token = $triggerType === 'webhook' ? Workflow::generateWebhookToken() : null;

        $id = Workflow::create([
            'tenant_id'      => $tenantId,
            'name'           => mb_substr($name, 0, 120),
            'description'    => mb_substr((string) $request->input('description', ''), 0, 500) ?: null,
            'trigger_type'   => $triggerType,
            'trigger_config' => json_encode($cfg, JSON_UNESCAPED_UNICODE),
            'webhook_token'  => $token,
            'is_active'      => 1,
            'created_by'     => Auth::id(),
        ]);

        Audit::log('workflow.create', 'workflow', $id, [], ['name' => $name, 'trigger_type' => $triggerType]);
        Session::flash('success', 'Workflow creado. Agrega steps para activarlo.');
        $this->redirect('/workflows/' . $id);
    }

    public function edit(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $wf = Workflow::findById($tenantId, $id);
        if (!$wf) {
            Session::flash('error', 'Workflow no encontrado.');
            $this->redirect('/workflows');
            return;
        }
        $steps = Workflow::listSteps($id);
        $runs  = Workflow::listRunsForWorkflow($tenantId, $id, 25);
        $publicUrl = $wf['webhook_token'] ? url('/workflows/run/' . (string) $wf['webhook_token']) : null;

        // Modo: visual (default, drag-drop) o json (escape hatch para usuarios avanzados)
        $mode = (string) $request->query('mode', 'visual');
        $view = $mode === 'json' ? 'workflows.edit' : 'workflows.edit_visual';

        $this->view($view, [
            'page'      => 'automations',
            'workflow'  => $wf,
            'steps'     => $steps,
            'runs'      => $runs,
            'publicUrl' => $publicUrl,
        ], 'layouts.app');
    }

    public function update(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $wf = Workflow::findById($tenantId, $id);
        if (!$wf) {
            Session::flash('error', 'Workflow no encontrado.');
            $this->redirect('/workflows');
            return;
        }
        $patch = [];
        if (($v = trim((string) $request->input('name', ''))) !== '')        $patch['name'] = mb_substr($v, 0, 120);
        if (($v = trim((string) $request->input('description', ''))) !== '') $patch['description'] = mb_substr($v, 0, 500);

        // Trigger config edicion
        $cfg = $wf['trigger_config'] ? json_decode((string) $wf['trigger_config'], true) : [];
        if (!is_array($cfg)) $cfg = [];
        if ($wf['trigger_type'] === 'event' && ($v = trim((string) $request->input('trigger_event', ''))) !== '') {
            $cfg['event'] = $v;
            $patch['trigger_config'] = json_encode($cfg, JSON_UNESCAPED_UNICODE);
        }
        if ($wf['trigger_type'] === 'schedule' && ($v = trim((string) $request->input('trigger_cron', ''))) !== '') {
            $cfg['cron'] = $v;
            $patch['trigger_config'] = json_encode($cfg, JSON_UNESCAPED_UNICODE);
            $patch['next_run_at']    = null;
        }

        if ($patch) Workflow::update($tenantId, $id, $patch);
        Session::flash('success', 'Workflow actualizado.');
        $this->redirect('/workflows/' . $id);
    }

    public function toggle(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $wf = Workflow::findById($tenantId, $id);
        if (!$wf) { $this->redirect('/workflows'); return; }
        Workflow::update($tenantId, $id, ['is_active' => empty($wf['is_active']) ? 1 : 0]);
        Session::flash('success', empty($wf['is_active']) ? 'Workflow activado.' : 'Workflow pausado.');
        $this->redirect('/workflows/' . $id);
    }

    public function delete(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $count = Workflow::delete($tenantId, $id);
        if ($count > 0) {
            Audit::log('workflow.delete', 'workflow', $id);
            Session::flash('success', 'Workflow eliminado.');
        }
        $this->redirect('/workflows');
    }

    public function runNow(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $wf = Workflow::findById($tenantId, $id);
        if (!$wf) { $this->redirect('/workflows'); return; }

        $payloadRaw = trim((string) $request->input('payload', ''));
        $ctx = ['triggered_by' => Auth::id(), 'manual_at' => date('c')];
        if ($payloadRaw !== '') {
            $decoded = json_decode($payloadRaw, true);
            if (is_array($decoded)) $ctx['payload'] = $decoded;
        }
        $runId = WorkflowEngine::triggerManual($tenantId, $id, $ctx);
        if ($runId) {
            Audit::log('workflow.run_manual', 'workflow', $id, [], ['run_id' => $runId]);
            Session::flash('success', 'Run #' . $runId . ' iniciado.');
        } else {
            Session::flash('error', 'No se pudo iniciar (verifica que tenga steps).');
        }
        $this->redirect('/workflows/' . $id);
    }

    // ---- Steps ----

    public function addStep(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $wf = Workflow::findById($tenantId, $id);
        if (!$wf) { $this->redirect('/workflows'); return; }

        $stepKey = preg_replace('/[^a-z0-9_]/', '_', strtolower((string) $request->input('step_key', '')));
        $type = (string) $request->input('type', 'action');
        if (!in_array($type, ['action','branch','delay','set_var','end'], true)) {
            Session::flash('error', 'Tipo de step invalido.');
            $this->redirect('/workflows/' . $id);
            return;
        }
        if ($stepKey === '' || !preg_match('/^[a-z][a-z0-9_]{0,39}$/', $stepKey)) {
            Session::flash('error', 'step_key invalido (a-z, 0-9, _, max 40 chars).');
            $this->redirect('/workflows/' . $id);
            return;
        }
        if (Workflow::findStep($id, $stepKey)) {
            Session::flash('error', 'Ya existe un step con ese key.');
            $this->redirect('/workflows/' . $id);
            return;
        }

        $cfgRaw = trim((string) $request->input('config_json', '{}'));
        $cfg = json_decode($cfgRaw, true);
        if (!is_array($cfg)) {
            Session::flash('error', 'config_json no es JSON valido.');
            $this->redirect('/workflows/' . $id);
            return;
        }

        $orderIndex = (int) Database::fetchColumn(
            "SELECT COALESCE(MAX(order_index), 0) + 10 FROM `workflow_steps` WHERE `workflow_id` = :w",
            ['w' => $id]
        );

        Workflow::createStep([
            'workflow_id'   => $id,
            'tenant_id'     => $tenantId,
            'step_key'      => $stepKey,
            'type'          => $type,
            'order_index'   => $orderIndex,
            'config'        => json_encode($cfg, JSON_UNESCAPED_UNICODE),
            'next_step_key' => $request->input('next_step_key') ?: null,
            'branch_yes'    => $request->input('branch_yes') ?: null,
            'branch_no'     => $request->input('branch_no') ?: null,
        ]);
        Session::flash('success', "Step '$stepKey' agregado.");
        $this->redirect('/workflows/' . $id);
    }

    public function updateStep(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $stepId = (int) ($params['step_id'] ?? 0);

        $cfgRaw = trim((string) $request->input('config_json', '{}'));
        $cfg = json_decode($cfgRaw, true);
        if (!is_array($cfg)) {
            Session::flash('error', 'config_json no es JSON valido.');
            $this->redirect('/workflows/' . $id);
            return;
        }
        Workflow::updateStep($tenantId, $stepId, [
            'config'        => json_encode($cfg, JSON_UNESCAPED_UNICODE),
            'next_step_key' => $request->input('next_step_key') ?: null,
            'branch_yes'    => $request->input('branch_yes') ?: null,
            'branch_no'     => $request->input('branch_no') ?: null,
        ]);
        Session::flash('success', 'Step actualizado.');
        $this->redirect('/workflows/' . $id);
    }

    public function deleteStep(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $stepId = (int) ($params['step_id'] ?? 0);
        Workflow::deleteStep($tenantId, $stepId);
        Session::flash('success', 'Step eliminado.');
        $this->redirect('/workflows/' . $id);
    }

    public function showRun(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $runId = (int) ($params['run_id'] ?? 0);

        $wf = Workflow::findById($tenantId, $id);
        $run = Workflow::findRun($tenantId, $runId);
        if (!$wf || !$run) {
            Session::flash('error', 'Run no encontrado.');
            $this->redirect('/workflows/' . $id);
            return;
        }
        $steps = Workflow::listRunSteps($runId, 200);
        $this->view('workflows.run', [
            'page'     => 'automations',
            'workflow' => $wf,
            'run'      => $run,
            'steps'    => $steps,
        ], 'layouts.app');
    }

    // ===========================================================================
    // AJAX endpoints (JSON) usados por el editor visual drag-drop
    // ===========================================================================

    /** GET /workflows/{id}/catalog.json — schema de step types + actions + agents + variables. */
    public function catalogJson(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $wf = Workflow::findById($tenantId, $id);
        if (!$wf) { $this->json(['error' => 'not_found'], 404); return; }

        $this->json([
            'data' => [
                'catalog'   => WorkflowActionCatalog::full(),
                'agents'    => WorkflowActionCatalog::agentsForSelect(),
                'variables' => WorkflowActionCatalog::variablesForWorkflow($wf),
            ],
        ]);
    }

    /** GET /workflows/{id}/steps.json — lista de steps con config decodificado. */
    public function stepsJson(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $wf = Workflow::findById($tenantId, $id);
        if (!$wf) { $this->json(['error' => 'not_found'], 404); return; }

        $rows = Workflow::listSteps($id);
        $items = array_map(fn($s) => $this->serializeStep($s), $rows);
        $this->json(['data' => $items]);
    }

    /** POST /workflows/{id}/steps.json — body: {step_key,type,config,next_step_key,branch_yes,branch_no} */
    public function stepCreateJson(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $wf = Workflow::findById($tenantId, $id);
        if (!$wf) { $this->json(['error' => 'not_found'], 404); return; }

        $stepKey = preg_replace('/[^a-z0-9_]/', '_', strtolower((string) $request->input('step_key', '')));
        $type    = (string) $request->input('type', 'action');
        $config  = $request->input('config');

        if (!is_array($config)) $config = [];
        if (!in_array($type, ['action','branch','delay','set_var','end'], true)) {
            $this->json(['error' => 'invalid_type'], 422);
            return;
        }
        if ($stepKey === '' || !preg_match('/^[a-z][a-z0-9_]{0,39}$/', $stepKey)) {
            $this->json(['error' => 'invalid_step_key', 'message' => 'a-z, 0-9, _, max 40 chars, debe empezar con letra'], 422);
            return;
        }
        if (Workflow::findStep($id, $stepKey)) {
            $this->json(['error' => 'duplicate_step_key'], 409);
            return;
        }

        $orderIndex = (int) Database::fetchColumn(
            "SELECT COALESCE(MAX(order_index), 0) + 10 FROM `workflow_steps` WHERE `workflow_id` = :w",
            ['w' => $id]
        );

        $stepId = Workflow::createStep([
            'workflow_id'   => $id,
            'tenant_id'     => $tenantId,
            'step_key'      => $stepKey,
            'type'          => $type,
            'order_index'   => $orderIndex,
            'config'        => json_encode($config, JSON_UNESCAPED_UNICODE),
            'next_step_key' => $request->input('next_step_key') ?: null,
            'branch_yes'    => $request->input('branch_yes')    ?: null,
            'branch_no'     => $request->input('branch_no')     ?: null,
        ]);

        $row = Database::fetch("SELECT * FROM `workflow_steps` WHERE `id` = :i", ['i' => $stepId]);
        $this->json(['data' => $this->serializeStep($row)], 201);
    }

    /** PATCH /workflows/{id}/steps/{step_id}.json — body: {config?, next_step_key?, branch_yes?, branch_no?} */
    public function stepUpdateJson(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $stepId = (int) ($params['step_id'] ?? 0);

        $patch = [];
        $config = $request->input('config');
        if (is_array($config)) $patch['config'] = json_encode($config, JSON_UNESCAPED_UNICODE);

        foreach (['next_step_key','branch_yes','branch_no'] as $f) {
            $v = $request->input($f);
            if ($v !== null) {
                $patch[$f] = $v === '' ? null : (string) $v;
            }
        }

        if (!$patch) { $this->json(['error' => 'no_fields'], 422); return; }

        Workflow::updateStep($tenantId, $stepId, $patch);
        $row = Database::fetch("SELECT * FROM `workflow_steps` WHERE `id` = :i AND `tenant_id` = :t", ['i' => $stepId, 't' => $tenantId]);
        if (!$row) { $this->json(['error' => 'not_found'], 404); return; }
        $this->json(['data' => $this->serializeStep($row)]);
    }

    /** POST /workflows/{id}/steps/{step_id}/delete.json */
    public function stepDeleteJson(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $stepId = (int) ($params['step_id'] ?? 0);
        Workflow::deleteStep($tenantId, $stepId);
        $this->json(['data' => ['deleted' => $stepId]]);
    }

    /**
     * POST /workflows/{id}/steps/reorder.json
     * Body: { "order": [step_id_1, step_id_2, ...] }
     * Reasigna order_index respetando el orden recibido (10, 20, 30...).
     * Tambien re-cablea next_step_key automaticamente para steps que NO sean
     * branch/end y NO tengan branch_yes/branch_no, preservando branches manuales.
     */
    public function stepsReorderJson(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $wf = Workflow::findById($tenantId, $id);
        if (!$wf) { $this->json(['error' => 'not_found'], 404); return; }

        $order = $request->input('order', []);
        if (!is_array($order) || empty($order)) {
            $this->json(['error' => 'invalid_order'], 422);
            return;
        }

        $existing = Database::fetchAll(
            "SELECT * FROM `workflow_steps` WHERE `workflow_id` = :w AND `tenant_id` = :t",
            ['w' => $id, 't' => $tenantId]
        );
        $byId = [];
        foreach ($existing as $s) $byId[(int) $s['id']] = $s;

        Database::transaction(function ($pdo) use ($order, $id, $tenantId, $byId) {
            $idx = 10;
            $orderedSteps = [];
            foreach ($order as $sid) {
                $sid = (int) $sid;
                if (!isset($byId[$sid])) continue;
                $stmt = $pdo->prepare("UPDATE `workflow_steps` SET `order_index` = :o WHERE `id` = :i AND `workflow_id` = :w AND `tenant_id` = :t");
                $stmt->execute(['o' => $idx, 'i' => $sid, 'w' => $id, 't' => $tenantId]);
                $orderedSteps[] = $byId[$sid];
                $idx += 10;
            }

            // Re-cablear next_step_key linealmente para steps NO-branch que no
            // tengan ya un siguiente explicito custom. Para no romper flows
            // que el usuario configuro a mano, solo actualizamos cuando el
            // next actual ES OBVIAMENTE el step inmediato siguiente o cuando
            // esta vacio.
            for ($i = 0; $i < count($orderedSteps); $i++) {
                $cur = $orderedSteps[$i];
                if ($cur['type'] === 'end' || $cur['type'] === 'branch') continue;

                $nextKey = $orderedSteps[$i + 1]['step_key'] ?? null;
                $currentNext = $cur['next_step_key'] ?? null;

                // Solo re-conectar si el next esta vacio o apuntaba al step
                // que era inmediato siguiente antes del reorden.
                $shouldRewire = ($currentNext === null || $currentNext === '');

                if ($shouldRewire) {
                    $stmt = $pdo->prepare("UPDATE `workflow_steps` SET `next_step_key` = :n WHERE `id` = :i");
                    $stmt->execute(['n' => $nextKey, 'i' => (int) $cur['id']]);
                }
            }
        });

        $rows = Workflow::listSteps($id);
        $this->json(['data' => array_map(fn($s) => $this->serializeStep($s), $rows)]);
    }

    private function serializeStep(?array $s): ?array
    {
        if (!$s) return null;
        $cfg = $s['config'] ? json_decode((string) $s['config'], true) : [];
        if (!is_array($cfg)) $cfg = [];
        return [
            'id'            => (int) $s['id'],
            'step_key'      => (string) $s['step_key'],
            'type'          => (string) $s['type'],
            'order_index'   => (int) $s['order_index'],
            'config'        => $cfg,
            'next_step_key' => $s['next_step_key'] ?? null,
            'branch_yes'    => $s['branch_yes']    ?? null,
            'branch_no'     => $s['branch_no']     ?? null,
        ];
    }
}
