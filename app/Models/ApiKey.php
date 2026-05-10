<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Modelo de api_keys: lookup, listado, revocacion y stats por tenant.
 * La generacion del secreto y su hash viven en App\Services\ApiKeyService.
 */
final class ApiKey
{
    public static function findActiveByHash(string $keyHash): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM `api_keys`
             WHERE `key_hash` = :h
             AND `revoked_at` IS NULL
             AND (`expires_at` IS NULL OR `expires_at` > NOW())
             LIMIT 1",
            ['h' => $keyHash]
        );
        return $row ?: null;
    }

    public static function findByPrefix(string $prefix, int $tenantId): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM `api_keys`
             WHERE `tenant_id` = :t AND `prefix` = :p
             LIMIT 1",
            ['t' => $tenantId, 'p' => $prefix]
        );
        return $row ?: null;
    }

    public static function findByIdForTenant(int $tenantId, int $id): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM `api_keys` WHERE `id` = :i AND `tenant_id` = :t LIMIT 1",
            ['i' => $id, 't' => $tenantId]
        );
        return $row ?: null;
    }

    public static function listForTenant(int $tenantId): array
    {
        return Database::fetchAll(
            "SELECT * FROM `api_keys`
             WHERE `tenant_id` = :t
             ORDER BY `revoked_at` IS NULL DESC, `created_at` DESC",
            ['t' => $tenantId]
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('api_keys', $data);
    }

    public static function revoke(int $tenantId, int $id): int
    {
        return Database::run(
            "UPDATE `api_keys`
             SET `revoked_at` = NOW()
             WHERE `id` = :i AND `tenant_id` = :t AND `revoked_at` IS NULL",
            ['i' => $id, 't' => $tenantId]
        )->rowCount();
    }

    public static function rename(int $tenantId, int $id, string $name): int
    {
        return Database::run(
            "UPDATE `api_keys` SET `name` = :n WHERE `id` = :i AND `tenant_id` = :t",
            ['n' => $name, 'i' => $id, 't' => $tenantId]
        )->rowCount();
    }

    public static function touchUsage(int $id, string $ip): void
    {
        Database::run(
            "UPDATE `api_keys` SET `last_used_at` = NOW(), `last_used_ip` = :ip WHERE `id` = :i",
            ['i' => $id, 'ip' => $ip]
        );
    }

    public static function statsForTenant(int $tenantId, int $days = 7): array
    {
        $row = Database::fetch(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status_code >= 200 AND status_code < 300 THEN 1 ELSE 0 END) AS ok,
                SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) AS errors,
                AVG(latency_ms) AS avg_latency
             FROM `api_request_logs`
             WHERE `tenant_id` = :t AND `created_at` >= DATE_SUB(NOW(), INTERVAL :d DAY)",
            ['t' => $tenantId, 'd' => $days]
        );
        return [
            'total'       => (int) ($row['total'] ?? 0),
            'ok'          => (int) ($row['ok'] ?? 0),
            'errors'      => (int) ($row['errors'] ?? 0),
            'avg_latency' => (float) ($row['avg_latency'] ?? 0),
        ];
    }
}
