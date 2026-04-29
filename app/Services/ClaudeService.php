<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\AiAgent;

/**
 * Servicio de integracion con Claude (Anthropic) para tareas de IA.
 * Cada tenant puede tener configuraciones propias (modelo, tono, base de conocimiento).
 */
final class ClaudeService
{
    public function __construct(private int $tenantId) {}

    private function tenantConfig(): array
    {
        $row = Database::fetch(
            "SELECT name, ai_assistant_name, ai_tone, ai_enabled, claude_api_key, claude_model,
                    business_hours, out_of_hours_msg, welcome_message, language
             FROM tenants WHERE id = :id",
            ['id' => $this->tenantId]
        );
        return is_array($row) ? $row : [];
    }

    private function apiKey(): string
    {
        $cfg = $this->tenantConfig();
        if (!empty($cfg['claude_api_key'])) return $cfg['claude_api_key'];
        return (string) config('services.claude.api_key', '');
    }

    private function model(): string
    {
        $agent = $this->defaultAgent();
        if (!empty($agent['model'])) return (string) $agent['model'];

        $cfg = $this->tenantConfig();
        if (!empty($cfg['claude_model'])) return $cfg['claude_model'];
        return (string) config('services.claude.model', 'claude-sonnet-6');
    }

    private function defaultAgent(): ?array
    {
        try {
            return AiAgent::findDefault($this->tenantId);
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildSystemPrompt(string $feature): string
    {
        $cfg = $this->tenantConfig();
        $agent = $this->defaultAgent();
        $brand = $cfg['name'] ?? 'la empresa';
        $assistant = $agent['name'] ?? $cfg['ai_assistant_name'] ?? 'Asistente IA';
        $tone = $agent['tone'] ?? $cfg['ai_tone'] ?? 'profesional, cercano y claro';
        $lang = $cfg['language'] ?? 'es';
        $role = trim((string) ($agent['role'] ?? ''));
        $objective = trim((string) ($agent['objective'] ?? ''));
        $instructions = trim((string) ($agent['instructions'] ?? ''));

        // Cargar base de conocimiento (max 20 entradas)
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

        return <<<PROMPT
Eres "$assistant", asistente IA oficial de $brand.
Tono de comunicacion: $tone.
Idioma principal: $lang.
Rol: {$role}
Objetivo: {$objective}

Reglas:
- Responde siempre como representante de $brand.
- Usa la base de conocimiento para responder preguntas sobre productos, servicios y politicas.
- Si no sabes, no inventes. Sugiere transferir a un agente humano.
- Si el cliente pide hablar con humano, responde "Te conecto con un agente." y devuelve la marca [TRANSFER].
- Mantente breve, util y orientado a resolver.
- No digas que eres un modelo de IA. Actua como parte del equipo de $brand.
- Si puedes cerrar una venta, pedir datos o avanzar una orden, hazlo con una pregunta concreta.

Tarea actual: $feature

Instrucciones especificas del agente:
$instructions

Base de conocimiento de $brand:
$kbText
PROMPT;
    }

    public function call(string $feature, string|array $userMessage, int $maxTokens = 1024): array
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            $msg = 'No hay API key de Claude configurada.';
            $this->logCall($feature, $userMessage, '', null, null, false, $msg);
            return ['success' => false, 'error' => $msg];
        }

        $messages = is_array($userMessage)
            ? $userMessage
            : [['role' => 'user', 'content' => $userMessage]];

        $payload = [
            'model'      => $this->model(),
            'max_tokens' => $maxTokens,
            'system'     => $this->buildSystemPrompt($feature),
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
                if (($block['type'] ?? '') === 'text') {
                    $output .= ($block['text'] ?? '');
                }
            }
        }

        $usage = $resp['body']['usage'] ?? [];
        $this->logCall(
            $feature,
            $userMessage,
            $output,
            $usage['input_tokens'] ?? null,
            $usage['output_tokens'] ?? null,
            $resp['success'],
            $resp['success'] ? null : ($resp['body']['error']['message'] ?? $resp['error'] ?? null),
            $resp['duration_ms'] ?? null
        );

        return [
            'success' => $resp['success'],
            'text'    => trim($output),
            'usage'   => $usage,
            'raw'     => $resp,
        ];
    }

    public function summarizeConversation(string $transcript): array
    {
        return $this->call(
            'summarize_conversation',
            "Resume esta conversacion en 3-4 frases, en espanol, destacando intenciones, pendientes y proxima accion sugerida:\n\n$transcript"
        );
    }

    public function detectIntent(string $message): array
    {
        return $this->call(
            'detect_intent',
            "Analiza este mensaje y responde SOLO en JSON valido con: {\"intent\": \"...\", \"sentiment\": \"positive|neutral|negative\", \"urgency\": \"low|normal|high\"}.\n\nMensaje: $message",
            300
        );
    }

    public function suggestReply(string $transcript): array
    {
        return $this->call(
            'suggest_reply',
            "Sugiere una respuesta breve, profesional y empatica para que un agente la envie ahora. Conversacion:\n\n$transcript",
            512
        );
    }

    public function evaluateSentiment(string $message): array
    {
        return $this->call(
            'evaluate_sentiment',
            "Clasifica el sentimiento del siguiente mensaje como positive, neutral o negative. Responde SOLO la palabra.\n\n$message",
            10
        );
    }

    public function autoReply(string $userMessage, string $history = ''): array
    {
        $messages = [];
        if ($history !== '') {
            $messages[] = ['role' => 'user', 'content' => "Historial reciente:\n$history"];
            $messages[] = ['role' => 'assistant', 'content' => 'Entendido, continuo la conversacion.'];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        return $this->call('auto_reply', $messages, (int) config('services.claude.max_tokens', 1024));
    }

    public function scoreLead(string $contactInfo, string $conversationSummary = ''): array
    {
        return $this->call(
            'score_lead',
            "Califica este lead del 1 al 100 segun probabilidad de cierre. Responde SOLO en JSON: {\"score\": <int>, \"reason\": \"...\", \"next_action\": \"...\"}.\n\nDatos: $contactInfo\n\nResumen: $conversationSummary",
            300
        );
    }

    public function generateCampaignMessage(string $audience, string $goal): array
    {
        return $this->call(
            'generate_campaign',
            "Crea un mensaje de WhatsApp comercial breve (max 350 caracteres) para esta audiencia: $audience. Objetivo: $goal. Incluye una llamada a la accion clara."
        );
    }

    public function recommendNextAction(string $context): array
    {
        return $this->call(
            'recommend_next_action',
            "Recomienda la mejor proxima accion comercial para este lead. Responde en una frase corta accionable.\n\nContexto: $context",
            150
        );
    }

    private function logCall(
        string $feature,
        mixed $prompt,
        string $response,
        ?int $tokensIn,
        ?int $tokensOut,
        bool $success,
        ?string $error = null,
        ?int $duration = null
    ): void {
        try {
            Database::insert('ai_logs', [
                'tenant_id'     => $this->tenantId,
                'feature'       => $feature,
                'model'         => $this->model(),
                'prompt'        => is_string($prompt) ? mb_substr($prompt, 0, 4000) : json_encode($prompt, JSON_UNESCAPED_UNICODE),
                'response'      => mb_substr($response, 0, 4000),
                'tokens_input'  => $tokensIn,
                'tokens_output' => $tokensOut,
                'duration_ms'   => $duration,
                'success'       => $success ? 1 : 0,
                'error_message' => $error,
            ]);
        } catch (\Throwable $e) {
            Logger::error('No se pudo registrar AI log', ['msg' => $e->getMessage()]);
        }
    }
}
