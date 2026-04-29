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
use App\Models\Conversation;
use App\Models\Message;
use App\Models\AiAgent;
use App\Models\QuickReply;
use App\Models\User;
use App\Services\AiAgentService;
use App\Services\AiProviderService;
use App\Services\ClaudeService;
use App\Services\WasapiService;

final class InboxController extends Controller
{
    public function index(Request $request, array $params = []): void
    {
        $tenantId = Tenant::id();

        $filters = [
            'status'  => (string) $request->query('status', ''),
            'agent'   => $request->query('agent') ?: null,
            'channel' => (string) $request->query('channel', ''),
            'q'       => trim((string) $request->query('q', '')),
        ];

        $conversations = Conversation::listFiltered($tenantId, $filters, 80);

        $activeId = isset($params['id']) ? (int) $params['id'] : null;
        if (!$activeId && !empty($conversations)) {
            $activeId = (int) $conversations[0]['id'];
        }

        $active = $activeId ? Conversation::findById($tenantId, $activeId) : null;
        $messages = $active ? Message::listByConversation($tenantId, (int) $active['id']) : [];

        if ($active) {
            Conversation::markRead($tenantId, (int) $active['id']);
        }

        try {
            $aiAgents = AiAgent::listForTenant($tenantId);
        } catch (\Throwable) {
            $aiAgents = [];
        }

        $this->view('inbox.index', [
            'page'           => 'bandeja',
            'conversations'  => $conversations,
            'active'         => $active,
            'messages'       => $messages,
            'filters'        => $filters,
            'agents'         => User::listByTenant($tenantId),
            'aiAgents'       => $aiAgents,
            'quickReplies'   => QuickReply::listForTenant($tenantId),
        ], 'layouts.app');
    }

    public function send(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $convId = (int) ($params['id'] ?? 0);
        $conv = Conversation::findById($tenantId, $convId);
        if (!$conv) $this->abort(404);

        $token = (string) $request->header('x-csrf-token', $request->input('_csrf', ''));
        if (!Csrf::validate($token)) {
            $this->json(['success' => false, 'error' => 'CSRF invalido'], 419);
            return;
        }

        $message = trim((string) $request->input('message', ''));
        $isInternal = !empty($request->input('is_internal'));

        if ($message === '') {
            $this->json(['success' => false, 'error' => 'Mensaje vacio']);
            return;
        }

        $messageId = Database::insert('messages', [
            'tenant_id'       => $tenantId,
            'conversation_id' => $convId,
            'contact_id'      => (int) $conv['contact_id'],
            'user_id'         => Auth::id(),
            'direction'       => 'outbound',
            'type'            => 'text',
            'content'         => $message,
            'is_internal'     => $isInternal ? 1 : 0,
            'status'          => $isInternal ? 'sent' : 'queued',
            'sent_at'         => $isInternal ? date('Y-m-d H:i:s') : null,
        ]);

        // Si no es nota interna, intentar enviar por Wasapi
        $whatsappResult = ['success' => true];
        if (!$isInternal && $conv['channel'] === 'whatsapp' && !empty($conv['phone'] ?? $conv['whatsapp'])) {
            $phone = (string) ($conv['whatsapp'] ?: $conv['phone']);
            $svc = new WasapiService($tenantId);
            $whatsappResult = $svc->sendTextMessage($phone, $message);
            Database::update('messages', [
                'status'        => $whatsappResult['success'] ? 'sent' : 'failed',
                'external_id'   => $whatsappResult['body']['id'] ?? null,
                'sent_at'       => $whatsappResult['success'] ? date('Y-m-d H:i:s') : null,
                'error_message' => $whatsappResult['success'] ? null : ($whatsappResult['error'] ?: 'Error envio'),
            ], ['id' => $messageId]);
        }

        // Actualizar conversacion
        Conversation::update($tenantId, $convId, [
            'last_message'    => mb_substr($message, 0, 200),
            'last_message_at' => date('Y-m-d H:i:s'),
            'status'          => 'open',
        ]);

        $this->json([
            'success'   => true,
            'message_id'=> $messageId,
            'sent'      => $whatsappResult['success'] ?? true,
            'whatsapp_error' => !($whatsappResult['success'] ?? true) ? ($whatsappResult['error'] ?? '') : null,
        ]);
    }

    public function messages(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $convId = (int) ($params['id'] ?? 0);
        $conv = Conversation::findById($tenantId, $convId);
        if (!$conv) {
            $this->json(['success' => false, 'error' => 'Conversacion no encontrada.'], 404);
            return;
        }

        $messages = Message::listByConversation($tenantId, $convId);
        Conversation::markRead($tenantId, $convId);

        $fingerprint = hash('sha256', json_encode(array_map(
            static fn ($m) => [$m['id'], $m['status'], $m['external_id'], $m['updated_at']],
            $messages
        ), JSON_UNESCAPED_UNICODE) ?: '');

        $this->json([
            'success'     => true,
            'fingerprint' => $fingerprint,
            'max_id'      => empty($messages) ? 0 : (int) max(array_column($messages, 'id')),
            'html'        => $this->renderMessagesHtml($messages),
        ]);
    }

    public function live(Request $request): void
    {
        $tenantId = Tenant::id();
        $filters = [
            'status'  => (string) $request->query('status', ''),
            'agent'   => $request->query('agent') ?: null,
            'channel' => (string) $request->query('channel', ''),
            'q'       => trim((string) $request->query('q', '')),
        ];

        $rows = Conversation::listFiltered($tenantId, $filters, 80);
        $this->json([
            'success' => true,
            'count'   => count($rows),
            'latest'  => $rows[0]['last_message_at'] ?? null,
            'rows'    => array_map(static fn ($c) => [
                'id' => (int) $c['id'],
                'name' => trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: ($c['phone'] ?? 'Sin nombre'),
                'last_message' => (string) ($c['last_message'] ?? ''),
                'unread_count' => (int) ($c['unread_count'] ?? 0),
                'last_message_at' => (string) ($c['last_message_at'] ?? $c['updated_at']),
            ], $rows),
        ]);
    }

    public function assign(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $convId = (int) ($params['id'] ?? 0);
        $userId = $request->input('user_id') ? (int) $request->input('user_id') : null;

        Conversation::update($tenantId, $convId, ['assigned_to' => $userId]);
        Database::insert('conversation_assignments', [
            'tenant_id'        => $tenantId,
            'conversation_id'  => $convId,
            'from_user_id'     => Auth::id(),
            'to_user_id'       => $userId,
            'action'           => $userId ? 'assigned' : 'unassigned',
            'note'             => (string) $request->input('note', ''),
        ]);

        Audit::log('conversation.assigned', 'conversation', $convId, [], ['to' => $userId]);

        Session::flash('success', $userId ? 'Conversacion asignada.' : 'Asignacion removida.');
        $this->redirect("/inbox/$convId");
    }

    public function close(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $convId = (int) ($params['id'] ?? 0);
        $reason = (string) $request->input('reason', '');

        Conversation::close($tenantId, $convId, $reason);
        Database::insert('conversation_assignments', [
            'tenant_id'        => $tenantId,
            'conversation_id'  => $convId,
            'from_user_id'     => Auth::id(),
            'action'           => 'closed',
            'note'             => $reason,
        ]);

        Session::flash('success', 'Conversacion cerrada.');
        $this->redirect('/inbox');
    }

    public function reopen(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $convId = (int) ($params['id'] ?? 0);
        Conversation::update($tenantId, $convId, [
            'status' => 'open',
            'closed_at' => null,
            'closed_reason' => null,
        ]);
        Session::flash('success', 'Conversacion reabierta.');
        $this->redirect("/inbox/$convId");
    }

    public function star(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $convId = (int) ($params['id'] ?? 0);
        $current = Database::fetchColumn(
            "SELECT is_starred FROM conversations WHERE id = :id AND tenant_id = :t",
            ['id' => $convId, 't' => $tenantId]
        );
        Conversation::update($tenantId, $convId, ['is_starred' => $current ? 0 : 1]);
        $this->json(['success' => true, 'starred' => !$current]);
    }

    public function aiSuggest(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $convId = (int) ($params['id'] ?? 0);
        $conv = Conversation::findById($tenantId, $convId);
        if (!$conv) { $this->json(['success' => false, 'error' => 'Conversacion no encontrada.']); return; }

        $messages = Message::listByConversation($tenantId, $convId, 20);
        if (empty($messages)) {
            $this->json(['success' => false, 'error' => 'Sin mensajes']);
            return;
        }

        $transcript = '';
        foreach ($messages as $m) {
            $who = $m['direction'] === 'inbound' ? 'Cliente' : 'Agente';
            $transcript .= "$who: " . trim((string) $m['content']) . "\n";
        }

        $agentId = !empty($conv['ai_agent_id']) ? (int) $conv['ai_agent_id'] : null;
        $svc = new AiProviderService($tenantId, $agentId);
        $action = (string) $request->input('action', 'suggest');

        $result = match ($action) {
            'summarize' => $svc->summarizeConversation($transcript),
            'sentiment' => $svc->evaluateSentiment($transcript),
            'next'      => $svc->recommendNextAction($transcript),
            'score'     => $svc->scoreLead("Contacto #{$conv['contact_id']} canal {$conv['channel']}", $transcript),
            default     => $svc->suggestReply($transcript),
        };

        if (!empty($result['success'])) {
            $text = trim((string) $result['text']);

            $update = [];
            if ($action === 'summarize') $update['ai_summary'] = $text;
            elseif ($action === 'sentiment') {
                $sent = strtolower($text);
                if (str_contains($sent, 'positive')) $update['ai_sentiment'] = 'positive';
                elseif (str_contains($sent, 'negative')) $update['ai_sentiment'] = 'negative';
                else $update['ai_sentiment'] = 'neutral';
            }
            elseif ($action === 'next') $update['ai_next_action'] = $text;
            elseif ($action === 'score') {
                $decoded = json_decode($text, true);
                if (is_array($decoded) && isset($decoded['score'])) {
                    $update['ai_score'] = max(0, min(100, (int) $decoded['score']));
                    if (!empty($decoded['next_action'])) $update['ai_next_action'] = (string) $decoded['next_action'];
                }
            }
            if ($update) Conversation::update($tenantId, $convId, $update);

            $this->json(['success' => true, 'text' => $text]);
            return;
        }

        $this->json([
            'success' => false,
            'error'   => $result['error'] ?? 'No se pudo conectar con la IA. Verifica tu API key en Integraciones.',
        ]);
    }

    /**
     * Asigna un agente IA a la conversacion. user_id = null para desasignar.
     * Solo afecta a la columna ai_agent_id (no enciende auto-pilot).
     */
    public function aiAssign(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $convId   = (int) ($params['id'] ?? 0);
        $token    = (string) $request->header('x-csrf-token', $request->input('_csrf', ''));
        if (!Csrf::validate($token)) {
            $this->json(['success' => false, 'error' => 'CSRF invalido'], 419);
            return;
        }

        $rawAgent = $request->input('agent_id');
        $agentId  = ($rawAgent === '' || $rawAgent === null) ? null : (int) $rawAgent;

        if ($agentId !== null) {
            $exists = Database::fetchColumn(
                "SELECT id FROM ai_agents WHERE id = :id AND tenant_id = :t",
                ['id' => $agentId, 't' => $tenantId]
            );
            if (!$exists) {
                $this->json(['success' => false, 'error' => 'Agente IA no encontrado.']);
                return;
            }
        }

        Conversation::update($tenantId, $convId, ['ai_agent_id' => $agentId]);
        Audit::log('conversation.ai_assigned', 'conversation', $convId, [], ['ai_agent_id' => $agentId]);
        $this->json(['success' => true, 'ai_agent_id' => $agentId]);
    }

    /**
     * Toggle del modo auto-pilot por conversacion. Cuando esta ON, la IA
     * responde automaticamente todos los mensajes entrantes incluso si el
     * tenant no tiene ai_enabled global. Cuando esta OFF, se respetan los
     * defaults del tenant + ai_agents.auto_reply_enabled.
     */
    public function aiTakeover(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $convId   = (int) ($params['id'] ?? 0);
        $token    = (string) $request->header('x-csrf-token', $request->input('_csrf', ''));
        if (!Csrf::validate($token)) {
            $this->json(['success' => false, 'error' => 'CSRF invalido'], 419);
            return;
        }

        $conv = Conversation::findById($tenantId, $convId);
        if (!$conv) { $this->json(['success' => false, 'error' => 'No encontrada.'], 404); return; }

        $enable = $request->input('enable');
        $enable = $enable === null ? !((int) $conv['ai_takeover'] === 1) : (bool) $enable;

        Conversation::update($tenantId, $convId, [
            'ai_takeover'     => $enable ? 1 : 0,
            'bot_enabled'     => $enable ? 1 : (int) $conv['bot_enabled'],
            'ai_paused_until' => null,
        ]);
        Audit::log('conversation.ai_takeover', 'conversation', $convId, [], ['enabled' => $enable]);
        $this->json(['success' => true, 'enabled' => $enable]);
    }

    /**
     * Pide a la IA que genere YA una respuesta y la envie por WhatsApp,
     * usando el agente IA asignado a la conversacion. Util cuando el
     * humano quiere "que la IA responda ahora" sin esperar mensaje entrante.
     */
    public function aiRunNow(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $convId   = (int) ($params['id'] ?? 0);
        $token    = (string) $request->header('x-csrf-token', $request->input('_csrf', ''));
        if (!Csrf::validate($token)) {
            $this->json(['success' => false, 'error' => 'CSRF invalido'], 419);
            return;
        }

        $conv = Conversation::findById($tenantId, $convId);
        if (!$conv) { $this->json(['success' => false, 'error' => 'No encontrada.'], 404); return; }

        // Forzamos takeover temporal para que el servicio considere al agente.
        $previousTakeover = (int) ($conv['ai_takeover'] ?? 0);
        Conversation::update($tenantId, $convId, ['ai_takeover' => 1]);

        // Tomamos el ultimo mensaje del cliente como input.
        $lastInbound = Database::fetch(
            "SELECT id, content FROM messages
             WHERE tenant_id = :t AND conversation_id = :c AND direction = 'inbound'
             ORDER BY id DESC LIMIT 1",
            ['t' => $tenantId, 'c' => $convId]
        );
        $userMsg = trim((string) ($lastInbound['content'] ?? '')) ?: 'Continua la conversacion con el cliente segun el historial.';

        $phone = (string) ($conv['whatsapp'] ?: $conv['phone']);
        $svc   = new AiAgentService($tenantId);
        $resp  = $svc->autoReplyToConversation($convId, (int) $conv['contact_id'], $phone, $userMsg, isset($lastInbound['id']) ? (int) $lastInbound['id'] : null);

        // Restauramos el estado original de takeover (si no estaba activo).
        if (!$previousTakeover) {
            Conversation::update($tenantId, $convId, ['ai_takeover' => 0]);
        }

        $this->json($resp);
    }

    private function renderMessagesHtml(array $messages): string
    {
        ob_start();
        $currentDate = '';
        foreach ($messages as $m):
            $msgDate = date('Y-m-d', strtotime((string) $m['created_at']));
            if ($msgDate !== $currentDate):
                $currentDate = $msgDate;
                $todayLabel = date('Y-m-d') === $msgDate ? 'Hoy' : (date('Y-m-d', strtotime('-1 day')) === $msgDate ? 'Ayer' : date('d M', strtotime($msgDate)));
        ?>
            <div class="flex justify-center my-4">
                <span class="px-3 py-1 rounded-full text-[10px] font-semibold uppercase tracking-[0.1em]" style="background: var(--color-bg-elevated); color: var(--color-text-tertiary); border: 1px solid var(--color-border-subtle);"><?= $todayLabel ?></span>
            </div>
        <?php endif;
            $isOut = $m['direction'] === 'outbound';
            $internal = !empty($m['is_internal']);
            $aiGen = !empty($m['is_ai_generated']);
        ?>
            <div class="flex <?= $isOut ? 'justify-end' : 'justify-start' ?> animate-fade-in" data-message-id="<?= (int) $m['id'] ?>">
                <div class="max-w-[75%]">
                    <?php if ($internal): ?>
                    <div class="px-4 py-3 rounded-2xl rounded-bl-sm border" style="background: rgba(245,158,11,.08); border-color: rgba(245,158,11,.25); color: #FBBF24;">
                        <div class="text-[10px] uppercase font-semibold tracking-wider mb-1.5">Nota interna</div>
                        <div class="text-sm whitespace-pre-line"><?= e((string) $m['content']) ?></div>
                    </div>
                    <?php else: ?>
                    <div class="px-4 py-2.5 <?= $isOut ? 'rounded-2xl rounded-br-sm text-white' : 'rounded-2xl rounded-bl-sm' ?>"
                         style="<?= $isOut ? 'background: var(--gradient-primary); box-shadow: 0 2px 8px rgba(124,58,237,.25);' : 'background: var(--color-bg-elevated); border: 1px solid var(--color-border-subtle); color: var(--color-text-primary); box-shadow: var(--shadow-xs);' ?>">
                        <?php if ($aiGen): ?><div class="text-[10px] opacity-80 mb-1">Generado por IA</div><?php endif; ?>
                        <?php if (!empty($m['media_url'])): ?>
                        <div class="mb-2 text-xs opacity-80"><a href="<?= e($m['media_url']) ?>" target="_blank" class="underline">Adjunto</a></div>
                        <?php endif; ?>
                        <div class="text-sm leading-relaxed whitespace-pre-line"><?= e((string) $m['content']) ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="text-[10px] mt-1.5 flex items-center gap-1 <?= $isOut ? 'justify-end' : '' ?>" style="color: var(--color-text-muted);">
                        <?php if (!empty($m['first_name'])): ?><span class="font-medium"><?= e($m['first_name']) ?></span><span>&middot;</span><?php endif; ?>
                        <span class="font-mono"><?= date('H:i', strtotime((string) $m['created_at'])) ?></span>
                        <?php if ($isOut): ?><span><?= e((string) $m['status']) ?></span><?php endif; ?>
                    </div>
                    <?php if (!empty($m['error_message'])): ?>
                    <div class="text-[10px] mt-1 text-red-400 text-right"><?= e((string) $m['error_message']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach;
        return (string) ob_get_clean();
    }
}
