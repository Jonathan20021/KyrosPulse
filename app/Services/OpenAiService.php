<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

/**
 * Wrapper minimo de la API de OpenAI Chat Completions.
 * Implementa la misma interfaz publica que ClaudeService::call() para que
 * AiProviderService pueda enrutar de forma transparente.
 */
final class OpenAiService
{
    public function __construct(private int $tenantId) {}

    private function tenantConfig(): array
    {
        $row = Database::fetch(
            "SELECT name, ai_assistant_name, ai_tone, ai_enabled, openai_api_key, openai_model,
                    business_hours, out_of_hours_msg, welcome_message, language
             FROM tenants WHERE id = :id",
            ['id' => $this->tenantId]
        );
        return is_array($row) ? $row : [];
    }

    public function apiKey(): string
    {
        $cfg = $this->tenantConfig();
        if (!empty($cfg['openai_api_key'])) return (string) $cfg['openai_api_key'];
        return (string) config('services.openai.api_key', '');
    }

    public function model(): string
    {
        $cfg = $this->tenantConfig();
        if (!empty($cfg['openai_model'])) return (string) $cfg['openai_model'];
        return (string) config('services.openai.model', 'gpt-5-mini');
    }

    /**
     * @param string|array $userMessage Texto plano o array de mensajes con role/content.
     */
    public function call(string $feature, string|array $userMessage, string $systemPrompt = '', int $maxTokens = 1024): array
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            return ['success' => false, 'error' => 'No hay API key de OpenAI configurada.'];
        }

        $messages = [];
        if ($systemPrompt !== '') {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        if (is_array($userMessage)) {
            foreach ($userMessage as $m) {
                if (is_array($m) && isset($m['role'], $m['content'])) {
                    $messages[] = ['role' => (string) $m['role'], 'content' => (string) $m['content']];
                }
            }
        } else {
            $messages[] = ['role' => 'user', 'content' => $userMessage];
        }

        $payload = [
            'model'       => $this->model(),
            'messages'    => $messages,
            'max_tokens'  => $maxTokens,
            'temperature' => 0.6,
        ];

        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ];
        $org = (string) config('services.openai.organization', '');
        if ($org !== '') {
            $headers['OpenAI-Organization'] = $org;
        }

        $resp = HttpClient::post(
            (string) config('services.openai.api_url', 'https://api.openai.com/v1/chat/completions'),
            $payload,
            $headers,
            (int) config('services.openai.timeout', 60)
        );

        $output = '';
        if (!empty($resp['body']['choices']) && is_array($resp['body']['choices'])) {
            foreach ($resp['body']['choices'] as $choice) {
                if (isset($choice['message']['content']) && is_string($choice['message']['content'])) {
                    $output .= $choice['message']['content'];
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

    /** Test rapido de la conexion: una sola llamada con prompt minimo. */
    public function ping(): array
    {
        if ($this->apiKey() === '') {
            return ['success' => false, 'error' => 'Falta API key OpenAI.'];
        }
        return $this->call('ping', 'Responde solo con la palabra OK.', 'Eres un servicio de healthcheck.', 8);
    }
}
