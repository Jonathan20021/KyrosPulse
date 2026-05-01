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

        $history = $this->conversationHistory($conversationId, (int) ($agent['max_context_messages'] ?? 30));

        // Inyectar el carrito actual como parte del contexto. Si la conversacion
        // ya tiene items en curso, se los pasamos a la IA para que no los olvide.
        $cartContext = $this->renderCartForPrompt($conversationId);
        if ($cartContext !== '') {
            $history .= "\n\n" . $cartContext;
        }

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

    // ============================================================
    // Carrito persistente por conversacion (sobrevive entre turnos)
    // ============================================================

    /**
     * Lee el carrito actual de la conversacion. Estructura:
     *   { "items": [{ "name": "Hamburguesa Clasica", "qty": 2, "modifiers": [...], "notes": "..." }, ...] }
     */
    public function readCart(int $conversationId): array
    {
        try {
            $raw = Database::fetchColumn(
                "SELECT cart_state FROM conversations WHERE id = :c AND tenant_id = :t",
                ['c' => $conversationId, 't' => $this->tenantId]
            );
            if (!$raw) return ['items' => []];
            $decoded = json_decode((string) $raw, true);
            if (!is_array($decoded)) return ['items' => []];
            if (!isset($decoded['items']) || !is_array($decoded['items'])) $decoded['items'] = [];
            return $decoded;
        } catch (\Throwable) {
            return ['items' => []];
        }
    }

    /** Escribe el carrito (reemplaza). */
    public function writeCart(int $conversationId, array $cart): void
    {
        try {
            if (!isset($cart['items']) || !is_array($cart['items'])) $cart['items'] = [];
            Database::update('conversations',
                ['cart_state' => json_encode($cart, JSON_UNESCAPED_UNICODE)],
                ['id' => $conversationId, 'tenant_id' => $this->tenantId]
            );
        } catch (\Throwable $e) {
            Logger::error('writeCart fallo', ['msg' => $e->getMessage()]);
        }
    }

    /** Anade items al carrito existente, fusionando cantidades por nombre. */
    public function addToCart(int $conversationId, array $payload): void
    {
        $cart = $this->readCart($conversationId);
        $items = $payload['items'] ?? [(isset($payload['name']) ? $payload : null)];
        if (!is_array($items)) return;

        foreach ($items as $newItem) {
            if (!is_array($newItem) || empty($newItem['name'])) continue;
            $name = trim((string) $newItem['name']);
            $qty  = max(1, (int) ($newItem['qty'] ?? $newItem['quantity'] ?? 1));

            // Buscar match en carrito (mismo nombre + mismas notas) → sumar qty
            $matched = false;
            foreach ($cart['items'] as &$existing) {
                if (strcasecmp((string) $existing['name'], $name) === 0
                    && (string) ($existing['notes'] ?? '') === (string) ($newItem['notes'] ?? '')) {
                    $existing['qty'] = (int) ($existing['qty'] ?? 1) + $qty;
                    $matched = true;
                    break;
                }
            }
            unset($existing);

            if (!$matched) {
                $cart['items'][] = [
                    'name'      => $name,
                    'qty'       => $qty,
                    'unit_price' => $newItem['unit_price'] ?? null,
                    'notes'     => trim((string) ($newItem['notes'] ?? '')) ?: null,
                    'modifiers' => is_array($newItem['modifiers'] ?? null) ? $newItem['modifiers'] : null,
                ];
            }
        }

        // Datos del cliente / direccion / pago si vienen
        foreach (['customer_name','customer_phone','address','zone','payment','delivery_type','notes'] as $k) {
            if (!empty($payload[$k])) $cart[$k] = $payload[$k];
        }

        $this->writeCart($conversationId, $cart);
    }

    public function clearCart(int $conversationId): void
    {
        try {
            Database::update('conversations',
                ['cart_state' => null],
                ['id' => $conversationId, 'tenant_id' => $this->tenantId]
            );
        } catch (\Throwable) {}
    }

    /**
     * Renderiza el carrito en formato legible para inyectar al prompt de la IA.
     * Vacio si no hay items. Incluye datos del cliente si los tiene.
     */
    private function renderCartForPrompt(int $conversationId): string
    {
        $cart = $this->readCart($conversationId);
        if (empty($cart['items'])) return '';

        $lines = ['ESTADO ACTUAL DEL CARRITO (acumulado durante esta conversacion):'];
        foreach ($cart['items'] as $i) {
            $line = '  - ' . (int) ($i['qty'] ?? 1) . '× ' . ($i['name'] ?? '?');
            if (!empty($i['modifiers']) && is_array($i['modifiers'])) {
                $mods = array_map(fn ($m) => is_array($m) ? ($m['name'] ?? '') : (string) $m, $i['modifiers']);
                $mods = array_filter($mods);
                if (!empty($mods)) $line .= ' (' . implode(', ', $mods) . ')';
            }
            if (!empty($i['notes'])) $line .= ' [' . $i['notes'] . ']';
            $lines[] = $line;
        }

        $extras = [];
        foreach (['customer_name' => 'Cliente', 'address' => 'Direccion', 'zone' => 'Zona',
                  'delivery_type' => 'Tipo', 'payment' => 'Pago'] as $k => $lbl) {
            if (!empty($cart[$k])) $extras[] = "$lbl: {$cart[$k]}";
        }
        if (!empty($extras)) {
            $lines[] = 'Datos del pedido: ' . implode(' · ', $extras);
        }

        $lines[] = '';
        $lines[] = 'Si el cliente confirma ahora ("dale", "confirmo", "perfecto"), emite [ORDER:...] usando ESTOS items y ESTOS datos. NO pidas que vuelva a elegir.';

        return implode("\n", $lines);
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

    /**
     * Construye el historial de la conversacion para inyectar en el prompt.
     * Usa una ventana mas amplia (default 30) y aplica un cap de caracteres
     * (~10k) para no agotar tokens en chats muy largos.
     *
     * Tambien filtra notas internas + mensajes de sistema (no relevantes al cliente).
     */
    private function conversationHistory(int $conversationId, int $limit): string
    {
        $effectiveLimit = max(8, $limit);
        $messages = Message::listByConversation($this->tenantId, $conversationId, max(40, $effectiveLimit));
        $lines = [];
        foreach ($messages as $m) {
            // Saltar notas internas, mensajes system, fallidos
            if (!empty($m['is_internal'])) continue;
            if (($m['type'] ?? 'text') === 'system') continue;
            if (($m['status'] ?? '') === 'failed') continue;

            $who = $m['direction'] === 'inbound'
                ? 'Cliente'
                : (!empty($m['is_ai_generated']) ? 'IA' : 'Agente humano');
            $text = trim((string) ($m['content'] ?? ''));
            if ($text !== '') {
                $lines[] = $who . ': ' . $text;
            }
        }

        // Tomar los ultimos N respetando un tope de chars
        $lines = array_slice($lines, -max($effectiveLimit, 30));
        $maxChars = 10000;
        $out = [];
        $total = 0;
        foreach (array_reverse($lines) as $ln) {
            $len = mb_strlen($ln) + 1;
            if ($total + $len > $maxChars && !empty($out)) break;
            $out[] = $ln;
            $total += $len;
        }
        return implode("\n", array_reverse($out));
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
        $forbidden = [
            'Asistente', 'Asistente IA', 'Agente', 'Agente IA', 'Bot', 'IA', 'AI',
            'Cliente', 'Tu', 'Yo', 'Usuario', 'Empresa', 'Soporte', 'Ventas', 'Operador',
            'Soporte Tecnico', 'Soporte Técnico', 'Servicio al Cliente', 'Mesero', 'Mesera',
            'Maitre', 'Maitre BBQ', 'Vendedor', 'Vendedora',
        ];
        if (!empty($agent['name']))        $forbidden[] = (string) $agent['name'];
        if (!empty($agent['role']))        $forbidden[] = (string) $agent['role'];
        $brand = Database::fetchColumn("SELECT name FROM tenants WHERE id = :id", ['id' => $this->tenantId]);
        if ($brand)                         $forbidden[] = (string) $brand;

        $forbidden = array_values(array_unique(array_filter($forbidden, fn($s) => trim((string) $s) !== '')));

        // Loop hasta 6 veces removiendo prefijos sucesivos: "Agente: Soporte Tecnico: Hola...".
        for ($i = 0; $i < 6; $i++) {
            $original = $text;

            // 1) Prefijos exactos de la lista (al inicio, multiline para cubrir nuevas lineas)
            foreach ($forbidden as $f) {
                $escaped = preg_quote($f, '/');
                $text = (string) preg_replace(
                    '/^\s*\*?\*?' . $escaped . '\*?\*?\s*[:\-—–]\s*\n?/iu',
                    '',
                    $text,
                    1
                );
            }

            // 2) Prefijo generico "Palabra Palabra Palabra:" al inicio (max 4 palabras Capitalizadas seguido de :)
            $text = (string) preg_replace(
                '/^\s*\*?\*?(?:[A-ZÁÉÍÓÚÑ][\wáéíóúñ]*\s+){0,3}[A-ZÁÉÍÓÚÑ][\wáéíóúñ]{1,30}\*?\*?\s*:\s*\n?/u',
                '',
                $text,
                1
            );

            // 3) Asteriscos / saltos sueltos al inicio
            $text = ltrim($text, " *_-—–\t\r\n");

            if ($text === $original) break;
        }

        // Tambien remover prefijos que quedaron al inicio de cualquier linea posterior
        $lines = preg_split("/(\r?\n)/u", $text) ?: [$text];
        $cleanLines = [];
        foreach ($lines as $line) {
            foreach ($forbidden as $f) {
                $escaped = preg_quote($f, '/');
                $line = (string) preg_replace('/^\s*\*?\*?' . $escaped . '\*?\*?\s*:\s*/iu', '', $line, 1);
            }
            $cleanLines[] = $line;
        }
        $text = implode("\n", $cleanLines);

        // Eliminar saltos vacios dobles al inicio
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

        // [CART_ADD: {...}] o [CART_SET: {...}] tambien JSON
        if (preg_match_all('/\[CART_(ADD|SET):\s*(\{.+?\})\s*\]/is', $text, $cartMatches, PREG_SET_ORDER)) {
            foreach ($cartMatches as $m) {
                $actions[] = ['type' => 'CART_' . strtoupper($m[1]), 'args' => trim($m[2])];
            }
            $text = (string) preg_replace('/\[CART_(ADD|SET):\s*\{.+?\}\s*\]/is', '', $text);
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
            'CART_CLEAR'    => '/\[CART_CLEAR\]/i',
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
        // Ordenar por prioridad: primero CART_ADD/SET (alimenta carrito), despues ORDER
        // (consume carrito), CART_CLEAR al final. Otras en medio.
        $priority = [
            'CART_ADD'     => 10,
            'CART_SET'     => 11,
            'TAG'          => 20,
            'TICKET'       => 21,
            'SCHEDULE'     => 22,
            'ORDER'        => 30,
            'ORDER_STATUS' => 31,
            'PAYMENT_LINK' => 32,
            'CLOSE_SALE'   => 40,
            'CART_CLEAR'   => 80,
            'TRANSFER'     => 90,
            'END_CHAT'     => 99,
        ];
        usort($actions, fn ($a, $b) => ($priority[$a['type']] ?? 50) <=> ($priority[$b['type']] ?? 50));

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
                        // Si la IA emite ORDER sin items, intentamos rellenar con el carrito persistente.
                        if (empty($payload['items']) || !is_array($payload['items'])) {
                            $cart = $this->readCart($conversationId);
                            if (!empty($cart['items'])) $payload['items'] = $cart['items'];
                        }
                        $orderResult = (new RestaurantOrderEngine($this->tenantId))->createFromAi(
                            $payload,
                            $conversationId,
                            $contactId
                        );
                        // Limpiar carrito tras crear la orden con exito
                        if (!empty($orderResult['success'])) {
                            $this->clearCart($conversationId);
                        }
                        break;

                    case 'CART_ADD':
                    case 'CART_SET':
                        $payload = json_decode($args, true);
                        if (!is_array($payload)) {
                            Logger::warning('AI CART JSON invalido', ['args' => $args, 'type' => $type]);
                            break;
                        }
                        if ($type === 'CART_SET') {
                            $this->writeCart($conversationId, $payload);
                        } else {
                            $this->addToCart($conversationId, $payload);
                        }
                        break;

                    case 'CART_CLEAR':
                        $this->clearCart($conversationId);
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
