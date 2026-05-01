<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class MenuCategory extends Model
{
    protected static string $table = 'menu_categories';

    public static function listForTenant(int $tenantId, bool $onlyActive = false): array
    {
        $where = 'tenant_id = :t';
        if ($onlyActive) $where .= ' AND is_active = 1';
        return Database::fetchAll(
            "SELECT * FROM menu_categories WHERE $where ORDER BY sort_order ASC, name ASC",
            ['t' => $tenantId]
        );
    }

    public static function findById(int $tenantId, int $id): ?array
    {
        return Database::fetch(
            "SELECT * FROM menu_categories WHERE id = :id AND tenant_id = :t",
            ['id' => $id, 't' => $tenantId]
        );
    }

    public static function create(array $data): int
    {
        $data['slug'] = $data['slug'] ?? self::slugify((string) ($data['name'] ?? 'cat'));
        return Database::insert('menu_categories', $data);
    }

    public static function update(int $tenantId, int $id, array $data): int
    {
        return Database::update('menu_categories', $data, ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function delete(int $tenantId, int $id): int
    {
        return (int) Database::run(
            "DELETE FROM menu_categories WHERE id = :id AND tenant_id = :t",
            ['id' => $id, 't' => $tenantId]
        )->rowCount();
    }

    private static function slugify(string $text): string
    {
        $s = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $text) ?? 'cat');
        return trim($s, '-') ?: 'cat';
    }
}
