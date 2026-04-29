<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Product
{
    public static function listForTenant(int $tenantId, ?bool $activeOnly = null): array
    {
        $where = ['tenant_id = :t', 'deleted_at IS NULL'];
        $params = ['t' => $tenantId];
        if ($activeOnly !== null) {
            $where[] = 'is_active = :a';
            $params['a'] = $activeOnly ? 1 : 0;
        }
        return Database::fetchAll(
            "SELECT * FROM products WHERE " . implode(' AND ', $where) . " ORDER BY priority DESC, name ASC",
            $params
        );
    }

    public static function findById(int $tenantId, int $id): ?array
    {
        return Database::fetch(
            "SELECT * FROM products WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL",
            ['id' => $id, 't' => $tenantId]
        );
    }

    public static function create(array $data): int
    {
        $data['uuid'] = $data['uuid'] ?? uuid4();
        return Database::insert('products', $data);
    }

    public static function update(int $tenantId, int $id, array $data): int
    {
        return Database::update('products', $data, ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function softDelete(int $tenantId, int $id): int
    {
        return Database::update('products', ['deleted_at' => date('Y-m-d H:i:s')], ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function countActive(int $tenantId): int
    {
        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM products WHERE tenant_id = :t AND is_active = 1 AND deleted_at IS NULL",
            ['t' => $tenantId]
        );
    }
}
