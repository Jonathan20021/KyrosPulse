<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Modelo base. Las subclases definen $table y $primaryKey.
 * Aplica scope automatico de tenant cuando $tenantScoped = true.
 */
abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected static bool $tenantScoped = true;
    protected static bool $softDelete = false;

    public static function find(int $id): ?array
    {
        $where = ['id' => $id];
        if (static::$tenantScoped) {
            $where['tenant_id'] = Tenant::id();
        }
        $sql = "SELECT * FROM `" . static::$table . "` WHERE `id` = :id";
        if (static::$tenantScoped) {
            $sql .= " AND `tenant_id` = :tenant_id";
        }
        if (static::$softDelete) {
            $sql .= " AND `deleted_at` IS NULL";
        }
        return Database::fetch($sql, $where);
    }

    public static function all(array $orderBy = ['id' => 'DESC'], int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT * FROM `" . static::$table . "`";
        $params = [];
        $where = [];

        if (static::$tenantScoped) {
            $where[] = '`tenant_id` = :tenant_id';
            $params['tenant_id'] = Tenant::id();
        }
        if (static::$softDelete) {
            $where[] = '`deleted_at` IS NULL';
        }

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $orderParts = [];
        foreach ($orderBy as $col => $dir) {
            $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
            $orderParts[] = "`$col` $dir";
        }
        if ($orderParts) {
            $sql .= ' ORDER BY ' . implode(', ', $orderParts);
        }

        $sql .= " LIMIT $limit OFFSET $offset";

        return Database::fetchAll($sql, $params);
    }

    public static function count(array $where = []): int
    {
        $sql = "SELECT COUNT(*) FROM `" . static::$table . "`";
        $conditions = [];
        $params = [];

        if (static::$tenantScoped) {
            $conditions[] = '`tenant_id` = :tenant_id';
            $params['tenant_id'] = Tenant::id();
        }
        if (static::$softDelete) {
            $conditions[] = '`deleted_at` IS NULL';
        }
        foreach ($where as $col => $val) {
            $conditions[] = "`$col` = :$col";
            $params[$col] = $val;
        }

        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        return (int) Database::fetchColumn($sql, $params);
    }

    public static function create(array $data): int
    {
        if (static::$tenantScoped && !isset($data['tenant_id'])) {
            $data['tenant_id'] = Tenant::id();
        }
        return Database::insert(static::$table, $data);
    }

    public static function updateById(int $id, array $data): int
    {
        $where = ['id' => $id];
        if (static::$tenantScoped) {
            $where['tenant_id'] = Tenant::id();
        }
        return Database::update(static::$table, $data, $where);
    }

    public static function deleteById(int $id): int
    {
        if (static::$softDelete) {
            return self::updateById($id, ['deleted_at' => date('Y-m-d H:i:s')]);
        }
        $where = ['id' => $id];
        if (static::$tenantScoped) {
            $where['tenant_id'] = Tenant::id();
        }
        return Database::delete(static::$table, $where);
    }
}
