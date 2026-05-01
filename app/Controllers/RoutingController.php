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
use App\Models\AiAgent;
use App\Models\ChannelRoutingRule;
use App\Models\User;
use App\Models\WhatsappChannel;

final class RoutingController extends Controller
{
    public function index(Request $request): void
    {
        $tenantId = Tenant::id();

        $this->view('settings.routing', [
            'page'      => 'configuracion',
            'tab'       => 'routing',
            'rules'     => ChannelRoutingRule::listForTenant($tenantId),
            'channels'  => WhatsappChannel::listForTenant($tenantId),
            'agents'    => User::listByTenant($tenantId),
            'roles'     => Database::fetchAll("SELECT * FROM roles WHERE slug NOT IN ('super_admin') ORDER BY id ASC"),
            'aiAgents'  => $this->safeAiAgents($tenantId),
            'matchTypes' => ChannelRoutingRule::MATCH_TYPES,
            'strategies' => ChannelRoutingRule::STRATEGIES,
        ], 'layouts.app');
    }

    public function store(Request $request): void
    {
        $tenantId = Tenant::id();
        $data = $this->validateInput($request);

        $id = ChannelRoutingRule::create(array_merge(['tenant_id' => $tenantId], $data));
        Audit::log('routing.created', 'channel_routing_rule', $id, [], ['name' => $data['name']]);
        Session::flash('success', 'Regla creada.');
        $this->redirect('/settings/routing');
    }

    public function update(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $existing = ChannelRoutingRule::findById($tenantId, $id);
        if (!$existing) $this->abort(404);

        $data = $this->validateInput($request);
        ChannelRoutingRule::update($tenantId, $id, $data);
        Audit::log('routing.updated', 'channel_routing_rule', $id);
        Session::flash('success', 'Regla actualizada.');
        $this->redirect('/settings/routing');
    }

    public function toggle(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $existing = ChannelRoutingRule::findById($tenantId, $id);
        if (!$existing) $this->abort(404);
        ChannelRoutingRule::update($tenantId, $id, ['is_active' => empty($existing['is_active']) ? 1 : 0]);
        Session::flash('success', empty($existing['is_active']) ? 'Regla activada.' : 'Regla pausada.');
        $this->redirect('/settings/routing');
    }

    public function destroy(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        ChannelRoutingRule::delete($tenantId, $id);
        Audit::log('routing.deleted', 'channel_routing_rule', $id);
        Session::flash('success', 'Regla eliminada.');
        $this->redirect('/settings/routing');
    }

    private function validateInput(Request $request): array
    {
        $name      = trim((string) $request->input('name', ''));
        $matchType = (string) $request->input('match_type', 'any');
        $strategy  = (string) $request->input('assign_strategy', 'round_robin');

        if (!array_key_exists($matchType, ChannelRoutingRule::MATCH_TYPES)) $matchType = 'any';
        if (!array_key_exists($strategy, ChannelRoutingRule::STRATEGIES)) $strategy = 'round_robin';
        if ($name === '') $name = 'Regla sin nombre';

        return [
            'name'              => mb_substr($name, 0, 120),
            'priority'          => max(1, min(999, (int) ($request->input('priority') ?: 100))),
            'is_active'         => !empty($request->input('is_active')) ? 1 : 0,
            'channel_id'        => $request->input('channel_id') ? (int) $request->input('channel_id') : null,
            'match_type'        => $matchType,
            'match_value'       => trim((string) $request->input('match_value', '')) ?: null,
            'assign_strategy'   => $strategy,
            'assign_user_id'    => $request->input('assign_user_id') ? (int) $request->input('assign_user_id') : null,
            'assign_role'       => trim((string) $request->input('assign_role', '')) ?: null,
            'assign_ai_agent_id' => $request->input('assign_ai_agent_id') ? (int) $request->input('assign_ai_agent_id') : null,
            'auto_reply_enabled' => !empty($request->input('auto_reply_enabled')) ? 1 : 0,
            'auto_tag'          => trim((string) $request->input('auto_tag', '')) ?: null,
            'auto_priority'     => in_array((string) $request->input('auto_priority'), ['low','normal','high','urgent'], true)
                                    ? (string) $request->input('auto_priority') : null,
        ];
    }

    private function safeAiAgents(int $tenantId): array
    {
        try {
            return AiAgent::listForTenant($tenantId);
        } catch (\Throwable) {
            return [];
        }
    }
}
