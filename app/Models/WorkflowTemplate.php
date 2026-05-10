<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Modelo de workflow_templates (marketplace).
 * Globales: tenant_id IS NULL. Custom del tenant: tenant_id = X.
 */
final class WorkflowTemplate
{
    public static function listAvailable(int $tenantId, ?string $category = null): array
    {
        $params = ['t' => $tenantId];
        $sql = "SELECT * FROM `workflow_templates`
                WHERE (`tenant_id` IS NULL OR `tenant_id` = :t)
                  AND `is_active` = 1";
        if ($category !== null && $category !== '') {
            $sql .= " AND `category` = :c";
            $params['c'] = $category;
        }
        $sql .= " ORDER BY `tenant_id` IS NULL DESC, `clone_count` DESC, `name` ASC";
        return Database::fetchAll($sql, $params);
    }

    public static function listCategories(int $tenantId): array
    {
        return Database::fetchAll(
            "SELECT `category`, COUNT(*) AS n
             FROM `workflow_templates`
             WHERE (`tenant_id` IS NULL OR `tenant_id` = :t) AND `is_active` = 1
             GROUP BY `category`
             ORDER BY n DESC, `category` ASC",
            ['t' => $tenantId]
        );
    }

    public static function findById(int $tenantId, int $id): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM `workflow_templates`
             WHERE `id` = :i AND (`tenant_id` IS NULL OR `tenant_id` = :t) AND `is_active` = 1
             LIMIT 1",
            ['i' => $id, 't' => $tenantId]
        );
        return $row ?: null;
    }

    public static function findBySlug(int $tenantId, string $slug): ?array
    {
        // Prefiere custom del tenant sobre global con el mismo slug
        $row = Database::fetch(
            "SELECT * FROM `workflow_templates` WHERE `slug` = :s AND `tenant_id` = :t AND `is_active` = 1 LIMIT 1",
            ['s' => $slug, 't' => $tenantId]
        );
        if ($row) return $row;
        $row = Database::fetch(
            "SELECT * FROM `workflow_templates` WHERE `slug` = :s AND `tenant_id` IS NULL AND `is_active` = 1 LIMIT 1",
            ['s' => $slug]
        );
        return $row ?: null;
    }

    public static function incrementCloneCount(int $id): void
    {
        Database::run(
            "UPDATE `workflow_templates` SET `clone_count` = `clone_count` + 1 WHERE `id` = :i",
            ['i' => $id]
        );
    }
}
