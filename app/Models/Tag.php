<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Tenant;

final class Tag
{
    public static function listForTenant(int $tenantId): array
    {
        return Database::fetchAll(
            "SELECT * FROM tags WHERE tenant_id = :t ORDER BY name ASC",
            ['t' => $tenantId]
        );
    }

    public static function findOrCreate(int $tenantId, string $name, string $color = '#7C3AED'): int
    {
        $row = Database::fetch("SELECT id FROM tags WHERE tenant_id = :t AND name = :n", ['t' => $tenantId, 'n' => $name]);
        if ($row) return (int) $row['id'];
        return Database::insert('tags', ['tenant_id' => $tenantId, 'name' => $name, 'color' => $color]);
    }

    public static function tagsOfContact(int $contactId): array
    {
        return Database::fetchAll(
            "SELECT t.* FROM tags t INNER JOIN contact_tags ct ON ct.tag_id = t.id WHERE ct.contact_id = :c",
            ['c' => $contactId]
        );
    }

    public static function attach(int $contactId, int $tagId, int $tenantId): void
    {
        Database::run(
            "INSERT IGNORE INTO contact_tags (contact_id, tag_id, tenant_id) VALUES (:c, :tg, :t)",
            ['c' => $contactId, 'tg' => $tagId, 't' => $tenantId]
        );
    }

    public static function detach(int $contactId, int $tagId): void
    {
        Database::delete('contact_tags', ['contact_id' => $contactId, 'tag_id' => $tagId]);
    }

    public static function syncContactTags(int $contactId, array $tagNames, int $tenantId): void
    {
        Database::run("DELETE FROM contact_tags WHERE contact_id = :c", ['c' => $contactId]);
        foreach (array_unique(array_filter(array_map('trim', $tagNames))) as $name) {
            $tagId = self::findOrCreate($tenantId, $name);
            self::attach($contactId, $tagId, $tenantId);
        }
    }

    public static function deleteById(int $id): void
    {
        $tenantId = Tenant::id();
        Database::run("DELETE FROM tags WHERE id = :id AND tenant_id = :t", ['id' => $id, 't' => $tenantId]);
    }
}
