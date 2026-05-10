<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\Events;
use App\Core\Request;
use App\Models\AgentRun;
use App\Models\AgentSkill;
use App\Models\AiAgent;
use App\Services\AgentSkillService;
use App\Services\AiProviderService;

/**
 * Endpoints de Agent-as-a-Service.
 *
 *   GET    /api/v1/agents             listar agentes del tenant
 *   GET    /api/v1/agents/{id}        detalle de un agente
 *   POST   /api/v1/agents/{id}/run    ejecutar agente con un input
 *   GET    /api/v1/agents/runs/{uuid} obtener una ejecucion previa
 *   GET    /api/v1/agents/runs        listar ejecuciones recientes
 */
final class ApiAgentController extends ApiController
{
    public function index(Request $request): void
    {
        $tenantId = $this->tenantId();
        $agents = AiAgent::listForTenant($tenantId);
        $out = array_map(fn($a) => $this->transform($a), $agents);
        $this->ok($out);
    }

    public function show(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        $row = Database::fetch(
            "SELECT * FROM `ai_agents` WHERE `id` = :i AND `tenant_id` = :t LIMIT 1",
            ['i' => $id, 't' => $tenantId]
        );
        if (!$row) {
            $this->error('not_found', 'Agent not found.', 404);
        }
        $this->ok($this->transform($row));
    }

    /**
     * Ejecuta un agente con un input de texto. Sincrono.
     *
     * POST /api/v1/agents/{id}/run
     *   { "input": "Quiero pedir 2 pizzas", "history": "...", "metadata": {...} }
     *
     * Respuesta:
     *   { "data": { "run_id": "...", "output": "...", "actions": [...],
     *               "tokens": {...}, "cost_usd": 0.0001, "latency_ms": 842 } }
     */
    public function run(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $agentId  = (int) ($params['id'] ?? 0);

        $clean = $this->validateApi($request, [
            'input' => 'required|string|min:1|max:8000',
        ]);

        $agent = Database::fetch(
            "SELECT * FROM `ai_agents` WHERE `id` = :i AND `tenant_id` = :t AND `status` = 'active' LIMIT 1",
            ['i' => $agentId, 't' => $tenantId]
        );
        if (!$agent) {
            $this->error('agent_not_found', 'Active agent not found.', 404);
        }

        $input    = (string) $clean['input'];
        $history  = (string) ($request->input('history', ''));
        $metadata = $request->input('metadata');
        if ($metadata !== null && !is_array($metadata)) {
            $this->error('validation_failed', '`metadata` must be an object.', 422);
        }

        // Crear run en estado running
        $runId = AgentRun::start([
            'tenant_id'  => $tenantId,
            'agent_id'   => $agentId,
            'api_key_id' => $this->apiKey()['id'] ?? null,
            'channel'    => 'api',
            'status'     => 'running',
            'input'      => $input,
            'metadata'   => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
        ]);
        $runRow = Database::fetch("SELECT uuid FROM `agent_runs` WHERE `id` = :i", ['i' => $runId]);
        $uuid = (string) ($runRow['uuid'] ?? '');

        $start = microtime(true);
        try {
            $provider = new AiProviderService($tenantId, $agentId);
            // user_message alimenta al hub de skills (router heuristico:
            // ventas/soporte/cobranza/agendamiento/...).
            $provider->withContext([
                'agent_run_id' => $runId,
                'request_id'   => $this->requestId(),
                'user_message' => $input,
            ]);

            $reply = $provider->autoReply($input, $history);
            $latency = (int) round((microtime(true) - $start) * 1000);

            if (empty($reply['success']) || trim((string) ($reply['text'] ?? '')) === '') {
                $errorMsg = (string) ($reply['error'] ?? 'AI returned empty response');
                AgentRun::fail($runId, $errorMsg);
                Events::dispatch('agent.run.failed', [
                    'tenant_id' => $tenantId,
                    'agent_id'  => $agentId,
                    'run_id'    => $uuid,
                    'error'     => $errorMsg,
                    'channel'   => 'api',
                ]);
                $this->error('agent_failed', $errorMsg, 502, [
                    'run_id'     => $uuid,
                    'latency_ms' => $latency,
                ]);
            }

            $rawText = (string) $reply['text'];
            $parsed  = $this->parseActions($rawText);

            AgentRun::complete($runId, [
                'status'      => 'succeeded',
                'output'      => $parsed['text'],
                'actions'     => json_encode($parsed['actions'], JSON_UNESCAPED_UNICODE),
                'tokens_in'   => (int) ($reply['tokens_in']  ?? 0),
                'tokens_out'  => (int) ($reply['tokens_out'] ?? 0),
                'cost_usd'    => (float) ($reply['cost_usd'] ?? 0),
                'latency_ms'  => $latency,
            ]);

            Events::dispatch('agent.run.completed', [
                'tenant_id'  => $tenantId,
                'agent_id'   => $agentId,
                'agent_name' => (string) $agent['name'],
                'run_id'     => $uuid,
                'output'     => $parsed['text'],
                'actions'    => $parsed['actions'],
                'tokens'     => [
                    'input'  => (int) ($reply['tokens_in']  ?? 0),
                    'output' => (int) ($reply['tokens_out'] ?? 0),
                ],
                'cost_usd'   => (float) ($reply['cost_usd'] ?? 0),
                'latency_ms' => $latency,
                'channel'    => 'api',
            ]);

            $this->ok([
                'run_id'     => $uuid,
                'output'     => $parsed['text'],
                'actions'    => $parsed['actions'],
                'tokens'     => [
                    'input'  => (int) ($reply['tokens_in']  ?? 0),
                    'output' => (int) ($reply['tokens_out'] ?? 0),
                ],
                'cost_usd'   => (float) ($reply['cost_usd'] ?? 0),
                'latency_ms' => $latency,
                'agent'      => [
                    'id'   => (int) $agent['id'],
                    'name' => (string) $agent['name'],
                ],
            ]);
        } catch (\Throwable $e) {
            AgentRun::fail($runId, $e->getMessage());
            $this->error('internal_error', 'Agent execution failed: ' . $e->getMessage(), 500, [
                'run_id' => $uuid,
            ]);
        }
    }

    public function showRun(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $uuid = (string) ($params['uuid'] ?? '');
        $row  = AgentRun::findByUuid($tenantId, $uuid);
        if (!$row) {
            $this->error('not_found', 'Run not found.', 404);
        }
        $this->ok($this->transformRun($row));
    }

    public function listRuns(Request $request): void
    {
        $tenantId = $this->tenantId();
        [$page, $perPage, $offset] = $this->pagination($request, 25, 100);
        $rows = AgentRun::listForTenant($tenantId, $perPage, $offset);
        $items = array_map(fn($r) => $this->transformRun($r), $rows);
        $this->paginated($items, $page, $perPage);
    }

    /**
     * GET /api/v1/skills — catalogo de skills disponibles para el tenant
     * (globales + custom). Permite descubrir que se puede componer.
     */
    public function listSkills(Request $request): void
    {
        $tenantId = $this->tenantId();
        $rows = AgentSkill::listAvailable($tenantId);
        $out = array_map(fn($s) => $this->transformSkill($s), $rows);
        $this->ok($out);
    }

    /**
     * GET /api/v1/agents/{id}/skills — skills enlazadas a este agente.
     */
    public function listAgentSkills(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $agentId  = (int) ($params['id'] ?? 0);
        $this->ensureAgentExists($tenantId, $agentId);
        $rows = AgentSkill::listForAgent($tenantId, $agentId, false);
        $out = array_map(fn($s) => $this->transformSkill($s, true), $rows);
        $this->ok($out);
    }

    /**
     * POST /api/v1/agents/{id}/skills — attach skill por slug.
     * Body: { "slug": "sales", "priority": 10, "config": {...} }
     */
    public function attachSkill(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $agentId  = (int) ($params['id'] ?? 0);
        $this->ensureAgentExists($tenantId, $agentId);

        $clean = $this->validateApi($request, [
            'slug' => 'required|string|min:1|max:64',
        ]);
        $slug     = (string) $clean['slug'];
        $priority = (int) $request->input('priority', 100);
        $cfg      = $request->input('config');
        if ($cfg !== null && !is_array($cfg)) {
            $this->error('validation_failed', '`config` must be an object.', 422);
        }

        $skill = AgentSkill::findBySlug($tenantId, $slug);
        if (!$skill) {
            $this->error('skill_not_found', "Skill '$slug' is not available for this tenant.", 404);
        }
        AgentSkill::attach($tenantId, $agentId, (int) $skill['id'], $priority, $cfg);
        $linked = AgentSkill::listForAgent($tenantId, $agentId, false);
        $found  = null;
        foreach ($linked as $row) {
            if ((int) $row['id'] === (int) $skill['id']) { $found = $row; break; }
        }
        $this->created($this->transformSkill($found ?? $skill, true));
    }

    /**
     * DELETE /api/v1/agents/{id}/skills/{slug} — detach skill.
     */
    public function detachSkill(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $agentId  = (int) ($params['id'] ?? 0);
        $slug     = (string) ($params['slug'] ?? '');
        $this->ensureAgentExists($tenantId, $agentId);

        $skill = AgentSkill::findBySlug($tenantId, $slug);
        if (!$skill) {
            $this->error('skill_not_found', "Skill '$slug' not found.", 404);
        }
        $count = AgentSkill::detach($tenantId, $agentId, (int) $skill['id']);
        if ($count === 0) {
            $this->error('not_attached', 'That skill is not attached to this agent.', 404);
        }
        $this->noContent();
    }

    private function ensureAgentExists(int $tenantId, int $agentId): void
    {
        $row = Database::fetch(
            "SELECT id FROM `ai_agents` WHERE `id` = :i AND `tenant_id` = :t LIMIT 1",
            ['i' => $agentId, 't' => $tenantId]
        );
        if (!$row) {
            $this->error('agent_not_found', 'Agent not found.', 404);
        }
    }

    private function transformSkill(array $s, bool $withLink = false): array
    {
        $tools  = $s['tools'] ?? null;
        if (is_string($tools))  $tools  = json_decode($tools, true)  ?: [];
        $config = $s['config'] ?? null;
        if (is_string($config)) $config = json_decode($config, true) ?: null;

        $out = [
            'id'           => (int) ($s['id'] ?? 0),
            'slug'         => (string) ($s['slug'] ?? ''),
            'name'         => (string) ($s['name'] ?? ''),
            'description'  => (string) ($s['description'] ?? ''),
            'tools'        => is_array($tools) ? $tools : [],
            'config'       => $config,
            'is_global'    => empty($s['tenant_id']),
            'is_active'    => !empty($s['is_active']),
        ];
        if ($withLink && isset($s['link_id'])) {
            $linkCfg = $s['link_config'] ?? null;
            if (is_string($linkCfg)) $linkCfg = json_decode($linkCfg, true) ?: null;
            $out['link'] = [
                'priority'  => (int) ($s['link_priority'] ?? 100),
                'is_active' => !empty($s['link_active']),
                'config'    => $linkCfg,
            ];
        }
        return $out;
    }

    private function transform(array $a): array
    {
        return [
            'id'                    => (int) $a['id'],
            'uuid'                  => (string) ($a['uuid'] ?? ''),
            'name'                  => (string) ($a['name'] ?? ''),
            'role'                  => (string) ($a['role'] ?? ''),
            'tone'                  => (string) ($a['tone'] ?? ''),
            'status'                => (string) ($a['status'] ?? 'inactive'),
            'is_default'            => !empty($a['is_default']),
            'model'                 => (string) ($a['model'] ?? ''),
            'temperature'           => (float)  ($a['temperature'] ?? 0.7),
            'max_context_messages'  => (int)    ($a['max_context_messages'] ?? 30),
            'created_at'            => (string) ($a['created_at'] ?? ''),
            'updated_at'            => (string) ($a['updated_at'] ?? ''),
        ];
    }

    private function transformRun(array $r): array
    {
        $actions = $r['actions'] ?? null;
        if (is_string($actions)) {
            $actions = json_decode($actions, true) ?: [];
        }
        return [
            'run_id'      => (string) $r['uuid'],
            'agent_id'    => (int) $r['agent_id'],
            'status'      => (string) $r['status'],
            'channel'     => (string) ($r['channel'] ?? 'api'),
            'input'       => (string) ($r['input'] ?? ''),
            'output'      => (string) ($r['output'] ?? ''),
            'actions'     => $actions ?: [],
            'tokens'      => [
                'input'  => (int) ($r['tokens_in']  ?? 0),
                'output' => (int) ($r['tokens_out'] ?? 0),
            ],
            'cost_usd'    => (float) ($r['cost_usd'] ?? 0),
            'latency_ms'  => (int)   ($r['latency_ms'] ?? 0),
            'error'       => $r['error'] ?? null,
            'created_at'  => (string) ($r['created_at'] ?? ''),
            'completed_at'=> $r['completed_at'] ?? null,
        ];
    }

    /**
     * Parser ligero de marcadores [ACTION:payload] que la IA puede emitir.
     * Compatible con el formato del AiAgentService existente.
     */
    private function parseActions(string $text): array
    {
        $actions = [];
        $clean = preg_replace_callback(
            '/\[(TRANSFER|CLOSE_SALE|SCHEDULE|ORDER|CART_ADD|CART_RM|TICKET|TAG|NOTE)(?::([^\]]+))?\]/i',
            function ($m) use (&$actions) {
                $actions[] = [
                    'type'    => strtolower($m[1]),
                    'payload' => isset($m[2]) ? trim($m[2]) : null,
                ];
                return '';
            },
            $text
        );
        return [
            'text'    => trim((string) $clean),
            'actions' => $actions,
        ];
    }
}
