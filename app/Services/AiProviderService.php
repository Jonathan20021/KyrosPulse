<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\AiAgent;
use App\Models\GlobalAiProvider;

/**
 * Capa de abstraccion que decide entre Claude u OpenAI segun la
 * configuracion del tenant (campo `ai_provider`). Construye el system
 * prompt unificado que incluye la base de conocimiento + el rol del agente.
 */
final class AiProviderService
{
    public function __construct(private int $tenantId, private ?int $agentId = null) {}

    public function tenantConfig(): array
    {
        $row = Database::fetch(
            "SELECT name, ai_assistant_name, ai_tone, ai_enabled, ai_provider,
                    claude_api_key, claude_model, openai_api_key, openai_model,
                    global_ai_provider_id, ai_token_quota, ai_tokens_used_period, ai_token_period_starts_at,
                    business_hours, out_of_hours_msg, welcome_message, language
             FROM tenants WHERE id = :id",
            ['id' => $this->tenantId]
        );
        return is_array($row) ? $row : [];
    }

    /**
     * Resuelve cual proveedor IA usar para este tenant.
     * Reglas (en orden):
     *   1. Si el tenant tiene su propia API key (claude o openai segun ai_provider) -> usa la propia.
     *   2. Si el super admin asigno un global_ai_provider al tenant -> usa el global asignado.
     *   3. Si hay un global_ai_provider con is_default = 1 -> usa ese.
     *   4. Else null (sin IA disponible).
     *
     * Devuelve un array con: source ('own'|'global'), provider ('claude'|'openai'),
     * api_key, model, global_provider_id (si aplica), display_name.
     */
    public function resolve(): ?array
    {
        $cfg = $this->tenantConfig();
        $providerType = strtolower((string) ($cfg['ai_provider'] ?? 'claude'));
        if (!in_array($providerType, ['claude','openai'], true)) $providerType = 'claude';

        // 1) Key propia del tenant
        $ownKey = $providerType === 'openai'
            ? trim((string) ($cfg['openai_api_key'] ?? ''))
            : trim((string) ($cfg['claude_api_key'] ?? ''));
        if ($ownKey !== '') {
            $ownModel = $providerType === 'openai'
                ? (string) ($cfg['openai_model'] ?? '')
                : (string) ($cfg['claude_model'] ?? '');
            return [
                'source'             => 'own',
                'provider'           => $providerType,
                'api_key'            => $ownKey,
                'model'              => $ownModel,
                'global_provider_id' => null,
                'display_name'       => 'Tu key (' . ucfirst($providerType) . ')',
            ];
        }

        // 2) Global asignado al tenant
        if (!empty($cfg['global_ai_provider_id'])) {
            $g = GlobalAiProvider::findById((int) $cfg['global_ai_provider_id']);
            if ($g && !empty($g['is_active'])) {
                return [
                    'source'             => 'global',
                    'provider'           => (string) $g['provider'],
                    'api_key'            => (string) $g['api_key'],
                    'model'              => (string) $g['model'],
                    'global_provider_id' => (int) $g['id'],
                    'display_name'       => (string) $g['name'],
                ];
            }
        }

        // 3) Default global del SaaS
        $g = GlobalAiProvider::findDefault();
        if ($g) {
            return [
                'source'             => 'global',
                'provider'           => (string) $g['provider'],
                'api_key'            => (string) $g['api_key'],
                'model'              => (string) $g['model'],
                'global_provider_id' => (int) $g['id'],
                'display_name'       => (string) $g['name'] . ' (default)',
            ];
        }

        return null;
    }

    /**
     * Reinicia el contador de tokens si entramos en un nuevo mes calendario.
     */
    private function ensurePeriod(): void
    {
        $cfg = $this->tenantConfig();
        $start = $cfg['ai_token_period_starts_at'] ?? null;
        $now = date('Y-m-01');
        if ($start !== $now) {
            Database::run(
                "UPDATE tenants SET ai_token_period_starts_at = :s, ai_tokens_used_period = 0 WHERE id = :id",
                ['s' => $now, 'id' => $this->tenantId]
            );
        }
    }

    /**
     * Suma tokens consumidos al periodo. Solo trackeamos si la respuesta usa
     * el provider GLOBAL (los tenants con su propia key pagan a su cuenta).
     */
    private function recordTokens(string $source, int $tokensIn, int $tokensOut): void
    {
        if ($source !== 'global') return;
        $total = max(0, $tokensIn) + max(0, $tokensOut);
        if ($total === 0) return;
        Database::run(
            "UPDATE tenants SET ai_tokens_used_period = ai_tokens_used_period + :t WHERE id = :id",
            ['t' => $total, 'id' => $this->tenantId]
        );
    }

    /**
     * Verifica si al tenant le quedan tokens disponibles (solo si usa global).
     */
    public function hasTokensAvailable(): bool
    {
        $cfg = $this->tenantConfig();
        $resolved = $this->resolve();
        if (!$resolved || $resolved['source'] !== 'global') return true; // tenant con key propia, sin limite del SaaS

        $quota = $cfg['ai_token_quota'] !== null ? (int) $cfg['ai_token_quota'] : null;
        if ($quota === null || $quota <= 0) return true; // sin cuota = ilimitado
        $used = (int) ($cfg['ai_tokens_used_period'] ?? 0);
        return $used < $quota;
    }

    /** Resumen util para mostrar al usuario en el dashboard. */
    public function tokenSummary(): array
    {
        $cfg = $this->tenantConfig();
        $resolved = $this->resolve();
        $quota = $cfg['ai_token_quota'] !== null ? (int) $cfg['ai_token_quota'] : null;
        $used  = (int) ($cfg['ai_tokens_used_period'] ?? 0);
        return [
            'source'       => $resolved['source'] ?? null,
            'display_name' => $resolved['display_name'] ?? null,
            'provider'     => $resolved['provider'] ?? null,
            'model'        => $resolved['model'] ?? null,
            'quota'        => $quota,
            'used'         => $used,
            'available'    => $quota === null || $quota <= 0 ? null : max(0, $quota - $used),
            'pct'          => $quota && $quota > 0 ? min(100, (int) round($used / $quota * 100)) : 0,
            'period_start' => $cfg['ai_token_period_starts_at'] ?? null,
        ];
    }

    public function provider(): string
    {
        $r = $this->resolve();
        return $r['provider'] ?? 'claude';
    }

    public function agent(): ?array
    {
        if ($this->agentId !== null) {
            $row = Database::fetch(
                "SELECT * FROM ai_agents WHERE id = :id AND tenant_id = :t",
                ['id' => $this->agentId, 't' => $this->tenantId]
            );
            if ($row) return $row;
        }
        try {
            return AiAgent::findDefault($this->tenantId);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Construye el prompt de sistema con marca, tono, rol del agente y
     * base de conocimiento. Tambien inyecta el "manual de acciones" para
     * que la IA pueda ejecutar handoff/cierre/agenda con marcadores.
     */
    public function buildSystemPrompt(string $feature, bool $allowActions = true): string
    {
        $cfg   = $this->tenantConfig();
        $agent = $this->agent();

        $brand     = $cfg['name'] ?? 'la empresa';
        $assistant = $agent['name'] ?? $cfg['ai_assistant_name'] ?? 'Asistente IA';
        $tone      = $agent['tone'] ?? $cfg['ai_tone'] ?? 'profesional, cercano y claro';
        $lang      = $cfg['language'] ?? 'es';
        $role      = trim((string) ($agent['role'] ?? ''));
        $objective = trim((string) ($agent['objective'] ?? ''));
        $instructions = trim((string) ($agent['instructions'] ?? ''));

        $kb = Database::fetchAll(
            "SELECT category, title, content FROM knowledge_base
             WHERE tenant_id = :t AND is_active = 1
             ORDER BY sort_order ASC LIMIT 20",
            ['t' => $this->tenantId]
        );
        $kbText = '';
        foreach ($kb as $entry) {
            $kbText .= "\n[" . $entry['category'] . '] ' . $entry['title'] . "\n" . $entry['content'] . "\n";
        }

        $actionsBlock = '';
        if ($allowActions) {
            $actionsBlock = <<<ACTIONS

ACCIONES ESPECIALES (al final de tu respuesta, en una linea aparte, puedes incluir uno o mas marcadores entre corchetes; el sistema los ejecuta y los ELIMINA antes de enviar tu mensaje al cliente):
- [TRANSFER] - Pide transferir a un agente humano. Usalo si el cliente pide hablar con humano, hay queja delicada o tu no puedes resolver.
- [CLOSE_SALE: monto, producto] - Marca que se concreto una venta (ej. [CLOSE_SALE: 1500, Plan Pro]).
- [SCHEDULE: YYYY-MM-DD HH:MM, motivo] - Agenda una cita o seguimiento (ej. [SCHEDULE: 2026-05-10 15:00, demo]).
- [END_CHAT: motivo] - Cierra la conversacion porque el caso fue resuelto (ej. [END_CHAT: pedido confirmado]).
- [TAG: nombre] - Aplica una etiqueta al contacto (ej. [TAG: cliente_premium]).
- [TICKET: titulo, prioridad] - Crea un ticket de soporte (prioridad: low, medium, high, critical).

NUNCA muestres los marcadores en la respuesta visible. Siempre escribe primero tu mensaje al cliente y luego en linea aparte el/los marcadores si aplican.
ACTIONS;
        }

        $productsBlock = $this->buildProductsBlock();

        return <<<PROMPT
Eres "$assistant", representante de $brand. Hablas DIRECTAMENTE con un cliente por WhatsApp.

REGLAS DE FORMATO (CRITICO - violarlas hace que el cliente reciba basura):
1. Responde SOLO con el texto del mensaje, sin etiquetas ni encabezados.
2. NUNCA antepongas tu nombre, tu rol o cualquier prefijo como "$assistant:", "$role:", "Agente:", "Asistente:", "Cliente:", "IA:" ni similares. Empieza directamente con el saludo o la respuesta.
3. NO uses formato tipo guion teatral. NO escribas "Yo:" ni "Tu:". Solo el mensaje natural.
4. NO digas "Como modelo de IA..." ni reveles que eres una IA. Eres parte del equipo de $brand.
5. Maximo 1-3 parrafos cortos, estilo conversacional WhatsApp, sin Markdown salvo *negritas* puntuales.

CONTEXTO COMERCIAL:
- Marca: $brand
- Tono: $tone
- Idioma: $lang
- Tu rol interno (NO lo menciones literalmente): $role
- Tu objetivo: $objective

REGLAS DE NEGOCIO:
- Usa la base de conocimiento y catalogo para responder con precisas. Si no sabes algo, no inventes: emite [TRANSFER].
- Si el cliente quiere humano, agente, asesor, persona real: emite [TRANSFER].
- Sé breve y orientado a accion. Cierra cada mensaje con UNA pregunta o paso siguiente concreto cuando sea posible.
- Cuando puedas cerrar venta, agendar o resolver, hazlo proactivamente con los marcadores.
- Adapta saludo a la hora local del cliente.

Tarea actual: $feature

Instrucciones especificas del agente:
$instructions
$actionsBlock

Base de conocimiento de $brand:
$kbText
$productsBlock
PROMPT;
    }

    private function buildProductsBlock(): string
    {
        try {
            $rows = Database::fetchAll(
                "SELECT name, sku, price, currency, stock, description
                 FROM products
                 WHERE tenant_id = :t AND is_active = 1
                 ORDER BY priority DESC, name ASC
                 LIMIT 30",
                ['t' => $this->tenantId]
            );
        } catch (\Throwable) {
            return '';
        }
        if (empty($rows)) return '';

        $out = "\nCATALOGO DE PRODUCTOS/SERVICIOS DISPONIBLES (precios en la moneda indicada):\n";
        foreach ($rows as $p) {
            $price = (float) ($p['price'] ?? 0);
            $cur   = (string) ($p['currency'] ?? 'USD');
            $stock = $p['stock'] !== null ? " | stock:" . (int) $p['stock'] : '';
            $desc  = trim((string) ($p['description'] ?? ''));
            $sku   = !empty($p['sku']) ? " [SKU:" . $p['sku'] . "]" : '';
            $out  .= sprintf("- %s%s — %s %.2f%s%s\n",
                $p['name'], $sku, $cur, $price, $stock,
                $desc !== '' ? " — " . mb_substr($desc, 0, 140) : ''
            );
        }
        return $out;
    }

    /**
     * Llama al proveedor IA resuelto. $userInput puede ser string o array
     * de mensajes [{role, content}]. Devuelve un shape uniforme.
     */
    public function call(string $feature, string|array $userInput, int $maxTokens = 1024, bool $allowActions = true): array
    {
        $resolved = $this->resolve();
        if (!$resolved) {
            return [
                'success' => false,
                'error'   => 'No hay IA configurada. Agrega tu API key en Integraciones o pide al administrador que asigne un proveedor IA global.',
            ];
        }

        // Verificar cuota si usa global
        $this->ensurePeriod();
        if ($resolved['source'] === 'global' && !$this->hasTokensAvailable()) {
            return [
                'success' => false,
                'error'   => 'Tokens IA del periodo agotados. Contacta al administrador para ampliar tu cuota o agrega tu propia API key.',
                'quota_exceeded' => true,
            ];
        }

        $system = $this->buildSystemPrompt($feature, $allowActions);
        $providerType = $resolved['provider'];
        $apiKey       = $resolved['api_key'];
        $model        = $resolved['model'] !== '' ? $resolved['model'] : ($providerType === 'openai' ? 'gpt-4o-mini' : 'claude-sonnet-4-6');

        if ($providerType === 'openai') {
            $result = $this->callOpenAi($apiKey, $model, $system, $userInput, $maxTokens);
            $modelUsed = $model;
        } else {
            $result = $this->callClaude($apiKey, $this->normalizeClaudeModel($model), $system, $userInput, $maxTokens);
            $modelUsed = $this->normalizeClaudeModel($model);
        }

        // Trackear tokens contra la cuota del tenant si usa global
        $usage = $result['usage'] ?? [];
        $this->recordTokens(
            $resolved['source'],
            (int) ($usage['input_tokens']  ?? 0),
            (int) ($usage['output_tokens'] ?? 0)
        );

        $this->logCall($feature, $userInput, $result, $modelUsed, $providerType, $resolved['source']);
        return $result;
    }

    private function callOpenAi(string $apiKey, string $model, string $system, string|array $userInput, int $maxTokens): array
    {
        $messages = [['role' => 'system', 'content' => $system]];
        if (is_array($userInput)) {
            foreach ($userInput as $m) {
                if (is_array($m) && isset($m['role'], $m['content'])) {
                    $messages[] = ['role' => (string) $m['role'], 'content' => (string) $m['content']];
                }
            }
        } else {
            $messages[] = ['role' => 'user', 'content' => $userInput];
        }

        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'max_tokens'  => $maxTokens,
            'temperature' => 0.6,
        ];

        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ];
        $org = (string) config('services.openai.organization', '');
        if ($org !== '') $headers['OpenAI-Organization'] = $org;

        $resp = HttpClient::post(
            (string) config('services.openai.api_url', 'https://api.openai.com/v1/chat/completions'),
            $payload, $headers,
            (int) config('services.openai.timeout', 60)
        );

        $output = '';
        if (!empty($resp['body']['choices']) && is_array($resp['body']['choices'])) {
            foreach ($resp['body']['choices'] as $c) {
                if (isset($c['message']['content']) && is_string($c['message']['content'])) {
                    $output .= $c['message']['content'];
                }
            }
        }
        $usage = $resp['body']['usage'] ?? [];

        return [
            'success' => $resp['success'],
            'text'    => trim($output),
            'usage'   => [
                'input_tokens'  => $usage['prompt_tokens']     ?? null,
                'output_tokens' => $usage['completion_tokens'] ?? null,
            ],
            'error'   => $resp['success'] ? null : ($resp['body']['error']['message'] ?? $resp['error'] ?? 'Error OpenAI.'),
            'raw'     => $resp,
        ];
    }

    private function callClaude(string $apiKey, string $model, string $system, string|array $userInput, int $maxTokens): array
    {
        $messages = is_array($userInput) ? $userInput : [['role' => 'user', 'content' => $userInput]];
        $payload = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'system'     => $system,
            'messages'   => $messages,
        ];

        $resp = HttpClient::post(
            (string) config('services.claude.api_url'),
            $payload,
            [
                'x-api-key'         => $apiKey,
                'anthropic-version' => (string) config('services.claude.version', '2023-06-01'),
                'Content-Type'      => 'application/json',
            ],
            (int) config('services.claude.timeout', 60)
        );

        $output = '';
        if (!empty($resp['body']['content']) && is_array($resp['body']['content'])) {
            foreach ($resp['body']['content'] as $block) {
                if (($block['type'] ?? '') === 'text') $output .= ($block['text'] ?? '');
            }
        }
        $usage = $resp['body']['usage'] ?? [];

        return [
            'success' => $resp['success'],
            'text'    => trim($output),
            'usage'   => [
                'input_tokens'  => $usage['input_tokens']  ?? null,
                'output_tokens' => $usage['output_tokens'] ?? null,
            ],
            'error'   => $resp['success'] ? null : ($resp['body']['error']['message'] ?? $resp['error'] ?? 'Error Claude.'),
            'raw'     => $resp,
        ];
    }

    private function normalizeClaudeModel(string $candidate): string
    {
        $aliases = [
            'claude-sonnet-6'     => 'claude-sonnet-4-6',
            'claude-opus-6'       => 'claude-opus-4-7',
            'claude-haiku-6'      => 'claude-haiku-4-5-20251001',
            'claude-3-5-sonnet'   => 'claude-sonnet-4-6',
            'claude-3-opus'       => 'claude-opus-4-7',
        ];
        return $aliases[$candidate] ?? ($candidate ?: 'claude-sonnet-4-6');
    }

    public function ping(): array
    {
        return $this->call('healthcheck', 'Responde unicamente con la palabra OK.', 8, false);
    }

    public function autoReply(string $userMessage, string $history = ''): array
    {
        $messages = [];
        if ($history !== '') {
            $messages[] = ['role' => 'user', 'content' => "Historial reciente de la conversacion:\n$history"];
            $messages[] = ['role' => 'assistant', 'content' => 'Entendido. Continuo la conversacion.'];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];
        return $this->call('auto_reply', $messages, (int) config('services.claude.max_tokens', 1024));
    }

    public function summarizeConversation(string $transcript): array
    {
        return $this->call(
            'summarize_conversation',
            "Resume esta conversacion en 3-4 frases destacando intencion del cliente, pendientes y proxima accion sugerida:\n\n$transcript",
            400,
            false
        );
    }

    public function suggestReply(string $transcript): array
    {
        return $this->call(
            'suggest_reply',
            "Sugiere una respuesta breve, profesional y empatica que un agente humano pueda enviar ahora. Sin saludo si la conversacion ya esta en curso.\n\nConversacion:\n$transcript",
            512,
            false
        );
    }

    public function evaluateSentiment(string $message): array
    {
        return $this->call(
            'evaluate_sentiment',
            "Clasifica el sentimiento del siguiente mensaje como positive, neutral o negative. Responde SOLO la palabra.\n\n$message",
            10,
            false
        );
    }

    public function detectIntent(string $message): array
    {
        return $this->call(
            'detect_intent',
            "Analiza este mensaje y responde SOLO en JSON valido con: {\"intent\":\"...\",\"sentiment\":\"positive|neutral|negative\",\"urgency\":\"low|normal|high\"}.\n\nMensaje: $message",
            300,
            false
        );
    }

    public function scoreLead(string $contactInfo, string $conversationSummary = ''): array
    {
        return $this->call(
            'score_lead',
            "Califica este lead del 1 al 100 segun probabilidad de cierre. Responde SOLO en JSON: {\"score\":<int>,\"reason\":\"...\",\"next_action\":\"...\"}.\n\nDatos: $contactInfo\n\nResumen: $conversationSummary",
            300,
            false
        );
    }

    public function recommendNextAction(string $context): array
    {
        return $this->call(
            'recommend_next_action',
            "Recomienda la mejor proxima accion comercial para este lead. Responde en una frase corta accionable.\n\nContexto: $context",
            150,
            false
        );
    }

    public function generateCampaignMessage(string $audience, string $goal): array
    {
        return $this->call(
            'generate_campaign',
            "Crea un mensaje de WhatsApp comercial breve (max 350 caracteres) para esta audiencia: $audience. Objetivo: $goal. Incluye una llamada a la accion clara.",
            400,
            false
        );
    }


    private function logCall(string $feature, mixed $prompt, array $result, string $model, string $provider, string $source = 'own'): void
    {
        try {
            $usage = $result['usage'] ?? [];
            $tag = $provider . ($source === 'global' ? ':global' : '');
            Database::insert('ai_logs', [
                'tenant_id'     => $this->tenantId,
                'feature'       => $feature . ":$tag",
                'model'         => $model,
                'prompt'        => is_string($prompt) ? mb_substr($prompt, 0, 4000) : (json_encode($prompt, JSON_UNESCAPED_UNICODE) ?: ''),
                'response'      => mb_substr((string) ($result['text'] ?? ''), 0, 4000),
                'tokens_input'  => $usage['input_tokens']  ?? null,
                'tokens_output' => $usage['output_tokens'] ?? null,
                'success'       => !empty($result['success']) ? 1 : 0,
                'error_message' => $result['error'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Logger::error('AI log fail', ['msg' => $e->getMessage()]);
        }
    }
}
