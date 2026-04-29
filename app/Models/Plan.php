<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Plan
{
    public static function findById(int $id): ?array
    {
        return Database::fetch("SELECT * FROM plans WHERE id = :id", ['id' => $id]);
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::fetch("SELECT * FROM plans WHERE slug = :s", ['s' => $slug]);
    }

    public static function listActive(): array
    {
        return Database::fetchAll(
            "SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order ASC, price_monthly ASC"
        );
    }
}
