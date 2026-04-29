<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Role
{
    public static function findBySlug(string $slug): ?array
    {
        return Database::fetch("SELECT * FROM roles WHERE slug = :s", ['s' => $slug]);
    }

    public static function all(): array
    {
        return Database::fetchAll("SELECT * FROM roles ORDER BY id ASC");
    }
}
