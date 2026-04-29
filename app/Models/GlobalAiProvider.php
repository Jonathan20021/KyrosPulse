<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class GlobalAiProvider
{
    public static function listAll(): array
    {
        return Database::fetchAll(
            "SELECT * FROM global_ai_providers ORDER BY is_default DESC, priority ASC, id DESC"
        );
    }

    public static function listActive(): array
    {
        return Database::fetchAll(
            "SELECT * FROM global_ai_providers WHERE is_active = 1 ORDER BY is_default DESC, priority ASC"
        );
    }

    public static function findById(int $id): ?array
    {
        return Database::fetch(
            "SELECT * FROM global_ai_providers WHERE id = :id",
            ['id' => $id]
        );
    }

    public static function findDefault(): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM global_ai_providers WHERE is_active = 1 AND is_default = 1 LIMIT 1"
        );
        if ($row) return $row;
        return Database::fetch(
            "SELECT * FROM global_ai_providers WHERE is_active = 1 ORDER BY priority ASC LIMIT 1"
        );
    }

    public static function create(array $data): int
    {
        if (!empty($data['is_default'])) {
            Database::run("UPDATE global_ai_providers SET is_default = 0");
        }
        return Database::insert('global_ai_providers', $data);
    }

    public static function update(int $id, array $data): int
    {
        if (!empty($data['is_default'])) {
            Database::run("UPDATE global_ai_providers SET is_default = 0 WHERE id != :id", ['id' => $id]);
        }
        return Database::update('global_ai_providers', $data, ['id' => $id]);
    }

    public static function delete(int $id): int
    {
        // Limpia las asignaciones de tenants antes de borrar para no romper integridad
        Database::run(
            "UPDATE tenants SET global_ai_provider_id = NULL WHERE global_ai_provider_id = :id",
            ['id' => $id]
        );
        return Database::delete('global_ai_providers', ['id' => $id]);
    }

    /**
     * Suma los tokens consumidos por TODOS los tenants que usan este provider
     * en el periodo (mes actual). Util para el dashboard del super admin.
     */
    public static function tokenUsageThisMonth(int $providerId): array
    {
        $row = Database::fetch(
            "SELECT COALESCE(SUM(t.ai_tokens_used_period), 0) AS used
             FROM tenants t
             WHERE t.global_ai_provider_id = :id",
            ['id' => $providerId]
        );
        return ['used' => (int) ($row['used'] ?? 0)];
    }
}
