<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\ChannelRoutingRule;
use App\Models\Conversation;

/**
 * Aplica reglas de routing y auto-asignacion cuando llega una conversacion
 * desde un canal de WhatsApp/email/etc.
 *
 * Diseñado para ejecutarse despues de que el webhook crea/actualiza la conversacion.
 */
final class RoutingEngine
{
    public function __construct(private int $tenantId) {}

    /**
     * Aplica la primera regla que matche al contexto dado.
     *
     * @param array $ctx ['conversation_id', 'channel_id', 'channel', 'message', 'contact_id']
     * @return array result con metadata de lo aplicado
     */
    public function apply(array $ctx): array
    {
        $convId   = (int) ($ctx['conversation_id'] ?? 0);
        $channelId = isset($ctx['channel_id']) ? (int) $ctx['channel_id'] : null;
        if ($convId <= 0) return ['matched' => false, 'reason' => 'sin conversacion'];

        // Si ya esta asignada, no la pisamos.
        $current = Database::fetch(
            "SELECT assigned_to, ai_agent_id FROM conversations WHERE id = :id AND tenant_id = :t",
            ['id' => $convId, 't' => $this->tenantId]
        );
        $alreadyAssigned = !empty($current['assigned_to']) || !empty($current['ai_agent_id']);

        $rules = ChannelRoutingRule::activeForChannel($this->tenantId, $channelId);
        if (empty($rules)) return ['matched' => false, 'reason' => 'sin reglas'];

        foreach ($rules as $rule) {
            if (!$this->matches($rule, $ctx)) continue;

            $applied = $this->applyRule($rule, $convId, $channelId, $alreadyAssigned);
            ChannelRoutingRule::bumpExecution((int) $rule['id'], $applied['user_id'] ?? null);
            return [
                'matched'  => true,
                'rule_id'  => (int) $rule['id'],
                'rule'     => $rule['name'],
                'strategy' => $rule['assign_strategy'],
                'result'   => $applied,
            ];
        }

        return ['matched' => false, 'reason' => 'no match'];
    }

    private function matches(array $rule, array $ctx): bool
    {
        $type = (string) $rule['match_type'];
        $value = (string) ($rule['match_value'] ?? '');

        return match ($type) {
            'any'           => true,
            'channel'       => $value === '' || (string) ($ctx['channel_id'] ?? '') === $value,
            'keyword'       => $this->matchesKeywords((string) ($ctx['message'] ?? ''), $value),
            'time'          => $this->matchesTime($rule),
            'language'      => $this->matchesLanguage((string) ($ctx['message'] ?? ''), $value),
            'contact_tag'   => $this->matchesContactTag((int) ($ctx['contact_id'] ?? 0), $value),
            'contact_score' => $this->matchesScore((int) ($ctx['contact_id'] ?? 0), (int) $value),
            default         => false,
        };
    }

    private function matchesKeywords(string $msg, string $value): bool
    {
        if ($value === '') return false;
        $msg = mb_strtolower($msg);
        foreach (preg_split('/[,;\n]+/', $value) ?: [] as $kw) {
            $kw = trim(mb_strtolower($kw));
            if ($kw !== '' && str_contains($msg, $kw)) return true;
        }
        return false;
    }

    private function matchesTime(array $rule): bool
    {
        $hours = $rule['business_hours'] ? json_decode((string) $rule['business_hours'], true) : null;
        if (!is_array($hours)) return true;

        $dayKey = strtolower(date('l'));
        $cfg = $hours[$dayKey] ?? null;
        if (!is_array($cfg) || empty($cfg['enabled'])) {
            return !empty($hours['out_of_hours_match']); // matchea fuera de horario solo si la regla lo pide
        }

        $now = date('H:i');
        $start = (string) ($cfg['start'] ?? '09:00');
        $end   = (string) ($cfg['end']   ?? '18:00');
        $inHours = strcmp($now, $start) >= 0 && strcmp($now, $end) <= 0;
        return !empty($hours['out_of_hours_match']) ? !$inHours : $inHours;
    }

    private function matchesLanguage(string $msg, string $expectedLang): bool
    {
        if ($expectedLang === '') return true;
        // Heuristica simple: presencia de caracteres no-latinos / palabras tipicas
        $expectedLang = strtolower($expectedLang);
        if ($expectedLang === 'en') {
            return preg_match('/\b(the|hello|how|please|thanks|i need|i want)\b/i', $msg) > 0;
        }
        if ($expectedLang === 'es') {
            return preg_match('/\b(hola|gracias|por favor|necesito|quiero|cuanto|como)\b/iu', $msg) > 0;
        }
        if ($expectedLang === 'pt') {
            return preg_match('/\b(ola|obrigado|preciso|quero|por favor)\b/iu', $msg) > 0;
        }
        return true;
    }

    private function matchesContactTag(int $contactId, string $tagSlug): bool
    {
        if ($contactId <= 0 || $tagSlug === '') return false;
        $row = Database::fetch(
            "SELECT 1 FROM contact_tags ct
             INNER JOIN tags t ON t.id = ct.tag_id
             WHERE ct.contact_id = :c AND ct.tenant_id = :t AND t.name = :n LIMIT 1",
            ['c' => $contactId, 't' => $this->tenantId, 'n' => $tagSlug]
        );
        return $row !== null;
    }

    private function matchesScore(int $contactId, int $minScore): bool
    {
        if ($contactId <= 0) return false;
        $score = (int) Database::fetchColumn(
            "SELECT score FROM contacts WHERE id = :c AND tenant_id = :t",
            ['c' => $contactId, 't' => $this->tenantId]
        );
        return $score >= $minScore;
    }

    private function applyRule(array $rule, int $convId, ?int $channelId, bool $alreadyAssigned): array
    {
        $strategy = (string) $rule['assign_strategy'];
        $update = [];
        $userId = null;
        $aiAgentId = null;

        if (!$alreadyAssigned) {
            switch ($strategy) {
                case 'specific_user':
                    if (!empty($rule['assign_user_id'])) {
                        $userId = (int) $rule['assign_user_id'];
                    }
                    break;

                case 'round_robin':
                    $userId = $this->roundRobinAgent((int) $rule['id'], (int) ($rule['last_assigned_user_id'] ?? 0), $rule['assign_role']);
                    break;

                case 'least_busy':
                    $userId = $this->leastBusyAgent($rule['assign_role']);
                    break;

                case 'team':
                    if (!empty($rule['assign_role'])) {
                        $userId = $this->roundRobinAgent((int) $rule['id'], (int) ($rule['last_assigned_user_id'] ?? 0), (string) $rule['assign_role']);
                    }
                    break;

                case 'ai_agent':
                    if (!empty($rule['assign_ai_agent_id'])) {
                        $aiAgentId = (int) $rule['assign_ai_agent_id'];
                        $update['ai_agent_id'] = $aiAgentId;
                        $update['ai_takeover'] = !empty($rule['auto_reply_enabled']) ? 1 : 0;
                    }
                    break;

                case 'keep':
                default:
                    // No asignar, solo aplicar tags/priority
                    break;
            }

            if ($userId) {
                $update['assigned_to'] = $userId;
                Database::insert('conversation_assignments', [
                    'tenant_id'       => $this->tenantId,
                    'conversation_id' => $convId,
                    'from_user_id'    => null,
                    'to_user_id'      => $userId,
                    'action'          => 'assigned',
                    'note'            => 'Auto-asignado por regla: ' . ($rule['name'] ?? 'sin nombre'),
                ]);
            }
        }

        // Tag automatico
        if (!empty($rule['auto_tag'])) {
            $this->applyAutoTag($convId, (string) $rule['auto_tag']);
        }

        // Priority
        if (!empty($rule['auto_priority'])) {
            $update['priority'] = (string) $rule['auto_priority'];
        }

        if (!empty($update)) {
            Conversation::update($this->tenantId, $convId, $update);
        }

        return ['user_id' => $userId, 'ai_agent_id' => $aiAgentId, 'updates' => $update];
    }

    private function roundRobinAgent(int $ruleId, int $lastUserId, ?string $role = null): ?int
    {
        $where = 'u.tenant_id = :t AND u.is_active = 1 AND u.deleted_at IS NULL';
        $params = ['t' => $this->tenantId];
        if ($role) {
            $where .= ' AND r.slug = :role';
            $params['role'] = $role;
        }

        $sql = "SELECT u.id FROM users u
                LEFT JOIN user_roles ur ON ur.user_id = u.id AND ur.tenant_id = :t
                LEFT JOIN roles r ON r.id = ur.role_id
                WHERE $where
                GROUP BY u.id
                ORDER BY u.id ASC";

        $rows = Database::fetchAll($sql, $params);
        if (empty($rows)) return null;
        $ids = array_map(fn ($r) => (int) $r['id'], $rows);
        $idx = $lastUserId > 0 ? array_search($lastUserId, $ids) : -1;
        $next = $idx === false || $idx === -1 ? 0 : ($idx + 1) % count($ids);
        return $ids[$next];
    }

    private function leastBusyAgent(?string $role = null): ?int
    {
        $where = 'u.tenant_id = :t AND u.is_active = 1 AND u.deleted_at IS NULL';
        $params = ['t' => $this->tenantId];
        if ($role) {
            $where .= ' AND r.slug = :role';
            $params['role'] = $role;
        }

        $sql = "SELECT u.id, COUNT(c.id) AS open_count
                FROM users u
                LEFT JOIN user_roles ur ON ur.user_id = u.id AND ur.tenant_id = :t
                LEFT JOIN roles r ON r.id = ur.role_id
                LEFT JOIN conversations c ON c.assigned_to = u.id
                    AND c.status NOT IN ('closed','resolved') AND c.tenant_id = :t
                WHERE $where
                GROUP BY u.id
                ORDER BY open_count ASC, u.id ASC
                LIMIT 1";

        $row = Database::fetch($sql, $params);
        return $row ? (int) $row['id'] : null;
    }

    private function applyAutoTag(int $convId, string $tagName): void
    {
        try {
            $conv = Database::fetch(
                "SELECT contact_id FROM conversations WHERE id = :id AND tenant_id = :t",
                ['id' => $convId, 't' => $this->tenantId]
            );
            if (!$conv) return;

            $tagId = (int) Database::fetchColumn(
                "SELECT id FROM tags WHERE tenant_id = :t AND name = :n",
                ['t' => $this->tenantId, 'n' => $tagName]
            );
            if (!$tagId) {
                $tagId = Database::insert('tags', [
                    'tenant_id' => $this->tenantId,
                    'name'      => $tagName,
                    'color'     => '#7C3AED',
                ]);
            }
            Database::run(
                "INSERT IGNORE INTO contact_tags (contact_id, tag_id, tenant_id) VALUES (:c, :tag, :t)",
                ['c' => (int) $conv['contact_id'], 'tag' => $tagId, 't' => $this->tenantId]
            );
        } catch (\Throwable $e) {
            Logger::error('Auto-tag fallo', ['msg' => $e->getMessage()]);
        }
    }
}
