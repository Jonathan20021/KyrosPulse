<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class AiAgent
{
    public static function listForTenant(int $tenantId): array
    {
        return Database::fetchAll(
            "SELECT * FROM ai_agents WHERE tenant_id = :t ORDER BY is_default DESC, created_at DESC",
            ['t' => $tenantId]
        );
    }

    public static function findDefault(int $tenantId): ?array
    {
        return Database::fetch(
            "SELECT * FROM ai_agents
             WHERE tenant_id = :t AND status = 'active'
             ORDER BY is_default DESC, id ASC
             LIMIT 1",
            ['t' => $tenantId]
        );
    }

    public static function create(array $data): int
    {
        $data['uuid'] = $data['uuid'] ?? uuid4();
        if (!empty($data['is_default'])) {
            Database::update('ai_agents', ['is_default' => 0], ['tenant_id' => (int) $data['tenant_id']]);
        }

        return Database::insert('ai_agents', $data);
    }

    public static function update(int $tenantId, int $id, array $data): int
    {
        if (!empty($data['is_default'])) {
            Database::update('ai_agents', ['is_default' => 0], ['tenant_id' => $tenantId]);
        }

        return Database::update('ai_agents', $data, ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function delete(int $tenantId, int $id): int
    {
        return Database::delete('ai_agents', ['id' => $id, 'tenant_id' => $tenantId]);
    }
}
