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
use App\Models\QuickReply;
use App\Models\User;
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

        $this->view('inbox.index', [
            'page'           => 'bandeja',
            'conversations'  => $conversations,
            'active'         => $active,
            'messages'       => $messages,
            'filters'        => $filters,
            'agents'         => User::listByTenant($tenantId),
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
                'error_message' => $whatsappResult['success'] ? null : ($whatsappResult['error'] ?? 'Error envio'),
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

        $svc = new ClaudeService($tenantId);
        $action = (string) $request->input('action', 'suggest');

        $result = match ($action) {
            'summarize' => $svc->summarizeConversation($transcript),
            'sentiment' => $svc->evaluateSentiment($transcript),
            'next'      => $svc->recommendNextAction($transcript),
            default     => $svc->suggestReply($transcript),
        };

        if (!empty($result['success'])) {
            $text = trim((string) $result['text']);

            // Guardar el resumen/sentimiento en la conversacion
            $update = [];
            if ($action === 'summarize') $update['ai_summary'] = $text;
            elseif ($action === 'sentiment') {
                $sent = strtolower($text);
                if (str_contains($sent, 'positive')) $update['ai_sentiment'] = 'positive';
                elseif (str_contains($sent, 'negative')) $update['ai_sentiment'] = 'negative';
                else $update['ai_sentiment'] = 'neutral';
            }
            elseif ($action === 'next') $update['ai_next_action'] = $text;
            if ($update) Conversation::update($tenantId, $convId, $update);

            $this->json(['success' => true, 'text' => $text]);
            return;
        }

        $this->json(['success' => false, 'error' => 'No se pudo conectar con Claude. Verifica tu API key.']);
    }
}
