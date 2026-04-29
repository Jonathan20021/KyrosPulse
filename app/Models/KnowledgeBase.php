<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class KnowledgeBase
{
    public static function listForTenant(int $tenantId): array
    {
        return Database::fetchAll(
            "SELECT * FROM knowledge_base WHERE tenant_id = :t ORDER BY category, sort_order ASC",
            ['t' => $tenantId]
        );
    }

    public static function findById(int $tenantId, int $id): ?array
    {
        return Database::fetch(
            "SELECT * FROM knowledge_base WHERE id = :id AND tenant_id = :t",
            ['id' => $id, 't' => $tenantId]
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('knowledge_base', $data);
    }

    public static function update(int $tenantId, int $id, array $data): int
    {
        return Database::update('knowledge_base', $data, ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function delete(int $tenantId, int $id): int
    {
        return Database::delete('knowledge_base', ['id' => $id, 'tenant_id' => $tenantId]);
    }
}
