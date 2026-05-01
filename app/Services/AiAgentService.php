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
        $agent = $this->activeAgentForConversation($conversationId, $incomingText);
        if (!$agent) {
            return ['success' => false, 'skipped' => true, 'reason' => 'Sin agente IA activo para auto-respuesta.'];
        }

        // Si el cliente esta pidiendo humano, escalamos sin gastar tokens.
        $router = new AiRouterService($this->tenantId);
        if ($router->clientWantsHuman($agent, $incomingText)) {
            $this->escalateToHuman($conversationId, 'Cliente solicito hablar con humano.');
            return ['success' => true, 'transferred' => true, 'reason' => 'Cliente pidio humano.'];
        }

        $history = $this->conversationHistory($conversationId, (int) ($agent['max_context_messages'] ?? 12));
        $provider = new AiProviderService($this->tenantId, (int) $agent['id']);

        // 1er intento (con prompt completo: menu + KB + productos)
        $ai = $provider->autoReply($incomingText, $history);

        // Reintento con prompt MUCHO mas pequeno si fallo (sin menu/kb)
        if (empty($ai['success']) || trim((string) ($ai['text'] ?? '')) === '') {
            Logger::warning('AI primer intento fallo, reintentando con prompt minimo', [
                'conv'  => $conversationId,
                'error' => $ai['error'] ?? 'sin texto',
            ]);
            $ai = $provider->autoReply($incomingText, $history, true /* minimal */);
        }

        if (empty($ai['success']) || trim((string) ($ai['text'] ?? '')) === '') {
            $errorMsg = (string) ($ai['error'] ?? 'IA sin respuesta');
            $this->log($agent, $conversationId, $inboundMessageId, 'auto_reply', $history, '', false, $errorMsg);
            $isTransient = $this->isTransientError($errorMsg);

            // Solo escalar si NO es un error transitorio (red, timeout, rate limit)
            if (!$isTransient) {
                $this->incrementFailedAttempts($conversationId, (int) ($agent['max_retries'] ?? 3));
            }

            // Visibilidad: nota interna en el chat para que el operador vea el error
            $this->writeAiErrorNote($conversationId, $contactId, $errorMsg, $isTransient);

            return ['success' => false, 'error' => $errorMsg, 'transient' => $isTransient];
        }

        $rawReply = (string) $ai['text'];
        $parsed   = $this->parseActions($rawReply);
        $reply    = $this->sanitizeReply($parsed['text'], $agent);

        if ($reply === '') {
            // Si la IA solo emitio acciones sin texto, no enviamos basura al cliente.
            $reply = '👍';
        }

        // Resolver canal preferido de la conversacion para enviar por el numero correcto
        $conv = Database::fetch(
            "SELECT channel_id FROM conversations WHERE id = :id AND tenant_id = :t",
            ['id' => $conversationId, 't' => $this->tenantId]
        );
        $channelId = isset($conv['channel_id']) ? (int) $conv['channel_id'] : null;

        $messageId = Database::insert('messages', [
            'tenant_id'       => $this->tenantId,
            'conversation_id' => $conversationId,
            'contact_id'      => $contactId,
            'channel_id'      => $channelId,
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

        // Enviar a traves del dispatcher para soportar multi-canal (Wasapi/Cloud/Twilio)
        $send = (new ChannelDispatcher($this->tenantId))->sendText($phone, $reply, $channelId);
        Database::update('messages', [
            'status'        => !empty($send['success']) ? 'sent' : 'failed',
            'external_id'   => $send['body']['id'] ?? null,
            'sent_at'       => !empty($send['success']) ? date('Y-m-d H:i:s') : null,
            'error_message' => !empty($send['success']) ? null : ($send['error'] ?: 'Error envio'),
        ], ['id' => $messageId]);

        // Aplicar acciones que la IA pidio (cierre, agenda, transfer, etc.)
        $this->applyActions($parsed['actions'], $conversationId, $contactId, $reply, (int) $agent['id']);

        // Refrescar metadatos generales de la conversacion + contador de exitos
        Database::run(
            "UPDATE conversations
             SET last_message = :msg,
                 last_message_at = NOW(),
                 ai_handled_count = ai_handled_count + 1,
                 ai_failed_attempts = 0,
                 ai_last_run_at = NOW()
             WHERE id = :id AND tenant_id = :t",
            [
                'msg' => mb_substr($reply, 0, 200),
                'id'  => $conversationId,
                't'   => $this->tenantId,
            ]
        );

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
     * Detecta errores transitorios que NO deben contar como fallo logico para escalar.
     * Network timeout, rate limit, gateway 5xx, etc.
     */
    private function isTransientError(string $errorMsg): bool
    {
        $msg = strtolower($errorMsg);
        $patterns = ['timeout', 'connection', 'curl', 'rate limit', 'rate_limit', '429', '502', '503', '504', 'gateway', 'overloaded', 'unavailable'];
        foreach ($patterns as $p) {
            if (str_contains($msg, $p)) return true;
        }
        return false;
    }

    /** Inserta una nota interna visible solo para el operador con el error de la IA. */
    private function writeAiErrorNote(int $conversationId, int $contactId, string $error, bool $transient): void
    {
        try {
            $prefix = $transient ? '⚠ Reintentando despues del proximo mensaje' : '✗ IA fallo';
            Database::insert('messages', [
                'tenant_id'       => $this->tenantId,
                'conversation_id' => $conversationId,
                'contact_id'      => $contactId,
                'direction'       => 'outbound',
                'type'            => 'system',
                'content'         => $prefix . ': ' . mb_substr($error, 0, 250),
                'is_internal'     => 1,
                'is_ai_generated' => 1,
                'status'          => 'sent',
                'sent_at'         => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {}
    }

    /**
     * Elige el agente IA que debe atender la conversacion.
     *  - Si la conversacion tiene `ai_agent_id`, usa ese (override manual).
     *  - Si tiene `ai_takeover = 1`, fuerza usar IA aunque el tenant tenga ai_enabled=0.
     *  - Si no, usa el AiRouterService para escoger por keywords/categoria/horario.
     */
    private function activeAgentForConversation(int $conversationId, string $incomingText = ''): ?array
    {
        $tenant = Database::fetch(
            "SELECT ai_enabled FROM tenants WHERE id = :id",
            ['id' => $this->tenantId]
        );

        $conversation = Database::fetch(
            "SELECT bot_enabled, ai_agent_id, ai_takeover, ai_paused_until, status, channel, ai_failed_attempts
             FROM conversations WHERE id = :id AND tenant_id = :t",
            ['id' => $conversationId, 't' => $this->tenantId]
        );

        if (!$conversation) return null;
        if (in_array((string) $conversation['status'], ['closed', 'resolved'], true)) return null;
        if (!empty($conversation['ai_paused_until']) && strtotime((string) $conversation['ai_paused_until']) > time()) return null;

        $takeover = !empty($conversation['ai_takeover']);
        if (!$takeover) {
            if (empty($tenant['ai_enabled'])) return null;
            if (empty($conversation['bot_enabled'])) return null;
        }

        // Si la conversacion tiene un agente fijado manualmente, respetalo
        if (!empty($conversation['ai_agent_id'])) {
            $agent = Database::fetch(
                "SELECT * FROM ai_agents WHERE id = :id AND tenant_id = :t AND status = 'active'",
                ['id' => (int) $conversation['ai_agent_id'], 't' => $this->tenantId]
            );
            if ($agent && ($takeover || !empty($agent['auto_reply_enabled']))) {
                return $agent;
            }
        }

        // Sin agente fijo: el router decide por reglas
        $channel = (string) ($conversation['channel'] ?? 'whatsapp');
        $router  = new AiRouterService($this->tenantId);
        $picked  = $router->pickAgentForMessage($conversationId, $channel, $incomingText);
        if (!$picked) return null;

        if (!$takeover && empty($picked['auto_reply_enabled'])) return null;

        // Memorizar el agente elegido SOLO si la conversacion no tenia uno aun
        Database::run(
            "UPDATE conversations SET ai_agent_id = :a WHERE id = :c AND tenant_id = :t AND ai_agent_id IS NULL",
            ['a' => (int) $picked['id'], 'c' => $conversationId, 't' => $this->tenantId]
        );

        return $picked;
    }

    /** Incrementa contador de fallos. Si supera max_retries, escala. */
    private function incrementFailedAttempts(int $conversationId, int $maxRetries): void
    {
        Database::run(
            "UPDATE conversations
             SET ai_failed_attempts = ai_failed_attempts + 1
             WHERE id = :c AND tenant_id = :t",
            ['c' => $conversationId, 't' => $this->tenantId]
        );
        $cur = (int) Database::fetchColumn(
            "SELECT ai_failed_attempts FROM conversations WHERE id = :c AND tenant_id = :t",
            ['c' => $conversationId, 't' => $this->tenantId]
        );
        if ($cur >= max(1, $maxRetries)) {
            $this->escalateToHuman($conversationId, "IA fallo $cur veces. Escalado automatico.");
        }
    }

    /** Marca la conversacion como pendiente de humano y desactiva auto-respuesta. */
    private function escalateToHuman(int $conversationId, string $reason): void
    {
        Database::update('conversations', [
            'status'      => 'pending',
            'bot_enabled' => 0,
            'ai_takeover' => 0,
        ], ['id' => $conversationId, 'tenant_id' => $this->tenantId]);

        try {
            Database::insert('conversation_assignments', [
                'tenant_id'        => $this->tenantId,
                'conversation_id'  => $conversationId,
                'action'           => 'unassigned',
                'note'             => $reason,
            ]);
        } catch (\Throwable) {}
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
     * Limpia prefijos tipo "Soporte Tecnico:", "Agente:", "Cliente:", su nombre
     * propio, etc. que la IA a veces antepone al mensaje. Tambien quita guiones
     * teatrales ("- Hola..."), markdown excesivo y dobles saltos al inicio.
     */
    public function sanitizeReply(string $text, ?array $agent = null): string
    {
        $text = trim($text);
        if ($text === '') return '';

        // Quitar bloques de codigo markdown que la IA a veces agrega.
        $text = preg_replace('/^```[\w]*\s*/u', '', $text);
        $text = preg_replace('/\s*```\s*$/u', '', $text);

        // Construir lista de prefijos prohibidos (nombre del agente, rol, comunes).
        $forbidden = ['Asistente', 'Asistente IA', 'Agente', 'Agente IA', 'Bot', 'IA', 'AI', 'Cliente', 'Tu', 'Yo', 'Usuario', 'Empresa', 'Soporte', 'Ventas', 'Operador'];
        if (!empty($agent['name']))        $forbidden[] = (string) $agent['name'];
        if (!empty($agent['role']))        $forbidden[] = (string) $agent['role'];
        $brand = Database::fetchColumn("SELECT name FROM tenants WHERE id = :id", ['id' => $this->tenantId]);
        if ($brand)                         $forbidden[] = (string) $brand;

        $forbidden = array_values(array_unique(array_filter($forbidden, fn($s) => trim((string) $s) !== '')));

        // Loop hasta 4 veces removiendo prefijos sucesivos como "Agente: Soporte Tecnico: Hola...".
        for ($i = 0; $i < 4; $i++) {
            $original = $text;

            // 1) Prefijos exactos de la lista
            foreach ($forbidden as $f) {
                $escaped = preg_quote($f, '/');
                $text = (string) preg_replace(
                    '/^\s*\*?\*?' . $escaped . '\*?\*?\s*[:\-—–]\s*/iu',
                    '',
                    $text,
                    1
                );
            }

            // 2) Prefijo generico "Palabra Palabra Palabra:" al inicio (max 4 palabras Capitalizadas)
            $text = (string) preg_replace(
                '/^\s*(?:[A-ZÁÉÍÓÚÑ][\wáéíóúñ]*\s+){0,3}[A-ZÁÉÍÓÚÑ][\wáéíóúñ]{1,30}\s*:\s+/u',
                '',
                $text,
                1
            );

            // 3) Asteriscos sueltos al inicio
            $text = ltrim($text, " *_-—–\t\r\n");

            if ($text === $original) break;
        }

        // Eliminar lineas vacias dobles al inicio
        $text = preg_replace("/^[\s]+/u", '', $text);

        return trim((string) $text);
    }

    /**
     * Extrae marcadores [ACCION: args] al final del texto. El sistema los
     * ejecuta y los REMUEVE del mensaje antes de enviarlo al cliente.
     */
    public function parseActions(string $text): array
    {
        $actions = [];

        // [ORDER: {...}] requiere captura non-greedy con balance de llaves.
        // Lo tratamos primero porque su contenido puede contener corchetes simples.
        if (preg_match_all('/\[ORDER:\s*(\{.+?\})\s*\]/is', $text, $orderMatches, PREG_SET_ORDER)) {
            foreach ($orderMatches as $m) {
                $actions[] = ['type' => 'ORDER', 'args' => trim($m[1])];
            }
            $text = (string) preg_replace('/\[ORDER:\s*\{.+?\}\s*\]/is', '', $text);
        }

        $patterns = [
            'TRANSFER'      => '/\[TRANSFER\]/i',
            'CLOSE_SALE'    => '/\[CLOSE_SALE(?::\s*([^\]]+))?\]/i',
            'SCHEDULE'      => '/\[SCHEDULE:\s*([^\]]+)\]/i',
            'END_CHAT'      => '/\[END_CHAT(?::\s*([^\]]+))?\]/i',
            'TAG'           => '/\[TAG:\s*([^\]]+)\]/i',
            'TICKET'        => '/\[TICKET:\s*([^\]]+)\]/i',
            'ORDER_STATUS'  => '/\[ORDER_STATUS:\s*([^\]]+)\]/i',
            'PAYMENT_LINK'  => '/\[PAYMENT_LINK:\s*([^\]]+)\]/i',
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

                    case 'ORDER':
                        $payload = json_decode($args, true);
                        if (!is_array($payload)) {
                            Logger::warning('AI ORDER JSON invalido', ['args' => $args]);
                            break;
                        }
                        (new RestaurantOrderEngine($this->tenantId))->createFromAi(
                            $payload,
                            $conversationId,
                            $contactId
                        );
                        break;

                    case 'ORDER_STATUS':
                        $parts = array_map('trim', explode(',', $args, 2));
                        $orderRef = $parts[0] ?? '';
                        $newStatus = strtolower($parts[1] ?? '');
                        if ($orderRef && $newStatus) {
                            (new RestaurantOrderEngine($this->tenantId))->updateStatusByRef($orderRef, $newStatus);
                        }
                        break;

                    case 'PAYMENT_LINK':
                        $orderRef = trim($args);
                        if ($orderRef !== '') {
                            (new RestaurantOrderEngine($this->tenantId))->generatePaymentLink($orderRef, $conversationId);
                        }
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
