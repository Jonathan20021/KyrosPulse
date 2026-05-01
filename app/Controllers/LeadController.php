<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Events;
use App\Core\Request;
use App\Core\Session;
use App\Core\Tenant;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\ClaudeService;

final class LeadController extends Controller
{
    public function index(Request $request): void
    {
        $tenantId = Tenant::id();
        $stages = PipelineStage::listForTenant($tenantId);

        if (empty($stages)) {
            $this->seedDefaultStages($tenantId);
            $stages = PipelineStage::listForTenant($tenantId);
        }

        $leads = Database::fetchAll(
            "SELECT l.*, c.first_name, c.last_name, c.company, c.phone, c.whatsapp,
                    u.first_name AS agent_first
             FROM leads l
             LEFT JOIN contacts c ON c.id = l.contact_id
             LEFT JOIN users u ON u.id = l.assigned_to
             WHERE l.tenant_id = :t AND l.deleted_at IS NULL
             ORDER BY l.updated_at DESC",
            ['t' => $tenantId]
        );

        $byStage = [];
        $totals  = [];
        foreach ($stages as $s) {
            $byStage[(int) $s['id']] = [];
            $totals[(int) $s['id']]  = ['count' => 0, 'value' => 0.0];
        }
        foreach ($leads as $l) {
            $sid = (int) $l['stage_id'];
            if (!isset($byStage[$sid])) continue;
            $byStage[$sid][] = $l;
            $totals[$sid]['count']++;
            $totals[$sid]['value'] += (float) $l['value'];
        }

        // KPIs globales del pipeline
        $kpis = [
            'total_leads'       => count($leads),
            'open_leads'        => count(array_filter($leads, fn ($l) => ($l['status'] ?? '') === 'open')),
            'won_leads'         => count(array_filter($leads, fn ($l) => ($l['status'] ?? '') === 'won')),
            'lost_leads'        => count(array_filter($leads, fn ($l) => ($l['status'] ?? '') === 'lost')),
            'pipeline_value'    => array_sum(array_map(fn ($l) => ($l['status'] ?? '') === 'open' ? (float) $l['value'] : 0, $leads)),
            'won_value'         => array_sum(array_map(fn ($l) => ($l['status'] ?? '') === 'won' ? (float) $l['value'] : 0, $leads)),
            'weighted_value'    => array_sum(array_map(fn ($l) => ($l['status'] ?? '') === 'open' ? (float) $l['value'] * ((int) ($l['probability'] ?? 0) / 100) : 0, $leads)),
            'ai_generated'      => count(array_filter($leads, fn ($l) => str_starts_with((string) ($l['source'] ?? ''), 'whatsapp_ia'))),
        ];
        $kpis['avg_value']    = $kpis['total_leads'] > 0 ? array_sum(array_column($leads, 'value')) / $kpis['total_leads'] : 0;
        $kpis['win_rate']     = ($kpis['won_leads'] + $kpis['lost_leads']) > 0
            ? round(($kpis['won_leads'] / ($kpis['won_leads'] + $kpis['lost_leads'])) * 100, 1)
            : 0;

        // Won del mes (para tendencia)
        $kpis['won_this_month'] = (float) Database::fetchColumn(
            "SELECT COALESCE(SUM(value), 0) FROM leads
             WHERE tenant_id = :t AND status = 'won' AND deleted_at IS NULL
               AND actual_close >= DATE_FORMAT(NOW(), '%Y-%m-01')",
            ['t' => $tenantId]
        );

        $tenant = \App\Models\Tenant::findById($tenantId);

        $this->view('leads.index', [
            'page'    => 'leads',
            'stages'  => $stages,
            'byStage' => $byStage,
            'totals'  => $totals,
            'kpis'    => $kpis,
            'tenant'  => $tenant,
        ], 'layouts.app');
    }

    public function create(Request $request): void
    {
        $tenantId = Tenant::id();
        $contactId = $request->query('contact');
        $contact = $contactId ? Contact::findById((int) $contactId) : null;

        $this->view('leads.create', [
            'page'     => 'leads',
            'errors'   => errors(),
            'stages'   => PipelineStage::listForTenant($tenantId),
            'agents'   => User::listByTenant($tenantId),
            'contact'  => $contact,
        ], 'layouts.app');
    }

    public function store(Request $request): void
    {
        $tenantId = Tenant::id();
        $data = $this->validate($request, [
            'title'    => 'required|min:3|max:180',
            'stage_id' => 'required|integer',
            'value'    => 'numeric',
        ]);

        $stage = PipelineStage::findById($tenantId, (int) $data['stage_id']);
        if (!$stage) $this->abort(422, 'Etapa invalida.');

        $id = Lead::create([
            'tenant_id'      => $tenantId,
            'title'          => $data['title'],
            'description'    => $data['description'] ?? null,
            'value'          => (float) ($data['value'] ?? 0),
            'currency'       => $data['currency'] ?? 'USD',
            'probability'    => (int) $stage['probability'],
            'stage_id'       => (int) $data['stage_id'],
            'contact_id'     => !empty($data['contact_id']) ? (int) $data['contact_id'] : null,
            'assigned_to'    => !empty($data['assigned_to']) ? (int) $data['assigned_to'] : null,
            'expected_close' => !empty($data['expected_close']) ? $data['expected_close'] : null,
            'source'         => $data['source'] ?? 'manual',
            'status'         => 'open',
        ]);

        Audit::log('lead.created', 'lead', $id);
        Events::dispatch('lead.created', [
            'tenant_id'  => $tenantId,
            'lead_id'    => $id,
            'entity_type'=> 'lead',
            'entity_id'  => $id,
            'stage_slug' => $stage['slug'],
        ]);

        Session::flash('success', 'Lead creado.');
        $this->redirect("/leads/$id");
    }

    public function show(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $lead = Lead::findById($tenantId, $id);
        if (!$lead) $this->abort(404);

        // Conversaciones del contacto
        $conversations = !empty($lead['contact_id']) ? Database::fetchAll(
            "SELECT * FROM conversations WHERE tenant_id = :t AND contact_id = :c ORDER BY last_message_at DESC LIMIT 5",
            ['t' => $tenantId, 'c' => $lead['contact_id']]
        ) : [];

        $this->view('leads.show', [
            'page'           => 'leads',
            'lead'           => $lead,
            'stages'         => PipelineStage::listForTenant($tenantId),
            'agents'         => User::listByTenant($tenantId),
            'conversations'  => $conversations,
        ], 'layouts.app');
    }

    public function update(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $lead = Lead::findById($tenantId, $id);
        if (!$lead) $this->abort(404);

        $data = $request->only(['title','description','value','currency','expected_close','assigned_to','source','probability','lost_reason']);
        if (!empty($data['value'])) $data['value'] = (float) $data['value'];
        if (!empty($data['probability'])) $data['probability'] = (int) $data['probability'];
        $data['assigned_to'] = !empty($data['assigned_to']) ? (int) $data['assigned_to'] : null;

        Lead::update($tenantId, $id, $data);
        Audit::log('lead.updated', 'lead', $id, $lead, $data);

        Session::flash('success', 'Lead actualizado.');
        $this->redirect("/leads/$id");
    }

    /** Endpoint AJAX para mover lead entre etapas (drag & drop) */
    public function move(Request $request): void
    {
        $tenantId = Tenant::id();
        $token = (string) $request->header('x-csrf-token', $request->input('_csrf', ''));
        if (!Csrf::validate($token)) {
            $this->json(['success' => false, 'error' => 'CSRF invalido'], 419);
            return;
        }

        $leadId  = (int) $request->input('lead_id');
        $stageId = (int) $request->input('stage_id');
        if (!$leadId || !$stageId) {
            $this->json(['success' => false, 'error' => 'Faltan parametros']);
            return;
        }

        $lead = Lead::findById($tenantId, $leadId);
        if (!$lead) { $this->json(['success' => false, 'error' => 'Lead no existe']); return; }

        $result = Lead::changeStage($tenantId, $leadId, $stageId);
        if (!$result['success']) { $this->json($result); return; }

        Audit::log('lead.stage_changed', 'lead', $leadId,
            ['stage_id' => $lead['stage_id']],
            ['stage_id' => $stageId]
        );

        Events::dispatch('lead.stage_changed', [
            'tenant_id'  => $tenantId,
            'lead_id'    => $leadId,
            'entity_type'=> 'lead',
            'entity_id'  => $leadId,
            'stage_slug' => $result['stage']['slug'] ?? '',
            'old_stage'  => (int) $lead['stage_id'],
            'new_stage'  => $stageId,
        ]);

        $this->json(['success' => true, 'stage' => $result['stage']]);
    }

    public function aiRecommend(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $lead = Lead::findById($tenantId, $id);
        if (!$lead) { $this->json(['success' => false], 404); return; }

        $context = "Titulo: {$lead['title']}\nValor: {$lead['value']} {$lead['currency']}\n"
                 . "Etapa: " . ($lead['stage_name'] ?? '') . "\n"
                 . "Probabilidad: {$lead['probability']}%\n"
                 . "Cliente: " . trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? '')) . " ({$lead['company']})\n"
                 . "Fuente: {$lead['source']}";

        $svc = new ClaudeService($tenantId);
        $rec  = $svc->recommendNextAction($context);
        $score = $svc->scoreLead($context, '');

        $aiScore = 0;
        if (!empty($score['text']) && preg_match('/"score"\s*:\s*(\d+)/', (string) $score['text'], $m)) {
            $aiScore = (int) $m[1];
        }

        Lead::update($tenantId, $id, [
            'ai_recommendation' => trim((string) $rec['text']),
            'ai_score'          => $aiScore,
        ]);

        $this->json([
            'success'        => true,
            'recommendation' => trim((string) $rec['text']),
            'score'          => $aiScore,
        ]);
    }

    public function destroy(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        Lead::softDelete($tenantId, $id);
        Audit::log('lead.deleted', 'lead', $id);
        Session::flash('success', 'Lead eliminado.');
        $this->redirect('/leads');
    }

    private function seedDefaultStages(int $tenantId): void
    {
        $stages = [
            ['Nuevo lead', 'nuevo', '#06B6D4', 10, 0, 0, 1],
            ['Contactado', 'contactado', '#3B82F6', 25, 0, 0, 2],
            ['Interesado', 'interesado', '#7C3AED', 50, 0, 0, 3],
            ['Cotizacion', 'cotizacion', '#A855F7', 70, 0, 0, 4],
            ['Negociacion','negociacion','#F59E0B', 85, 0, 0, 5],
            ['Ganado',     'ganado',     '#22C55E', 100, 1, 0, 6],
            ['Perdido',    'perdido',    '#EF4444', 0, 0, 1, 7],
        ];
        foreach ($stages as [$name, $slug, $color, $prob, $w, $l, $order]) {
            Database::insert('pipeline_stages', [
                'tenant_id'   => $tenantId,
                'name'        => $name,
                'slug'        => $slug,
                'color'       => $color,
                'probability' => $prob,
                'is_won'      => $w,
                'is_lost'     => $l,
                'sort_order'  => $order,
            ]);
        }
    }
}
