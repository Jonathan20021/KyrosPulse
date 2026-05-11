<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Plantillas builtin de agentes IA para el wizard de creacion no-tecnica.
 *
 * Cada template define:
 *  - prompt con placeholders {{key}} que se rellenan con respuestas del usuario
 *  - defaults tecnicos (keywords, prioridad, canales, etc.)
 *  - lista de "questions" en lenguaje plano para mostrar en el wizard
 *
 * Las plantillas viven en DB para que mas adelante un super-admin pueda
 * editarlas desde un panel sin redeploy.
 */
final class AiAgentTemplate
{
    public static function listActive(): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM ai_agent_templates
             WHERE is_active = 1
             ORDER BY display_order ASC, name ASC"
        );
        foreach ($rows as &$r) {
            $r = self::decode($r);
        }
        return $rows;
    }

    public static function findBySlug(string $slug): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM ai_agent_templates WHERE slug = :s LIMIT 1",
            ['s' => $slug]
        );
        return $row ? self::decode($row) : null;
    }

    public static function find(int $id): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM ai_agent_templates WHERE id = :i LIMIT 1",
            ['i' => $id]
        );
        return $row ? self::decode($row) : null;
    }

    public static function create(array $data): int
    {
        $data = self::encode($data);
        return Database::insert('ai_agent_templates', $data);
    }

    public static function update(int $id, array $data): int
    {
        $data = self::encode($data);
        return Database::update('ai_agent_templates', $data, ['id' => $id]);
    }

    public static function delete(int $id): int
    {
        return Database::delete('ai_agent_templates', ['id' => $id]);
    }

    /**
     * Aplica las respuestas del usuario al template y produce un array listo
     * para AiAgent::create(). Resuelve los {{placeholders}} en el prompt y
     * mezcla los defaults tecnicos del template.
     *
     * @param array $template fila de ai_agent_templates (decoded)
     * @param array $answers  ['key' => 'respuesta', ...]
     * @return array          payload para AiAgent::create() (faltan tenant_id, name)
     */
    public static function buildAgentPayload(array $template, array $answers): array
    {
        $prompt = (string) ($template['instructions_template'] ?? '');
        foreach ($answers as $key => $val) {
            $prompt = str_replace('{{' . $key . '}}', (string) $val, $prompt);
        }
        // Limpia placeholders sobrantes que el usuario no contesto
        $prompt = preg_replace('/\{\{[a-z0-9_]+\}\}/i', '—', $prompt);

        return [
            'category'           => (string) ($template['category'] ?? 'generic'),
            'role'               => (string) ($template['default_role'] ?? ''),
            'objective'          => (string) ($template['default_objective'] ?? ''),
            'instructions'       => $prompt,
            'tone'               => (string) ($template['default_tone'] ?? ''),
            'avatar_emoji'       => (string) ($template['default_avatar_emoji'] ?? $template['icon'] ?? '🤖'),
            'priority'           => (int) ($template['default_priority'] ?? 100),
            'max_retries'        => (int) ($template['default_max_retries'] ?? 3),
            'trigger_keywords'   => json_encode($template['default_trigger_keywords']  ?? [], JSON_UNESCAPED_UNICODE),
            'transfer_keywords'  => json_encode($template['default_transfer_keywords'] ?? [], JSON_UNESCAPED_UNICODE),
            'channels'           => json_encode($template['default_channels']          ?? [], JSON_UNESCAPED_UNICODE),
        ];
    }

    private static function decode(array $row): array
    {
        foreach (['default_trigger_keywords','default_transfer_keywords','default_channels','questions'] as $jsonField) {
            if (isset($row[$jsonField]) && is_string($row[$jsonField])) {
                $decoded = json_decode($row[$jsonField], true);
                $row[$jsonField] = is_array($decoded) ? $decoded : [];
            }
        }
        return $row;
    }

    private static function encode(array $data): array
    {
        foreach (['default_trigger_keywords','default_transfer_keywords','default_channels','questions'] as $jsonField) {
            if (isset($data[$jsonField]) && is_array($data[$jsonField])) {
                $data[$jsonField] = json_encode($data[$jsonField], JSON_UNESCAPED_UNICODE);
            }
        }
        return $data;
    }
}
