<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Modelo de agent_skills + agent_skill_links.
 *
 * Skill = capacidad componible (ventas, soporte, cobranza, agendamiento, etc).
 * Las skills GLOBALES (tenant_id NULL) son del sistema y todos los tenants
 * pueden enlazarlas. Las skills CUSTOM (tenant_id != NULL) son privadas del
 * tenant que las creo.
 *
 * agent_skill_links es el pivote agente <-> skill con priority + config override.
 */
final class AgentSkill
{
    /**
     * Skills disponibles para el tenant (globales + propias). Excluye inactivas.
     */
    public static function listAvailable(int $tenantId): array
    {
        return Database::fetchAll(
            "SELECT * FROM `agent_skills`
             WHERE (`tenant_id` IS NULL OR `tenant_id` = :t)
               AND `is_active` = 1
             ORDER BY `tenant_id` IS NULL DESC, `name` ASC",
            ['t' => $tenantId]
        );
    }

    public static function findById(int $tenantId, int $id): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM `agent_skills`
             WHERE `id` = :i AND (`tenant_id` IS NULL OR `tenant_id` = :t)
             LIMIT 1",
            ['i' => $id, 't' => $tenantId]
        );
        return $row ?: null;
    }

    public static function findBySlug(int $tenantId, string $slug): ?array
    {
        // Prefiere custom del tenant sobre global con mismo slug
        $row = Database::fetch(
            "SELECT * FROM `agent_skills`
             WHERE `slug` = :s AND `tenant_id` = :t
             LIMIT 1",
            ['s' => $slug, 't' => $tenantId]
        );
        if ($row) return $row;

        $row = Database::fetch(
            "SELECT * FROM `agent_skills`
             WHERE `slug` = :s AND `tenant_id` IS NULL
             LIMIT 1",
            ['s' => $slug]
        );
        return $row ?: null;
    }

    public static function createCustom(int $tenantId, array $data): int
    {
        $data['tenant_id'] = $tenantId;
        $data['is_active'] = $data['is_active'] ?? 1;
        return Database::insert('agent_skills', $data);
    }

    public static function updateCustom(int $tenantId, int $id, array $data): int
    {
        return Database::update('agent_skills', $data, ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function deleteCustom(int $tenantId, int $id): int
    {
        // Solo se pueden borrar skills custom (tenant_id != NULL).
        $count = Database::delete('agent_skills', ['id' => $id, 'tenant_id' => $tenantId]);
        if ($count > 0) {
            Database::run("DELETE FROM `agent_skill_links` WHERE `skill_id` = :i", ['i' => $id]);
        }
        return $count;
    }

    /**
     * Skills enlazadas a un agente, con info de la skill y override del link.
     * Ordenadas por priority (menor primero) y nombre.
     */
    public static function listForAgent(int $tenantId, int $agentId, bool $onlyActive = true): array
    {
        $where = $onlyActive ? "AND l.`is_active` = 1 AND s.`is_active` = 1" : '';
        return Database::fetchAll(
            "SELECT s.*,
                    l.`id` AS link_id,
                    l.`priority` AS link_priority,
                    l.`is_active` AS link_active,
                    l.`config` AS link_config
             FROM `agent_skill_links` l
             INNER JOIN `agent_skills` s ON s.`id` = l.`skill_id`
             WHERE l.`tenant_id` = :t AND l.`agent_id` = :a $where
             ORDER BY l.`priority` ASC, s.`name` ASC",
            ['t' => $tenantId, 'a' => $agentId]
        );
    }

    public static function attach(int $tenantId, int $agentId, int $skillId, int $priority = 100, ?array $config = null): int
    {
        $existing = Database::fetch(
            "SELECT id FROM `agent_skill_links` WHERE `agent_id` = :a AND `skill_id` = :s LIMIT 1",
            ['a' => $agentId, 's' => $skillId]
        );
        if ($existing) {
            Database::update('agent_skill_links', [
                'is_active' => 1,
                'priority'  => $priority,
                'config'    => $config ? json_encode($config, JSON_UNESCAPED_UNICODE) : null,
            ], ['id' => (int) $existing['id']]);
            return (int) $existing['id'];
        }

        return Database::insert('agent_skill_links', [
            'tenant_id' => $tenantId,
            'agent_id'  => $agentId,
            'skill_id'  => $skillId,
            'priority'  => $priority,
            'is_active' => 1,
            'config'    => $config ? json_encode($config, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    public static function detach(int $tenantId, int $agentId, int $skillId): int
    {
        return Database::run(
            "DELETE FROM `agent_skill_links`
             WHERE `tenant_id` = :t AND `agent_id` = :a AND `skill_id` = :s",
            ['t' => $tenantId, 'a' => $agentId, 's' => $skillId]
        )->rowCount();
    }

    public static function setActive(int $tenantId, int $agentId, int $skillId, bool $active): int
    {
        return Database::run(
            "UPDATE `agent_skill_links`
             SET `is_active` = :v
             WHERE `tenant_id` = :t AND `agent_id` = :a AND `skill_id` = :s",
            ['v' => $active ? 1 : 0, 't' => $tenantId, 'a' => $agentId, 's' => $skillId]
        )->rowCount();
    }

    public static function setPriority(int $tenantId, int $agentId, int $skillId, int $priority): int
    {
        return Database::run(
            "UPDATE `agent_skill_links`
             SET `priority` = :p
             WHERE `tenant_id` = :t AND `agent_id` = :a AND `skill_id` = :s",
            ['p' => max(0, min(999, $priority)), 't' => $tenantId, 'a' => $agentId, 's' => $skillId]
        )->rowCount();
    }
}
