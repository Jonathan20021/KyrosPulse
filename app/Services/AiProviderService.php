<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\AiAgent;

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
                    business_hours, out_of_hours_msg, welcome_message, language
             FROM tenants WHERE id = :id",
            ['id' => $this->tenantId]
        );
        return is_array($row) ? $row : [];
    }

    public function provider(): string
    {
        $cfg = $this->tenantConfig();
        $p = strtolower((string) ($cfg['ai_provider'] ?? 'claude'));
        return $p === 'openai' ? 'openai' : 'claude';
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

        return <<<PROMPT
Eres "$assistant", asistente IA oficial de $brand.
Tono de comunicacion: $tone.
Idioma principal: $lang.
Rol: {$role}
Objetivo: {$objective}

Reglas:
- Responde SIEMPRE como representante de $brand. No digas que eres un modelo de IA.
- Usa la base de conocimiento para responder dudas sobre productos, servicios, politicas, precios.
- Si no sabes algo, no inventes: usa [TRANSFER].
- Mantente breve, claro, util. Una pregunta o accion concreta por mensaje cuando sea posible.
- Si puedes cerrar venta, agendar o avanzar el proceso, hazlo proactivamente.
- Adapta el saludo a la hora (manana/tarde/noche) y al historial de la conversacion.

Tarea actual: $feature

Instrucciones especificas del agente:
$instructions
$actionsBlock

Base de conocimiento de $brand:
$kbText
PROMPT;
    }

    /**
     * Llama al proveedor configurado. $userInput puede ser string o array
     * de mensajes [{role, content}]. Devuelve un shape uniforme.
     */
    public function call(string $feature, string|array $userInput, int $maxTokens = 1024, bool $allowActions = true): array
    {
        $system = $this->buildSystemPrompt($feature, $allowActions);
        $provider = $this->provider();

        if ($provider === 'openai') {
            $service = new OpenAiService($this->tenantId);
            $result  = $service->call($feature, $userInput, $system, $maxTokens);
            $modelUsed = $service->model();
        } else {
            $messages = is_array($userInput) ? $userInput : [['role' => 'user', 'content' => $userInput]];
            $payload = [
                'model'      => $this->claudeModel(),
                'max_tokens' => $maxTokens,
                'system'     => $system,
                'messages'   => $messages,
            ];

            $apiKey = $this->claudeApiKey();
            if ($apiKey === '') {
                return ['success' => false, 'error' => 'No hay API key de Claude configurada.'];
            }

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
                    if (($block['type'] ?? '') === 'text') {
                        $output .= ($block['text'] ?? '');
                    }
                }
            }

            $usage = $resp['body']['usage'] ?? [];
            $result = [
                'success' => $resp['success'],
                'text'    => trim($output),
                'usage'   => [
                    'input_tokens'  => $usage['input_tokens']  ?? null,
                    'output_tokens' => $usage['output_tokens'] ?? null,
                ],
                'error'   => $resp['success'] ? null : ($resp['body']['error']['message'] ?? $resp['error'] ?? 'Error Claude.'),
                'raw'     => $resp,
            ];
            $modelUsed = $this->claudeModel();
        }

        $this->logCall($feature, $userInput, $result, $modelUsed, $provider);
        return $result;
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

    private function claudeApiKey(): string
    {
        $cfg = $this->tenantConfig();
        if (!empty($cfg['claude_api_key'])) return (string) $cfg['claude_api_key'];
        return (string) config('services.claude.api_key', '');
    }

    private function claudeModel(): string
    {
        $agent = $this->agent();
        $candidate = '';
        if (!empty($agent['model'])) $candidate = (string) $agent['model'];

        if ($candidate === '') {
            $cfg = $this->tenantConfig();
            $candidate = (string) ($cfg['claude_model'] ?? '');
        }
        if ($candidate === '') {
            $candidate = (string) config('services.claude.model', 'claude-sonnet-4-6');
        }

        // Normalizar IDs antiguos/erroneos a la familia 4.X actual.
        $aliases = [
            'claude-sonnet-6'     => 'claude-sonnet-4-6',
            'claude-opus-6'       => 'claude-opus-4-7',
            'claude-haiku-6'      => 'claude-haiku-4-5-20251001',
            'claude-3-5-sonnet'   => 'claude-sonnet-4-6',
            'claude-3-opus'       => 'claude-opus-4-7',
        ];
        return $aliases[$candidate] ?? $candidate;
    }

    private function logCall(string $feature, mixed $prompt, array $result, string $model, string $provider): void
    {
        try {
            $usage = $result['usage'] ?? [];
            Database::insert('ai_logs', [
                'tenant_id'     => $this->tenantId,
                'feature'       => $feature . ($provider !== 'claude' ? ":$provider" : ''),
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
