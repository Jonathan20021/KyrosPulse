<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Tenant;
use App\Models\AgentSkill;
use App\Services\AgentSkillService;

/**
 * Admin UI para componer skills en un agente IA.
 *
 *   GET    /settings/ai/agents/{id}/skills
 *   POST   /settings/ai/agents/{id}/skills/attach   (slug + priority)
 *   POST   /settings/ai/agents/{id}/skills/{skill_id}/detach
 *   POST   /settings/ai/agents/{id}/skills/{skill_id}/toggle
 *   POST   /settings/ai/agents/{id}/skills/{skill_id}/priority
 *   POST   /settings/ai/skills          (crear skill custom del tenant)
 *   POST   /settings/ai/skills/{id}/delete
 */
final class AgentSkillController extends Controller
{
    public function index(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $agentId  = (int) ($params['id'] ?? 0);

        $agent = Database::fetch(
            "SELECT * FROM `ai_agents` WHERE `id` = :i AND `tenant_id` = :t LIMIT 1",
            ['i' => $agentId, 't' => $tenantId]
        );
        if (!$agent) {
            Session::flash('error', 'Agente no encontrado.');
            $this->redirect('/settings/ai');
            return;
        }

        $available = AgentSkill::listAvailable($tenantId);
        $linked    = AgentSkill::listForAgent($tenantId, $agentId, false);

        // Excluir las ya enlazadas del listado de disponibles
        $linkedIds = array_map(fn($s) => (int) $s['id'], $linked);
        $available = array_values(array_filter($available, fn($s) => !in_array((int) $s['id'], $linkedIds, true)));

        $this->view('settings.agent_skills', [
            'page'      => 'configuracion',
            'tab'       => 'ai',
            'agent'     => $agent,
            'linked'    => $linked,
            'available' => $available,
        ], 'layouts.app');
    }

    public function attach(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $agentId  = (int) ($params['id'] ?? 0);
        $slug     = trim((string) $request->input('slug', ''));
        $priority = (int) $request->input('priority', 100);

        if ($slug === '') {
            Session::flash('error', 'Slug de skill vacio.');
            $this->redirect('/settings/ai/agents/' . $agentId . '/skills');
            return;
        }

        $skill = AgentSkill::findBySlug($tenantId, $slug);
        if (!$skill) {
            Session::flash('error', 'Skill no disponible.');
            $this->redirect('/settings/ai/agents/' . $agentId . '/skills');
            return;
        }

        AgentSkill::attach($tenantId, $agentId, (int) $skill['id'], $priority);
        Audit::log('agent_skill.attach', 'agent', $agentId, [], [
            'skill_id' => (int) $skill['id'],
            'slug'     => $slug,
            'priority' => $priority,
        ]);
        Session::flash('success', 'Skill "' . $skill['name'] . '" anadida al agente.');
        $this->redirect('/settings/ai/agents/' . $agentId . '/skills');
    }

    public function detach(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $agentId  = (int) ($params['id'] ?? 0);
        $skillId  = (int) ($params['skill_id'] ?? 0);
        $count = AgentSkill::detach($tenantId, $agentId, $skillId);
        if ($count > 0) {
            Audit::log('agent_skill.detach', 'agent', $agentId, [], ['skill_id' => $skillId]);
            Session::flash('success', 'Skill removida.');
        } else {
            Session::flash('error', 'No se pudo remover.');
        }
        $this->redirect('/settings/ai/agents/' . $agentId . '/skills');
    }

    public function toggle(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $agentId  = (int) ($params['id'] ?? 0);
        $skillId  = (int) ($params['skill_id'] ?? 0);

        $row = Database::fetch(
            "SELECT `is_active` FROM `agent_skill_links`
             WHERE `tenant_id` = :t AND `agent_id` = :a AND `skill_id` = :s LIMIT 1",
            ['t' => $tenantId, 'a' => $agentId, 's' => $skillId]
        );
        if (!$row) {
            Session::flash('error', 'Skill no enlazada.');
            $this->redirect('/settings/ai/agents/' . $agentId . '/skills');
            return;
        }
        AgentSkill::setActive($tenantId, $agentId, $skillId, empty($row['is_active']));
        Session::flash('success', empty($row['is_active']) ? 'Skill activada.' : 'Skill pausada.');
        $this->redirect('/settings/ai/agents/' . $agentId . '/skills');
    }

    public function priority(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $agentId  = (int) ($params['id'] ?? 0);
        $skillId  = (int) ($params['skill_id'] ?? 0);
        $priority = (int) $request->input('priority', 100);
        AgentSkill::setPriority($tenantId, $agentId, $skillId, $priority);
        Session::flash('success', 'Prioridad actualizada.');
        $this->redirect('/settings/ai/agents/' . $agentId . '/skills');
    }

    public function storeCustom(Request $request): void
    {
        $tenantId = Tenant::id();
        $name = trim((string) $request->input('name', ''));
        $slug = trim((string) $request->input('slug', ''));
        $description  = trim((string) $request->input('description', ''));
        $systemPrompt = trim((string) $request->input('system_prompt', ''));
        $toolsRaw     = (string) $request->input('tools', '');

        if ($name === '' || $slug === '') {
            Session::flash('error', 'Nombre y slug son obligatorios.');
            $this->redirect('/settings/ai');
            return;
        }
        $slug = preg_replace('/[^a-z0-9_]/', '_', strtolower($slug)) ?? '';
        if ($slug === '') {
            Session::flash('error', 'Slug invalido.');
            $this->redirect('/settings/ai');
            return;
        }

        $tools = array_values(array_filter(array_map('trim', explode(',', $toolsRaw))));

        // Evitar colisionar con un slug global existente
        $existingGlobal = Database::fetch(
            "SELECT id FROM `agent_skills` WHERE `slug` = :s AND `tenant_id` IS NULL LIMIT 1",
            ['s' => $slug]
        );
        if ($existingGlobal) {
            Session::flash('error', 'Ese slug ya existe como skill global. Elige otro.');
            $this->redirect('/settings/ai');
            return;
        }

        $existingTenant = Database::fetch(
            "SELECT id FROM `agent_skills` WHERE `slug` = :s AND `tenant_id` = :t LIMIT 1",
            ['s' => $slug, 't' => $tenantId]
        );
        if ($existingTenant) {
            Session::flash('error', 'Ya tienes una skill con ese slug.');
            $this->redirect('/settings/ai');
            return;
        }

        $id = AgentSkill::createCustom($tenantId, [
            'slug'          => $slug,
            'name'          => mb_substr($name, 0, 120),
            'description'   => $description !== '' ? $description : null,
            'system_prompt' => $systemPrompt !== '' ? $systemPrompt : null,
            'tools'         => $tools ? json_encode($tools, JSON_UNESCAPED_UNICODE) : null,
        ]);
        Audit::log('agent_skill.create', 'skill', $id, [], ['slug' => $slug, 'name' => $name]);
        Session::flash('success', 'Skill custom "' . $name . '" creada.');
        $this->redirect('/settings/ai');
    }

    public function deleteCustom(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $count = AgentSkill::deleteCustom($tenantId, $id);
        if ($count > 0) {
            Audit::log('agent_skill.delete', 'skill', $id);
            Session::flash('success', 'Skill removida.');
        } else {
            Session::flash('error', 'No se pudo remover (las skills globales no se borran).');
        }
        $this->redirect('/settings/ai');
    }
}
