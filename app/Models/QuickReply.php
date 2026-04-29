<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class QuickReply
{
    public static function listForTenant(int $tenantId): array
    {
        return Database::fetchAll(
            "SELECT * FROM quick_replies WHERE tenant_id = :t ORDER BY shortcut ASC",
            ['t' => $tenantId]
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('quick_replies', $data);
    }

    public static function delete(int $tenantId, int $id): int
    {
        return Database::delete('quick_replies', ['id' => $id, 'tenant_id' => $tenantId]);
    }
}
