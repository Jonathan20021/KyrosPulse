<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class PipelineStage
{
    public static function listForTenant(int $tenantId): array
    {
        return Database::fetchAll(
            "SELECT * FROM pipeline_stages WHERE tenant_id = :t ORDER BY sort_order ASC",
            ['t' => $tenantId]
        );
    }

    public static function findBySlug(int $tenantId, string $slug): ?array
    {
        return Database::fetch(
            "SELECT * FROM pipeline_stages WHERE tenant_id = :t AND slug = :s",
            ['t' => $tenantId, 's' => $slug]
        );
    }

    public static function findById(int $tenantId, int $id): ?array
    {
        return Database::fetch(
            "SELECT * FROM pipeline_stages WHERE tenant_id = :t AND id = :id",
            ['t' => $tenantId, 'id' => $id]
        );
    }
}
