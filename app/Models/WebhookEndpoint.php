<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Modelo de webhook_endpoints + webhook_deliveries.
 *
 * Cada endpoint se subscribe a una lista de eventos via JSON `events`:
 *   - ["*"]                           → todos los eventos del tenant
 *   - ["order.created","order.cancelled"] → solo esos
 *   - ["agent.run.*"]                 → wildcard por seccion
 *
 * El secret se genera al crear el endpoint y se muestra UNA vez (igual que
 * las API keys). Para rotarlo, el tenant debe regenerarlo manualmente.
 */
final class WebhookEndpoint
{
    public static function listForTenant(int $tenantId, bool $activeOnly = false): array
    {
        $where = $activeOnly ? "AND `is_active` = 1" : '';
        return Database::fetchAll(
            "SELECT * FROM `webhook_endpoints`
             WHERE `tenant_id` = :t $where
             ORDER BY `is_active` DESC, `created_at` DESC",
            ['t' => $tenantId]
        );
    }

    /** Endpoints activos de un tenant que estan suscritos a $event. */
    public static function listSubscribed(int $tenantId, string $event): array
    {
        // Filtrado lo hace el dispatcher en PHP por simplicidad y por wildcards.
        $rows = Database::fetchAll(
            "SELECT * FROM `webhook_endpoints`
             WHERE `tenant_id` = :t AND `is_active` = 1",
            ['t' => $tenantId]
        );

        $matched = [];
        foreach ($rows as $row) {
            $events = $row['events'] ? json_decode((string) $row['events'], true) : ['*'];
            if (!is_array($events)) $events = ['*'];
            if (self::matchesEvent($events, $event)) {
                $matched[] = $row;
            }
        }
        return $matched;
    }

    public static function findByIdForTenant(int $tenantId, int $id): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM `webhook_endpoints` WHERE `id` = :i AND `tenant_id` = :t LIMIT 1",
            ['i' => $id, 't' => $tenantId]
        );
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        return Database::insert('webhook_endpoints', $data);
    }

    public static function update(int $tenantId, int $id, array $data): int
    {
        return Database::update('webhook_endpoints', $data, ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function delete(int $tenantId, int $id): int
    {
        return Database::delete('webhook_endpoints', ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function recordDeliveryResult(int $endpointId, bool $success, ?int $statusCode = null, ?string $error = null): void
    {
        if ($success) {
            Database::run(
                "UPDATE `webhook_endpoints`
                 SET `success_count` = `success_count` + 1,
                     `last_delivery_at` = NOW(),
                     `last_status` = :s,
                     `last_error` = NULL
                 WHERE `id` = :i",
                ['s' => $statusCode, 'i' => $endpointId]
            );
        } else {
            Database::run(
                "UPDATE `webhook_endpoints`
                 SET `failure_count` = `failure_count` + 1,
                     `last_delivery_at` = NOW(),
                     `last_status` = :s,
                     `last_error` = :e
                 WHERE `id` = :i",
                ['s' => $statusCode, 'e' => $error ? mb_substr($error, 0, 500) : null, 'i' => $endpointId]
            );
        }
    }

    /** Lista deliveries recientes de un endpoint (para audit log en UI). */
    public static function deliveriesForEndpoint(int $tenantId, int $endpointId, int $limit = 50): array
    {
        return Database::fetchAll(
            "SELECT * FROM `webhook_deliveries`
             WHERE `tenant_id` = :t AND `endpoint_id` = :e
             ORDER BY `id` DESC
             LIMIT $limit",
            ['t' => $tenantId, 'e' => $endpointId]
        );
    }

    public static function deliveriesForTenant(int $tenantId, int $limit = 50): array
    {
        return Database::fetchAll(
            "SELECT d.*, e.name AS endpoint_name, e.url AS endpoint_url
             FROM `webhook_deliveries` d
             LEFT JOIN `webhook_endpoints` e ON e.id = d.endpoint_id
             WHERE d.tenant_id = :t
             ORDER BY d.id DESC
             LIMIT $limit",
            ['t' => $tenantId]
        );
    }

    public static function findDeliveryByUuid(int $tenantId, string $uuid): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM `webhook_deliveries` WHERE `tenant_id` = :t AND `uuid` = :u LIMIT 1",
            ['t' => $tenantId, 'u' => $uuid]
        );
        return $row ?: null;
    }

    /**
     * Match de evento contra lista de patrones.
     *   ["*"]             → todo
     *   ["order.created"] → solo exacto
     *   ["agent.run.*"]   → prefijo "agent.run."
     */
    public static function matchesEvent(array $patterns, string $event): bool
    {
        foreach ($patterns as $p) {
            $p = (string) $p;
            if ($p === '' || $p === '*') return true;
            if ($p === $event) return true;
            if (str_ends_with($p, '.*')) {
                $prefix = substr($p, 0, -1); // "agent.run."
                if (str_starts_with($event, $prefix)) return true;
            }
        }
        return false;
    }
}
