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
use App\Models\KnowledgeBase;
use App\Models\QuickReply;
use App\Models\Role;
use App\Models\Tenant as TenantModel;
use App\Models\User;
use App\Services\AiProviderService;
use App\Services\ResendService;
use App\Services\WasapiService;

final class SettingsController extends Controller
{
    public function general(Request $request): void
    {
        $tenantId = Tenant::id();
        $tenant   = TenantModel::findById($tenantId);
        $hours    = !empty($tenant['business_hours']) ? json_decode((string) $tenant['business_hours'], true) : [];

        $this->view('settings.general', [
            'page'   => 'configuracion',
            'tab'    => 'general',
            'tenant' => $tenant,
            'hours'  => is_array($hours) ? $hours : [],
        ], 'layouts.app');
    }

    public function updateGeneral(Request $request): void
    {
        $tenantId = Tenant::id();
        $data = $request->only([
            'name','legal_name','tax_id','email','phone','country','currency','timezone','language',
            'website','industry','address','welcome_message','out_of_hours_msg'
        ]);

        // Business hours
        $hours = [];
        foreach (['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day) {
            $h = $request->input('hours.' . $day, null);
            if (is_array($h) || is_array($request->input('hours'))) {
                $allHours = $request->input('hours', []);
                $h = $allHours[$day] ?? null;
            }
            if (is_array($h)) {
                $hours[$day] = [
                    'enabled' => !empty($h['enabled']),
                    'start'   => $h['start'] ?? '09:00',
                    'end'     => $h['end']   ?? '18:00',
                ];
            }
        }
        $data['business_hours'] = json_encode($hours, JSON_UNESCAPED_UNICODE);

        Database::update('tenants', $data, ['id' => $tenantId]);
        Audit::log('settings.general', 'tenant', $tenantId, [], $data);

        Session::flash('success', 'Configuracion actualizada.');
        $this->redirect('/settings');
    }

    public function integrations(Request $request): void
    {
        $tenantId = Tenant::id();
        $tenant = TenantModel::findById($tenantId);
        $this->view('settings.integrations_core', [
            'page'   => 'configuracion',
            'tab'    => 'integrations_core',
            'tenant' => $tenant,
            'webhookUrl' => url('/webhooks/wasapi/' . ($tenant['uuid'] ?? '')),
        ], 'layouts.app');
    }

    public function updateIntegrations(Request $request): void
    {
        $tenantId = Tenant::id();
        $data = $request->only([
            'wasapi_api_key','wasapi_phone','wasapi_webhook',
            'resend_api_key','resend_from_email',
            'claude_api_key','claude_model',
            'openai_api_key','openai_model',
            'ai_provider',
        ]);

        // Normalizar proveedor IA
        if (isset($data['ai_provider'])) {
            $data['ai_provider'] = in_array($data['ai_provider'], ['claude', 'openai'], true)
                ? $data['ai_provider']
                : 'claude';
        }

        // Sanitizar las API keys (quitar espacios accidentales)
        foreach (['wasapi_api_key','resend_api_key','claude_api_key','openai_api_key'] as $k) {
            if (isset($data[$k]) && is_string($data[$k])) {
                $data[$k] = trim($data[$k]);
            }
        }

        try {
            Database::update('tenants', $data, ['id' => $tenantId]);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('settings.integrations update failed', [
                'tenant' => $tenantId,
                'msg'    => $e->getMessage(),
            ]);
            Session::flash('error', 'No se pudieron guardar las integraciones: ' . $e->getMessage());
            $this->redirect('/settings/integrations/core');
            return;
        }

        Audit::log('settings.integrations', 'tenant', $tenantId);
        Session::flash('success', 'Integraciones actualizadas correctamente.');
        $this->redirect('/settings/integrations/core');
    }

    /**
     * Endpoint AJAX: prueba la conexion con el proveedor IA. Si recibe
     * api keys/model en el body, las usa para la prueba (asi el usuario
     * puede validar antes de guardar). Si no, usa la config guardada.
     */
    public function testAi(Request $request): void
    {
        $tenantId = Tenant::id();

        $provider = strtolower((string) $request->input('ai_provider', ''));
        if (!in_array($provider, ['claude', 'openai'], true)) {
            $provider = '';
        }

        $claudeKey   = trim((string) $request->input('claude_api_key', ''));
        $claudeModel = trim((string) $request->input('claude_model', ''));
        $openaiKey   = trim((string) $request->input('openai_api_key', ''));
        $openaiModel = trim((string) $request->input('openai_model', ''));

        try {
            // Override temporal SOLO en memoria, sin tocar la BD, para que el
            // ping use los valores del formulario tal cual los esta probando.
            $original = TenantModel::findById($tenantId);
            if (!$original) {
                $this->json(['success' => false, 'error' => 'Tenant no encontrado.']);
                return;
            }

            $override = [];
            if ($provider !== '')       $override['ai_provider']    = $provider;
            if ($claudeKey !== '')      $override['claude_api_key'] = $claudeKey;
            if ($claudeModel !== '')    $override['claude_model']   = $claudeModel;
            if ($openaiKey !== '')      $override['openai_api_key'] = $openaiKey;
            if ($openaiModel !== '')    $override['openai_model']   = $openaiModel;

            if (!empty($override)) {
                Database::update('tenants', $override, ['id' => $tenantId]);
            }

            try {
                $svc = new AiProviderService($tenantId);
                $resolved = $svc->provider();
                $resp = $svc->ping();
            } finally {
                // Restaurar valores originales si hicimos override.
                if (!empty($override)) {
                    $restore = [];
                    foreach ($override as $k => $_) {
                        $restore[$k] = $original[$k] ?? null;
                    }
                    Database::update('tenants', $restore, ['id' => $tenantId]);
                }
            }

            if (!empty($resp['success'])) {
                $this->json([
                    'success'  => true,
                    'provider' => $resolved,
                    'message'  => 'Conexion ' . strtoupper($resolved) . ' OK. Respuesta: ' . mb_substr((string) ($resp['text'] ?? ''), 0, 80),
                ]);
                return;
            }

            $this->json([
                'success'  => false,
                'provider' => $resolved,
                'error'    => $resp['error'] ?? 'El proveedor IA no respondio. Verifica la API key y el modelo.',
            ]);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('test-ai failed', ['msg' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function syncWasapiTemplates(Request $request): void
    {
        $tenantId = Tenant::id();
        $result = (new WasapiService($tenantId))->syncTemplates();
        if (!empty($result['success'])) {
            Session::flash('success', 'Plantillas sincronizadas: ' . (int) ($result['synced'] ?? 0));
        } else {
            Session::flash('error', 'No se pudieron sincronizar plantillas: ' . ($result['error'] ?? 'Error Wasapi'));
        }
        $this->redirect('/settings/integrations/core');
    }

    /**
     * Recorre los contactos WhatsApp con nombre generico ("Contacto WhatsApp" o vacio)
     * y consulta a Wasapi para obtener el nombre real del perfil. Limita el batch
     * para no agotar timeouts ni rate limit.
     */
    public function syncWasapiContactNames(Request $request): void
    {
        $tenantId = Tenant::id();
        $svc = new WasapiService($tenantId);

        $contacts = Database::fetchAll(
            "SELECT id, phone, whatsapp, first_name
             FROM contacts
             WHERE tenant_id = :t AND deleted_at IS NULL
               AND source = 'whatsapp'
               AND (first_name IS NULL OR first_name = '' OR first_name = 'Contacto WhatsApp' OR first_name = 'Contacto')
             ORDER BY id DESC
             LIMIT 100",
            ['t' => $tenantId]
        );

        $updated = 0;
        $checked = 0;
        foreach ($contacts as $c) {
            $checked++;
            $phone = (string) ($c['whatsapp'] ?: $c['phone']);
            if ($phone === '') continue;
            $profile = $svc->getContactProfile($phone);
            if (!$profile || ($profile['first_name'] === '' && $profile['last_name'] === '')) continue;
            Database::update('contacts', [
                'first_name' => $profile['first_name'] ?: 'Contacto WhatsApp',
                'last_name'  => $profile['last_name'] ?: null,
            ], ['id' => (int) $c['id'], 'tenant_id' => $tenantId]);
            $updated++;
        }

        Session::flash('success', "Sincronizacion completa: $updated nombres actualizados de $checked revisados.");
        $this->redirect('/settings/integrations/core');
    }

    public function ai(Request $request): void
    {
        $tenantId = Tenant::id();
        $tenant = TenantModel::findById($tenantId);
        try {
            $agents = AiAgent::listForTenant($tenantId);
        } catch (\Throwable) {
            $agents = [];
        }

        // KPIs de la IA (ultimos 30 dias)
        $kpi = [
            'handled'       => 0,
            'sent_messages' => 0,
            'transferred'   => 0,
            'sales_closed'  => 0,
            'tokens_in'     => 0,
            'tokens_out'    => 0,
        ];
        try {
            $kpi['handled'] = (int) Database::fetchColumn(
                "SELECT COUNT(DISTINCT conversation_id) FROM ai_agent_logs
                 WHERE tenant_id = :t AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                ['t' => $tenantId]
            );
            $kpi['sent_messages'] = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM messages
                 WHERE tenant_id = :t AND is_ai_generated = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                ['t' => $tenantId]
            );
            $kpi['transferred'] = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM conversation_assignments
                 WHERE tenant_id = :t AND action = 'unassigned' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                ['t' => $tenantId]
            );
            $kpi['sales_closed'] = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM ai_agent_logs
                 WHERE tenant_id = :t AND action_payload LIKE '%CLOSE_SALE%' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                ['t' => $tenantId]
            );
            $usage = Database::fetch(
                "SELECT COALESCE(SUM(tokens_input),0) AS ti, COALESCE(SUM(tokens_output),0) AS toX
                 FROM ai_logs WHERE tenant_id = :t AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                ['t' => $tenantId]
            );
            $kpi['tokens_in']  = (int) ($usage['ti']  ?? 0);
            $kpi['tokens_out'] = (int) ($usage['toX'] ?? 0);
        } catch (\Throwable) {}

        try {
            $aiSummary = (new AiProviderService($tenantId))->tokenSummary();
        } catch (\Throwable) {
            $aiSummary = null;
        }

        $this->view('settings.ai', [
            'page'      => 'configuracion',
            'tab'       => 'ai',
            'tenant'    => $tenant,
            'knowledge' => KnowledgeBase::listForTenant($tenantId),
            'agents'    => $agents,
            'kpi'       => $kpi,
            'aiSummary' => $aiSummary,
        ], 'layouts.app');
    }

    public function updateAi(Request $request): void
    {
        $tenantId = Tenant::id();
        $data = $request->only(['ai_assistant_name','ai_tone','ai_enabled','ai_force_all','out_of_hours_msg','welcome_message']);
        $data['ai_enabled']   = !empty($data['ai_enabled']) ? 1 : 0;
        $data['ai_force_all'] = !empty($data['ai_force_all']) ? 1 : 0;
        // Si se activa "Autopilot Total", asegurar que el master switch tambien quede ON.
        if ($data['ai_force_all']) $data['ai_enabled'] = 1;
        Database::update('tenants', $data, ['id' => $tenantId]);
        Audit::log('settings.ai', 'tenant', $tenantId);
        Session::flash('success', 'Configuracion de IA actualizada.');
        $this->redirect('/settings/ai');
    }

    /**
     * Endpoint dedicado para el boton grande "Autopilot Total" — toggle rapido
     * que activa/desactiva la IA en TODAS las conversaciones (sin pasar por el
     * formulario completo). Tambien limpia ai_paused_until de conversaciones
     * activas para reanudar inmediatamente la respuesta automatica.
     */
    public function aiAutopilotToggle(Request $request): void
    {
        $tenantId = Tenant::id();
        $enable = !empty($request->input('enable')) ? 1 : 0;

        Database::update('tenants', [
            'ai_force_all' => $enable,
            'ai_enabled'   => $enable ? 1 : (int) Database::fetchColumn(
                "SELECT ai_enabled FROM tenants WHERE id = :id",
                ['id' => $tenantId]
            ),
        ], ['id' => $tenantId]);

        if ($enable) {
            // Reanudar respuesta automatica en conversaciones activas
            Database::run(
                "UPDATE conversations
                 SET ai_paused_until = NULL,
                     ai_failed_attempts = 0
                 WHERE tenant_id = :t AND status IN ('open','pending')",
                ['t' => $tenantId]
            );
        }

        Audit::log('settings.ai_autopilot', 'tenant', $tenantId, [], ['enabled' => $enable]);

        if ($request->expectsJson()) {
            $this->json(['success' => true, 'enabled' => (bool) $enable]);
            return;
        }
        Session::flash('success', $enable ? 'Autopilot Total activado: la IA responde TODOS los mensajes.' : 'Autopilot Total desactivado.');
        $this->redirect('/settings/ai');
    }

    public function aiAgentStore(Request $request): void
    {
        $tenantId = Tenant::id();
        $data = $this->validate($request, [
            'name' => 'required|min:2|max:120',
        ]);

        $autoReply = !empty($request->input('auto_reply_enabled')) ? 1 : 0;

        AiAgent::create(array_merge(
            ['tenant_id' => $tenantId, 'name' => $data['name']],
            $this->aiAgentFieldsFromRequest($request),
            [
                'auto_reply_enabled' => $autoReply,
                'is_default'         => !empty($request->input('is_default')) ? 1 : 0,
                'handoff_keywords'   => json_encode(['humano','agente','asesor','soporte'], JSON_UNESCAPED_UNICODE),
                'allowed_actions'    => json_encode(['send_whatsapp','create_ticket','assign_agent','add_tag','close_sale','schedule'], JSON_UNESCAPED_UNICODE),
                'status'             => 'active',
            ]
        ));
        if ($autoReply) {
            Database::update('tenants', ['ai_enabled' => 1], ['id' => $tenantId]);
        }

        Session::flash('success', 'Agente IA creado.');
        $this->redirect('/settings/ai');
    }

    public function aiAgentUpdate(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $existing = Database::fetch(
            "SELECT * FROM ai_agents WHERE id = :id AND tenant_id = :t",
            ['id' => $id, 't' => $tenantId]
        );
        if (!$existing) $this->abort(404);

        $update = array_merge(
            ['name' => trim((string) $request->input('name', $existing['name']))],
            $this->aiAgentFieldsFromRequest($request, $existing),
            [
                'auto_reply_enabled' => !empty($request->input('auto_reply_enabled')) ? 1 : 0,
                'is_default'         => !empty($request->input('is_default')) ? 1 : 0,
            ]
        );
        AiAgent::update($tenantId, $id, $update);

        Session::flash('success', 'Agente IA actualizado.');
        $this->redirect('/settings/ai');
    }

    public function aiAgentDuplicate(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $src = Database::fetch(
            "SELECT * FROM ai_agents WHERE id = :id AND tenant_id = :t",
            ['id' => $id, 't' => $tenantId]
        );
        if (!$src) $this->abort(404);

        unset($src['id'], $src['uuid'], $src['created_at'], $src['updated_at']);
        $src['name']       = $src['name'] . ' (copia)';
        $src['is_default'] = 0;
        AiAgent::create($src);

        Session::flash('success', 'Agente IA duplicado.');
        $this->redirect('/settings/ai');
    }

    /**
     * Normaliza los campos editables del agente IA desde el Request.
     * @param array|null $existing Para preservar valores no enviados al editar.
     */
    private function aiAgentFieldsFromRequest(Request $request, ?array $existing = null): array
    {
        $triggers = $this->parseKeywordList((string) $request->input('trigger_keywords', ''));
        $transfer = $this->parseKeywordList((string) $request->input('transfer_keywords', ''));
        $channels = (array) $request->input('channels', []);
        $channels = array_values(array_filter(array_map('strtolower', $channels), fn($c) => in_array($c, ['whatsapp','email','webchat','instagram','facebook','telegram','sms'], true)));

        $hours = $this->parseWorkingHours($request);

        $category = (string) $request->input('category', 'generic');
        $valid = ['generic','sales','support','scheduling','collections','onboarding','retention'];
        if (!in_array($category, $valid, true)) $category = 'generic';

        return [
            'role'              => trim((string) $request->input('role', $existing['role'] ?? '')),
            'objective'         => trim((string) $request->input('objective', $existing['objective'] ?? '')),
            'instructions'      => trim((string) $request->input('instructions', $existing['instructions'] ?? '')),
            'tone'              => trim((string) $request->input('tone', $existing['tone'] ?? 'profesional, claro y orientado a vender')),
            'model'             => trim((string) $request->input('model', $existing['model'] ?? '')),
            'category'          => $category,
            'priority'          => (int) ($request->input('priority') ?: ($existing['priority'] ?? 100)),
            'max_retries'       => max(1, (int) ($request->input('max_retries') ?: ($existing['max_retries'] ?? 3))),
            'avatar_emoji'      => mb_substr((string) $request->input('avatar_emoji', $existing['avatar_emoji'] ?? '🤖'), 0, 4),
            'trigger_keywords'  => json_encode($triggers, JSON_UNESCAPED_UNICODE),
            'transfer_keywords' => json_encode($transfer, JSON_UNESCAPED_UNICODE),
            'channels'          => json_encode($channels, JSON_UNESCAPED_UNICODE),
            'working_hours'     => json_encode($hours, JSON_UNESCAPED_UNICODE),
        ];
    }

    private function parseKeywordList(string $raw): array
    {
        if ($raw === '') return [];
        $parts = preg_split('/[,;\n]+/u', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '' && mb_strlen($p) <= 60) $out[] = $p;
        }
        return array_values(array_unique($out));
    }

    private function parseWorkingHours(Request $request): array
    {
        $days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
        $hours = [];
        $raw = (array) $request->input('agent_hours', []);
        foreach ($days as $d) {
            $cfg = $raw[$d] ?? null;
            if (!is_array($cfg)) continue;
            $hours[$d] = [
                'enabled' => !empty($cfg['enabled']),
                'start'   => (string) ($cfg['start'] ?? '09:00'),
                'end'     => (string) ($cfg['end']   ?? '18:00'),
            ];
        }
        return $hours;
    }

    public function aiAgentToggle(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $agent = \App\Core\Database::fetch(
            "SELECT * FROM ai_agents WHERE id = :id AND tenant_id = :t",
            ['id' => $id, 't' => $tenantId]
        );
        if (!$agent) $this->abort(404);

        AiAgent::update($tenantId, $id, [
            'auto_reply_enabled' => empty($agent['auto_reply_enabled']) ? 1 : 0,
            'is_default' => 1,
        ]);
        if (empty($agent['auto_reply_enabled'])) {
            Database::update('tenants', ['ai_enabled' => 1], ['id' => $tenantId]);
        }

        Session::flash('success', empty($agent['auto_reply_enabled']) ? 'Agente IA activado.' : 'Agente IA pausado.');
        $this->redirect('/settings/ai');
    }

    public function aiAgentDelete(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        AiAgent::delete($tenantId, (int) ($params['id'] ?? 0));
        Session::flash('success', 'Agente IA eliminado.');
        $this->redirect('/settings/ai');
    }

    public function knowledgeStore(Request $request): void
    {
        $tenantId = Tenant::id();
        $data = $this->validate($request, [
            'category' => 'required|max:80',
            'title'    => 'required|min:3|max:255',
            'content'  => 'required|min:5',
        ]);
        KnowledgeBase::create([
            'tenant_id'  => $tenantId,
            'category'   => $data['category'],
            'title'      => $data['title'],
            'content'    => $data['content'],
            'is_active'  => 1,
            'created_by' => Auth::id(),
        ]);
        Session::flash('success', 'Articulo agregado a la base de conocimiento.');
        $this->redirect('/settings/ai');
    }

    public function knowledgeDelete(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        KnowledgeBase::delete($tenantId, $id);
        Session::flash('success', 'Articulo eliminado.');
        $this->redirect('/settings/ai');
    }

    public function users(Request $request): void
    {
        $tenantId = Tenant::id();
        $users = Database::fetchAll(
            "SELECT u.*, GROUP_CONCAT(r.name) AS roles
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             WHERE u.tenant_id = :t AND u.deleted_at IS NULL
             GROUP BY u.id ORDER BY u.created_at DESC",
            ['t' => $tenantId]
        );
        $this->view('settings.users', [
            'page'  => 'configuracion',
            'tab'   => 'users',
            'users' => $users,
            'roles' => Database::fetchAll("SELECT * FROM roles WHERE slug != 'super_admin' ORDER BY id ASC"),
        ], 'layouts.app');
    }

    public function inviteUser(Request $request): void
    {
        $tenantId = Tenant::id();
        $tenant   = TenantModel::findById($tenantId);
        $data = $this->validate($request, [
            'first_name' => 'required|min:2',
            'last_name'  => 'required|min:2',
            'email'      => 'required|email',
            'role_id'    => 'required|integer',
        ]);

        if (User::emailExists($data['email'])) {
            Session::flash('error', 'El email ya esta registrado en el sistema.');
            $this->redirect('/settings/users');
            return;
        }

        $tempPass = bin2hex(random_bytes(6));
        $userId = User::createUser([
            'tenant_id'  => $tenantId,
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'email'      => strtolower($data['email']),
            'password'   => $tempPass,
            'is_active'  => 1,
        ]);
        User::assignRole($userId, (int) $data['role_id'], $tenantId);

        // Email invitacion
        $resend = new ResendService($tenantId);
        $user = User::findById($userId);
        if ($user) {
            $resend->sendInvitation($user, url('/login') . '?invited=' . urlencode($data['email']), (string) ($tenant['name'] ?? ''));
        }

        Audit::log('user.invited', 'user', $userId, [], ['email' => $data['email']]);
        Session::flash('success', "Usuario invitado. Password temporal: $tempPass (compartelo seguramente).");
        $this->redirect('/settings/users');
    }

    public function quickReplies(Request $request): void
    {
        $tenantId = Tenant::id();
        $this->view('settings.quick_replies', [
            'page'    => 'configuracion',
            'tab'     => 'quick_replies',
            'replies' => QuickReply::listForTenant($tenantId),
        ], 'layouts.app');
    }

    public function quickReplyStore(Request $request): void
    {
        $tenantId = Tenant::id();
        $data = $this->validate($request, [
            'shortcut' => 'required|max:60',
            'body'     => 'required|min:2',
        ]);
        QuickReply::create([
            'tenant_id'  => $tenantId,
            'shortcut'   => $data['shortcut'],
            'title'      => $data['title'] ?? null,
            'body'       => $data['body'],
            'created_by' => Auth::id(),
        ]);
        Session::flash('success', 'Respuesta rapida creada.');
        $this->redirect('/settings/quick-replies');
    }

    public function quickReplyDelete(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        QuickReply::delete($tenantId, $id);
        Session::flash('success', 'Respuesta eliminada.');
        $this->redirect('/settings/quick-replies');
    }

    public function profile(Request $request): void
    {
        $this->view('settings.profile', [
            'page' => 'configuracion',
            'tab'  => 'profile',
            'user' => Auth::user(),
        ], 'layouts.app');
    }

    public function updateProfile(Request $request): void
    {
        $userId = (int) Auth::id();
        $data = $request->only(['first_name','last_name','phone','language','timezone','signature']);
        Database::update('users', $data, ['id' => $userId]);

        // Cambio de contrasena opcional
        if (!empty($request->input('new_password'))) {
            $pass = (string) $request->input('new_password');
            $confirm = (string) $request->input('new_password_confirmation');
            if (mb_strlen($pass) >= 8 && $pass === $confirm) {
                User::updatePassword($userId, $pass);
            } else {
                Session::flash('error', 'Las contrasenas no coinciden o son menores a 8 caracteres.');
                $this->redirect('/settings/profile');
                return;
            }
        }

        Session::flash('success', 'Perfil actualizado.');
        $this->redirect('/settings/profile');
    }
}
