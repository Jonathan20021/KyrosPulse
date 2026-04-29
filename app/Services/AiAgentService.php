<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\AiAgent;
use App\Models\Message;

final class AiAgentService
{
    public function __construct(private int $tenantId) {}

    public function autoReplyToConversation(int $conversationId, int $contactId, string $phone, string $incomingText, ?int $inboundMessageId = null): array
    {
        $agent = $this->activeAgentForConversation($conversationId);
        if (!$agent) {
            return ['success' => false, 'skipped' => true, 'reason' => 'Sin agente IA activo para auto-respuesta.'];
        }

        $history = $this->conversationHistory($conversationId, (int) ($agent['max_context_messages'] ?? 12));
        $ai = (new ClaudeService($this->tenantId))->autoReply($incomingText, $history);
        if (empty($ai['success']) || trim((string) ($ai['text'] ?? '')) === '') {
            $this->log($agent, $conversationId, $inboundMessageId, 'auto_reply', $history, '', false, $ai['error'] ?? 'IA sin respuesta.');
            return ['success' => false, 'error' => $ai['error'] ?? 'IA sin respuesta.'];
        }

        $reply = trim((string) $ai['text']);
        $transfer = str_contains($reply, '[TRANSFER]');
        $reply = trim(str_replace('[TRANSFER]', '', $reply));

        if ($reply === '') {
            $reply = 'Te conecto con un agente.';
        }

        $messageId = Database::insert('messages', [
            'tenant_id'       => $this->tenantId,
            'conversation_id' => $conversationId,
            'contact_id'      => $contactId,
            'direction'       => 'outbound',
            'type'            => 'text',
            'content'         => $reply,
            'is_ai_generated' => 1,
            'status'          => 'queued',
            'metadata'        => json_encode(['agent_id' => (int) $agent['id'], 'transfer' => $transfer], JSON_UNESCAPED_UNICODE),
        ]);

        if ($transfer) {
            Database::update('conversations', [
                'status' => 'pending',
                'bot_enabled' => 0,
                'last_message' => mb_substr($reply, 0, 200),
                'last_message_at' => date('Y-m-d H:i:s'),
            ], ['id' => $conversationId, 'tenant_id' => $this->tenantId]);
        } else {
            Database::update('conversations', [
                'last_message' => mb_substr($reply, 0, 200),
                'last_message_at' => date('Y-m-d H:i:s'),
            ], ['id' => $conversationId, 'tenant_id' => $this->tenantId]);
        }

        $send = (new WasapiService($this->tenantId))->sendTextMessage($phone, $reply);
        Database::update('messages', [
            'status'        => !empty($send['success']) ? 'sent' : 'failed',
            'external_id'   => $send['body']['id'] ?? null,
            'sent_at'       => !empty($send['success']) ? date('Y-m-d H:i:s') : null,
            'error_message' => !empty($send['success']) ? null : ($send['error'] ?: 'Error envio Wasapi'),
        ], ['id' => $messageId]);

        $this->log($agent, $conversationId, $messageId, 'auto_reply', $history, $reply, !empty($send['success']), $send['error'] ?? null);

        return [
            'success' => !empty($send['success']),
            'message_id' => $messageId,
            'text' => $reply,
            'sent' => !empty($send['success']),
            'transfer' => $transfer,
            'error' => $send['error'] ?? null,
        ];
    }

    private function activeAgentForConversation(int $conversationId): ?array
    {
        $tenant = Database::fetch(
            "SELECT ai_enabled FROM tenants WHERE id = :id",
            ['id' => $this->tenantId]
        );
        if (empty($tenant['ai_enabled'])) {
            return null;
        }

        $conversation = Database::fetch(
            "SELECT bot_enabled FROM conversations WHERE id = :id AND tenant_id = :t",
            ['id' => $conversationId, 't' => $this->tenantId]
        );
        if ($conversation && empty($conversation['bot_enabled'])) {
            return null;
        }

        try {
            $agent = AiAgent::findDefault($this->tenantId);
        } catch (\Throwable) {
            $agent = null;
        }

        if ($agent && !empty($agent['auto_reply_enabled'])) {
            return $agent;
        }

        return null;
    }

    private function conversationHistory(int $conversationId, int $limit): string
    {
        $messages = Message::listByConversation($this->tenantId, $conversationId, max(4, $limit));
        $lines = [];
        foreach ($messages as $m) {
            $who = $m['direction'] === 'inbound' ? 'Cliente' : (!empty($m['is_ai_generated']) ? 'Agente IA' : 'Agente');
            $text = trim((string) ($m['content'] ?? ''));
            if ($text !== '') {
                $lines[] = $who . ': ' . $text;
            }
        }

        return implode("\n", array_slice($lines, -$limit));
    }

    private function log(array $agent, int $conversationId, ?int $messageId, string $action, string $prompt, string $response, bool $success, ?string $error): void
    {
        try {
            Database::insert('ai_agent_logs', [
                'tenant_id'       => $this->tenantId,
                'agent_id'        => (int) $agent['id'],
                'conversation_id' => $conversationId,
                'message_id'      => $messageId,
                'action'          => $action,
                'prompt'          => mb_substr($prompt, 0, 12000),
                'response'        => mb_substr($response, 0, 12000),
                'success'         => $success ? 1 : 0,
                'error_message'   => $error,
            ]);
        } catch (\Throwable $e) {
            Logger::error('No se pudo registrar ai_agent_log', ['msg' => $e->getMessage()]);
        }
    }
}
