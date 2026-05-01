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
use App\Models\WhatsappChannel;
use App\Services\AiAgentService;
use App\Services\AiProviderService;
use App\Services\ChannelDispatcher;
use App\Services\ClaudeService;
use App\Services\WasapiService;

final class InboxController extends Controller
{
    public function index(Request $request, array $params = []): void
    {
        $tenantId = Tenant::id();

        $filters = [
            'status'     => (string) $request->query('status', ''),
            'agent'      => $request->query('agent') ?: null,
            'channel'    => (string) $request->query('channel', ''),
            'channel_id' => $request->query('channel_id') ? (int) $request->query('channel_id') : null,
            'q'          => trim((string) $request->query('q', '')),
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

        $channels = WhatsappChannel::listForTenant($tenantId);

        $this->view('inbox.index', [
            'page'           => 'bandeja',
            'conversations'  => $conversations,
            'active'         => $active,
            'messages'       => $messages,
            'filters'        => $filters,
            'agents'         => User::listByTenant($tenantId),
            'aiAgents'       => $aiAgents,
            'channels'       => $channels,
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
        $channelOverride = $request->input('channel_id') ? (int) $request->input('channel_id') : null;

        if ($message === '') {
            $this->json(['success' => false, 'error' => 'Mensaje vacio']);
            return;
        }

        // Resolver canal a usar
        $channelId = $channelOverride ?? (isset($conv['channel_id']) ? (int) $conv['channel_id'] : null);
        if (!$channelId && $conv['channel'] === 'whatsapp') {
            $defaultChannel = WhatsappChannel::findDefault($tenantId);
            if ($defaultChannel) $channelId = (int) $defaultChannel['id'];
        }

        $messageId = Database::insert('messages', [
            'tenant_id'       => $tenantId,
            'conversation_id' => $convId,
            'contact_id'      => (int) $conv['contact_id'],
            'user_id'         => Auth::id(),
            'channel_id'      => $channelId,
            'direction'       => 'outbound',
            'type'            => 'text',
            'content'         => $message,
            'is_internal'     => $isInternal ? 1 : 0,
            'status'          => $isInternal ? 'sent' : 'queued',
            'sent_at'         => $isInternal ? date('Y-m-d H:i:s') : null,
        ]);

        // Si no es nota interna, intentar enviar a traves del dispatcher (Wasapi/Cloud/etc)
        $whatsappResult = ['success' => true];
        if (!$isInternal && $conv['channel'] === 'whatsapp' && !empty($conv['phone'] ?? $conv['whatsapp'])) {
            $phone = (string) ($conv['whatsapp'] ?: $conv['phone']);
            $dispatcher = new ChannelDispatcher($tenantId);
            $whatsappResult = $dispatcher->sendText($phone, $message, $channelId);
            Database::update('messages', [
                'status'        => $whatsappResult['success'] ? 'sent' : 'failed',
                'external_id'   => $whatsappResult['body']['id'] ?? null,
                'sent_at'       => $whatsappResult['success'] ? date('Y-m-d H:i:s') : null,
                'error_message' => $whatsappResult['success'] ? null : ($whatsappResult['error'] ?: 'Error envio'),
            ], ['id' => $messageId]);
            if ($whatsappResult['success'] && $channelId) {
                WhatsappChannel::touchActivity($channelId);
            }
        }

        // Actualizar conversacion (channel_id en try separado por si la columna aun no existe)
        Conversation::update($tenantId, $convId, [
            'last_message'    => mb_substr($message, 0, 200),
            'last_message_at' => date('Y-m-d H:i:s'),
            'status'          => 'open',
        ]);
        if ($channelId) {
            try {
                Conversation::update($tenantId, $convId, ['channel_id' => $channelId]);
            } catch (\Throwable) { /* columna aun no creada */ }
        }

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
        $action = (string) $request->query('action', $request->input('action', 'suggest'));

        // Acciones de transformación de texto del composer
        $transformModes = ['improve', 'formal', 'casual', 'shorter', 'longer', 'fix', 'translate', 'continue', 'emojify'];
        if (in_array($action, $transformModes, true)) {
            $userText = trim((string) $request->input('text', ''));
            if ($userText === '') {
                $this->json(['success' => false, 'error' => 'No hay texto para transformar.']);
                return;
            }
            $result = $svc->transformText($userText, $action, $transcript);
            if (!empty($result['success'])) {
                $this->json(['success' => true, 'text' => trim((string) $result['text'])]);
            } else {
                $this->json(['success' => false, 'error' => $result['error'] ?? 'No se pudo transformar el texto.']);
            }
            return;
        }

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
        $base = rtrim(url(''), '/');
        $linkify = static function (string $raw) use ($base): string {
            $escaped = e($raw);
            $escaped = preg_replace_callback(
                '~(?<![">\w])(https?://[^\s<]+)~i',
                static fn ($m) => '<a href="' . $m[1] . '" target="_blank" rel="noopener" class="msg-link">' . mb_strimwidth($m[1], 0, 60, '…') . '</a>',
                $escaped
            );
            $escaped = preg_replace_callback(
                '~#?(OR-[A-Z0-9-]{4,})~i',
                static fn ($m) => '<a href="' . $base . '/orders" class="msg-order-tag">📦 ' . strtoupper($m[1]) . '</a>',
                $escaped
            );
            return $escaped;
        };

        ob_start();
        $currentDate = '';
        foreach ($messages as $m):
            $msgDate = date('Y-m-d', strtotime((string) $m['created_at']));
            if ($msgDate !== $currentDate):
                $currentDate = $msgDate;
                $todayLabel = date('Y-m-d') === $msgDate ? 'Hoy' : (date('Y-m-d', strtotime('-1 day')) === $msgDate ? 'Ayer' : date('d M Y', strtotime($msgDate)));
        ?>
            <div class="flex justify-center my-4">
                <span class="px-3 py-1 rounded-full text-[10px] font-semibold uppercase tracking-[0.1em]" style="background: var(--color-bg-elevated); color: var(--color-text-tertiary); border: 1px solid var(--color-border-subtle);"><?= $todayLabel ?></span>
            </div>
        <?php endif;
            $isOut    = $m['direction'] === 'outbound';
            $internal = !empty($m['is_internal']);
            $aiGen    = !empty($m['is_ai_generated']);
            $body     = (string) $m['content'];
            $isImg    = !empty($m['media_url']) && preg_match('~\.(jpe?g|png|gif|webp)(\?|$)~i', (string) $m['media_url']);
            $isAud    = !empty($m['media_url']) && preg_match('~\.(mp3|ogg|wav|m4a|opus)(\?|$)~i', (string) $m['media_url']);
            $isVid    = !empty($m['media_url']) && preg_match('~\.(mp4|webm|mov)(\?|$)~i', (string) $m['media_url']);
        ?>
            <div class="msg-row flex <?= $isOut ? 'justify-end' : 'justify-start' ?> animate-fade-in group" data-message-id="<?= (int) $m['id'] ?>" data-search="<?= e(strtolower($body)) ?>">
                <div class="max-w-[78%] relative">
                    <?php if ($internal): ?>
                    <div class="px-4 py-3 rounded-2xl rounded-bl-sm border" style="background: rgba(245,158,11,.08); border-color: rgba(245,158,11,.25); color: #FBBF24;">
                        <div class="text-[10px] uppercase font-semibold tracking-wider mb-1.5 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M5 3a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V5a2 2 0 00-2-2H5z"/></svg>
                            Nota interna
                        </div>
                        <div class="text-sm whitespace-pre-line"><?= $linkify($body) ?></div>
                    </div>
                    <?php else: ?>
                    <div class="msg-bubble px-4 py-2.5 <?= $isOut ? 'rounded-2xl rounded-br-sm text-white' : 'rounded-2xl rounded-bl-sm' ?>"
                         style="<?= $isOut ? 'background: var(--gradient-primary); box-shadow: 0 2px 8px rgba(124,58,237,.25);' : 'background: var(--color-bg-elevated); border: 1px solid var(--color-border-subtle); color: var(--color-text-primary); box-shadow: var(--shadow-xs);' ?>">
                        <?php if ($aiGen): ?>
                        <div class="text-[10px] opacity-80 mb-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/></svg>
                            Generado por IA
                        </div>
                        <?php endif; ?>
                        <?php if ($isImg): ?>
                        <a href="<?= e((string) $m['media_url']) ?>" target="_blank" class="block mb-2 -mx-1 -mt-1">
                            <img src="<?= e((string) $m['media_url']) ?>" alt="" class="rounded-xl max-h-64 w-auto" style="max-width:100%;" loading="lazy">
                        </a>
                        <?php elseif ($isAud): ?>
                        <audio controls class="mb-2 w-full" style="max-width:280px;">
                            <source src="<?= e((string) $m['media_url']) ?>">
                        </audio>
                        <?php elseif ($isVid): ?>
                        <video controls class="mb-2 rounded-xl" style="max-height:240px; max-width:100%;">
                            <source src="<?= e((string) $m['media_url']) ?>">
                        </video>
                        <?php elseif (!empty($m['media_url'])): ?>
                        <div class="mb-2 text-xs opacity-80 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8 4a3 3 0 00-3 3v4a5 5 0 0010 0V7a1 1 0 112 0v4a7 7 0 11-14 0V7a5 5 0 0110 0v4a3 3 0 11-6 0V7a1 1 0 012 0v4a1 1 0 102 0V7a3 3 0 00-3-3z" clip-rule="evenodd"/></svg>
                            <a href="<?= e($m['media_url']) ?>" target="_blank" class="underline">Adjunto</a>
                        </div>
                        <?php endif; ?>
                        <div class="text-sm leading-relaxed whitespace-pre-line"><?= $linkify($body) ?></div>
                    </div>
                    <div class="msg-toolbar absolute top-1 <?= $isOut ? 'left-0 -translate-x-full -ml-1' : 'right-0 translate-x-full mr-1' ?> opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-0.5 rounded-lg p-0.5 shadow-lg" style="background: var(--color-bg-elevated); border: 1px solid var(--color-border-subtle);">
                        <button class="p-1 rounded hover:bg-white/5" title="Copiar" onclick="copyMsg(this)" data-text="<?= e($body) ?>">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" style="color: var(--color-text-secondary);"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </button>
                        <button class="p-1 rounded hover:bg-white/5" title="Citar" onclick="quoteMsg(this)" data-text="<?= e($body) ?>">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" style="color: var(--color-text-secondary);"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                        </button>
                    </div>
                    <?php endif; ?>
                    <div class="text-[10px] mt-1 flex items-center gap-1 <?= $isOut ? 'justify-end' : '' ?>" style="color: var(--color-text-muted);">
                        <?php if (!empty($m['first_name'])): ?><span class="font-medium"><?= e($m['first_name']) ?></span><span>&middot;</span><?php endif; ?>
                        <span class="font-mono"><?= date('H:i', strtotime((string) $m['created_at'])) ?></span>
                        <?php if ($isOut):
                            $statusIcon = match($m['status']) {
                                'sent'      => '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>',
                                'delivered' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7M9 17l4-4"/></svg>',
                                'read'      => '<svg class="w-3.5 h-3.5" fill="none" stroke="#06B6D4" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7M9 17l4-4"/></svg>',
                                default     => '',
                            };
                            echo $statusIcon;
                        endif; ?>
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
