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

    /**
     * Genera y envia una auto-respuesta del agente IA asignado a la
     * conversacion. Tambien interpreta las acciones especiales que la IA
     * puede emitir como marcadores ([TRANSFER], [CLOSE_SALE], [SCHEDULE], etc).
     */
    public function autoReplyToConversation(int $conversationId, int $contactId, string $phone, string $incomingText, ?int $inboundMessageId = null): array
    {
        $agent = $this->activeAgentForConversation($conversationId);
        if (!$agent) {
            return ['success' => false, 'skipped' => true, 'reason' => 'Sin agente IA activo para auto-respuesta.'];
        }

        $history = $this->conversationHistory($conversationId, (int) ($agent['max_context_messages'] ?? 12));
        $ai = (new AiProviderService($this->tenantId, (int) $agent['id']))->autoReply($incomingText, $history);
        if (empty($ai['success']) || trim((string) ($ai['text'] ?? '')) === '') {
            $this->log($agent, $conversationId, $inboundMessageId, 'auto_reply', $history, '', false, $ai['error'] ?? 'IA sin respuesta.');
            return ['success' => false, 'error' => $ai['error'] ?? 'IA sin respuesta.'];
        }

        $rawReply = (string) $ai['text'];
        $parsed   = $this->parseActions($rawReply);
        $reply    = $parsed['text'];

        if ($reply === '') {
            $reply = 'Te conecto con un agente para asistirte mejor.';
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
            'metadata'        => json_encode([
                'agent_id' => (int) $agent['id'],
                'actions'  => $parsed['actions'],
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $send = (new WasapiService($this->tenantId))->sendTextMessage($phone, $reply);
        Database::update('messages', [
            'status'        => !empty($send['success']) ? 'sent' : 'failed',
            'external_id'   => $send['body']['id'] ?? null,
            'sent_at'       => !empty($send['success']) ? date('Y-m-d H:i:s') : null,
            'error_message' => !empty($send['success']) ? null : ($send['error'] ?: 'Error envio Wasapi'),
        ], ['id' => $messageId]);

        // Aplicar acciones que la IA pidio (cierre, agenda, transfer, etc.)
        $this->applyActions($parsed['actions'], $conversationId, $contactId, $reply, (int) $agent['id']);

        // Refrescar metadatos generales de la conversacion
        Database::update('conversations', [
            'last_message'    => mb_substr($reply, 0, 200),
            'last_message_at' => date('Y-m-d H:i:s'),
        ], ['id' => $conversationId, 'tenant_id' => $this->tenantId]);

        $this->log($agent, $conversationId, $messageId, 'auto_reply', $history, $reply, !empty($send['success']), $send['error'] ?? null, $parsed['actions']);

        return [
            'success'    => !empty($send['success']),
            'message_id' => $messageId,
            'text'       => $reply,
            'sent'       => !empty($send['success']),
            'actions'    => $parsed['actions'],
            'error'      => $send['error'] ?? null,
        ];
    }

    /**
     * Elige el agente IA que debe atender la conversacion.
     *  - Si la conversacion tiene `ai_agent_id` definido, usa ese.
     *  - Si la conversacion tiene `ai_takeover = 1`, fuerza el uso del agente
     *    (incluso si el bot esta en off para otros canales).
     *  - Si no, usa el agente principal del tenant.
     */
    private function activeAgentForConversation(int $conversationId): ?array
    {
        $tenant = Database::fetch(
            "SELECT ai_enabled FROM tenants WHERE id = :id",
            ['id' => $this->tenantId]
        );

        $conversation = Database::fetch(
            "SELECT bot_enabled, ai_agent_id, ai_takeover, ai_paused_until, status
             FROM conversations WHERE id = :id AND tenant_id = :t",
            ['id' => $conversationId, 't' => $this->tenantId]
        );

        if (!$conversation) {
            return null;
        }
        if (in_array((string) $conversation['status'], ['closed', 'resolved'], true)) {
            return null;
        }
        if (!empty($conversation['ai_paused_until']) && strtotime((string) $conversation['ai_paused_until']) > time()) {
            return null;
        }

        $takeover = !empty($conversation['ai_takeover']);
        if (!$takeover) {
            if (empty($tenant['ai_enabled'])) {
                return null;
            }
            if (empty($conversation['bot_enabled'])) {
                return null;
            }
        }

        $agent = null;
        if (!empty($conversation['ai_agent_id'])) {
            $agent = Database::fetch(
                "SELECT * FROM ai_agents WHERE id = :id AND tenant_id = :t AND status = 'active'",
                ['id' => (int) $conversation['ai_agent_id'], 't' => $this->tenantId]
            );
        }

        if (!$agent) {
            try {
                $agent = AiAgent::findDefault($this->tenantId);
            } catch (\Throwable) {
                $agent = null;
            }
        }

        if (!$agent) {
            return null;
        }

        // Si no hay takeover forzado, requiere que el agente tenga auto-reply
        if (!$takeover && empty($agent['auto_reply_enabled'])) {
            return null;
        }

        return $agent;
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

    /**
     * Extrae marcadores [ACCION: args] al final del texto. El sistema los
     * ejecuta y los REMUEVE del mensaje antes de enviarlo al cliente.
     */
    public function parseActions(string $text): array
    {
        $actions = [];
        $patterns = [
            'TRANSFER'   => '/\[TRANSFER\]/i',
            'CLOSE_SALE' => '/\[CLOSE_SALE(?::\s*([^\]]+))?\]/i',
            'SCHEDULE'   => '/\[SCHEDULE:\s*([^\]]+)\]/i',
            'END_CHAT'   => '/\[END_CHAT(?::\s*([^\]]+))?\]/i',
            'TAG'        => '/\[TAG:\s*([^\]]+)\]/i',
            'TICKET'     => '/\[TICKET:\s*([^\]]+)\]/i',
        ];
        foreach ($patterns as $key => $regex) {
            if (preg_match_all($regex, $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $actions[] = [
                        'type' => $key,
                        'args' => isset($m[1]) ? trim((string) $m[1]) : '',
                    ];
                }
                $text = (string) preg_replace($regex, '', $text);
            }
        }
        return ['text' => trim($text), 'actions' => $actions];
    }

    /** Ejecuta las acciones extraidas del marcador. */
    private function applyActions(array $actions, int $conversationId, int $contactId, string $reply, int $agentId): void
    {
        foreach ($actions as $a) {
            try {
                $type = $a['type'];
                $args = (string) ($a['args'] ?? '');

                switch ($type) {
                    case 'TRANSFER':
                        Database::update('conversations', [
                            'status'      => 'pending',
                            'bot_enabled' => 0,
                            'ai_takeover' => 0,
                        ], ['id' => $conversationId, 'tenant_id' => $this->tenantId]);
                        Database::insert('conversation_assignments', [
                            'tenant_id'        => $this->tenantId,
                            'conversation_id'  => $conversationId,
                            'action'           => 'unassigned',
                            'note'             => 'IA solicito transferir a humano.',
                        ]);
                        break;

                    case 'CLOSE_SALE':
                        Database::insert('tasks', [
                            'tenant_id'    => $this->tenantId,
                            'title'        => 'Venta cerrada por IA',
                            'description'  => 'Detalle: ' . $args,
                            'contact_id'   => $contactId,
                            'priority'     => 'high',
                            'status'       => 'completed',
                            'completed_at' => date('Y-m-d H:i:s'),
                        ]);
                        Database::update('contacts', [
                            'lifecycle_stage'  => 'customer',
                            'last_interaction' => date('Y-m-d H:i:s'),
                            'status'           => 'active',
                        ], ['id' => $contactId, 'tenant_id' => $this->tenantId]);
                        break;

                    case 'SCHEDULE':
                        // Args esperados: "YYYY-MM-DD HH:MM, motivo"
                        $parts = array_map('trim', explode(',', $args, 2));
                        $when  = $parts[0] ?? '';
                        $why   = $parts[1] ?? 'Cita agendada por IA';
                        $ts    = strtotime($when);
                        if ($ts) {
                            Database::insert('tasks', [
                                'tenant_id'   => $this->tenantId,
                                'title'       => mb_substr($why, 0, 200),
                                'description' => 'Agendado automaticamente por agente IA en la conversacion #' . $conversationId,
                                'contact_id'  => $contactId,
                                'due_at'      => date('Y-m-d H:i:s', $ts),
                                'priority'    => 'medium',
                                'status'      => 'pending',
                            ]);
                        }
                        break;

                    case 'END_CHAT':
                        Database::update('conversations', [
                            'status'        => 'resolved',
                            'closed_at'     => date('Y-m-d H:i:s'),
                            'closed_reason' => $args !== '' ? mb_substr($args, 0, 250) : 'Resuelto por IA',
                            'ai_takeover'   => 0,
                        ], ['id' => $conversationId, 'tenant_id' => $this->tenantId]);
                        break;

                    case 'TAG':
                        $tagName = mb_substr(trim($args), 0, 60);
                        if ($tagName === '') break;
                        $tag = Database::fetch(
                            "SELECT id FROM tags WHERE tenant_id = :t AND name = :n LIMIT 1",
                            ['t' => $this->tenantId, 'n' => $tagName]
                        );
                        if (!$tag) {
                            $tagId = Database::insert('tags', [
                                'tenant_id' => $this->tenantId,
                                'name'      => $tagName,
                                'color'     => '#7C3AED',
                            ]);
                        } else {
                            $tagId = (int) $tag['id'];
                        }
                        $exists = Database::fetchColumn(
                            "SELECT COUNT(*) FROM contact_tags WHERE tenant_id = :t AND contact_id = :c AND tag_id = :tag",
                            ['t' => $this->tenantId, 'c' => $contactId, 'tag' => $tagId]
                        );
                        if (!$exists) {
                            Database::insert('contact_tags', [
                                'tenant_id'  => $this->tenantId,
                                'contact_id' => $contactId,
                                'tag_id'     => $tagId,
                            ]);
                        }
                        break;

                    case 'TICKET':
                        // Args: "titulo, prioridad"
                        $parts    = array_map('trim', explode(',', $args, 2));
                        $title    = $parts[0] ?? 'Ticket creado por IA';
                        $priority = strtolower($parts[1] ?? 'medium');
                        if (!in_array($priority, ['low','medium','high','critical'], true)) {
                            $priority = 'medium';
                        }
                        Database::insert('tickets', [
                            'tenant_id'       => $this->tenantId,
                            'code'            => 'AI-' . date('YmdHis') . '-' . $contactId,
                            'contact_id'      => $contactId,
                            'conversation_id' => $conversationId,
                            'subject'         => mb_substr($title, 0, 250),
                            'description'     => 'Ticket abierto automaticamente por agente IA.',
                            'status'          => 'open',
                            'priority'        => $priority,
                            'channel'         => 'whatsapp',
                        ]);
                        break;
                }
            } catch (\Throwable $e) {
                Logger::error('AI action failed', [
                    'tenant'    => $this->tenantId,
                    'conv'      => $conversationId,
                    'action'    => $a,
                    'msg'       => $e->getMessage(),
                ]);
            }
        }
    }

    private function log(array $agent, int $conversationId, ?int $messageId, string $action, string $prompt, string $response, bool $success, ?string $error, array $actions = []): void
    {
        try {
            Database::insert('ai_agent_logs', [
                'tenant_id'       => $this->tenantId,
                'agent_id'        => (int) $agent['id'],
                'conversation_id' => $conversationId,
                'message_id'      => $messageId,
                'action'          => $action,
                'action_payload'  => !empty($actions) ? json_encode($actions, JSON_UNESCAPED_UNICODE) : null,
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
