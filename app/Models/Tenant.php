<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Tenant
{
    public static function findById(int $id): ?array
    {
        return Database::fetch(
            "SELECT * FROM tenants WHERE id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::fetch(
            "SELECT * FROM tenants WHERE slug = :s AND deleted_at IS NULL",
            ['s' => $slug]
        );
    }

    public static function slugExists(string $slug): bool
    {
        $c = Database::fetchColumn(
            "SELECT COUNT(*) FROM tenants WHERE slug = :s AND deleted_at IS NULL",
            ['s' => $slug]
        );
        return ((int) $c) > 0;
    }

    public static function emailExists(string $email): bool
    {
        $c = Database::fetchColumn(
            "SELECT COUNT(*) FROM tenants WHERE email = :e AND deleted_at IS NULL",
            ['e' => strtolower(trim($email))]
        );
        return ((int) $c) > 0;
    }

    public static function generateUniqueSlug(string $base): string
    {
        $slug = slugify($base);
        $candidate = $slug;
        $i = 1;
        while (self::slugExists($candidate)) {
            $candidate = $slug . '-' . (++$i);
        }
        return $candidate;
    }

    public static function create(array $data): int
    {
        $data['uuid'] = $data['uuid'] ?? uuid4();
        return Database::insert('tenants', $data);
    }

    public static function listAll(): array
    {
        return Database::fetchAll(
            "SELECT t.*, p.name AS plan_name
             FROM tenants t
             LEFT JOIN plans p ON p.id = t.plan_id
             WHERE t.deleted_at IS NULL
             ORDER BY t.created_at DESC"
        );
    }
}
