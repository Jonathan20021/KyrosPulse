<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class DeliveryZone extends Model
{
    protected static string $table = 'delivery_zones';

    public static function listForTenant(int $tenantId, bool $activeOnly = false): array
    {
        $where = 'tenant_id = :t';
        if ($activeOnly) $where .= ' AND is_active = 1';
        return Database::fetchAll(
            "SELECT * FROM delivery_zones WHERE $where ORDER BY name ASC",
            ['t' => $tenantId]
        );
    }

    public static function findById(int $tenantId, int $id): ?array
    {
        return Database::fetch(
            "SELECT * FROM delivery_zones WHERE id = :id AND tenant_id = :t",
            ['id' => $id, 't' => $tenantId]
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('delivery_zones', $data);
    }

    public static function update(int $tenantId, int $id, array $data): int
    {
        return Database::update('delivery_zones', $data, ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function delete(int $tenantId, int $id): int
    {
        return (int) Database::run(
            "DELETE FROM delivery_zones WHERE id = :id AND tenant_id = :t",
            ['id' => $id, 't' => $tenantId]
        )->rowCount();
    }
}
