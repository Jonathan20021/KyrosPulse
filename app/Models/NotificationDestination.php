<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Destinos de notificacion configurables por tenant.
 *
 * Un destino representa "donde mandar" una notificacion: un email, un canal de
 * Slack via webhook, un chat de Telegram, etc. Cada destino se suscribe a uno
 * o mas eventos (ej. ['order.ready', 'order.delivered']).
 */
final class NotificationDestination
{
    public const TYPES = ['email','slack','discord','teams','telegram','webhook','whatsapp'];

    public const ENTITY_ORDER  = 'order';
    public const ENTITY_TICKET = 'ticket';
    public const ENTITY_LEAD   = 'lead';

    /** Eventos disponibles agrupados por entidad. La UI los expone para suscribirse. */
    public static function availableEvents(): array
    {
        return [
            'order' => [
                'order.new'              => 'Nueva orden recibida',
                'order.confirmed'        => 'Orden confirmada',
                'order.preparing'        => 'Orden en preparacion',
                'order.ready'            => 'Orden lista',
                'order.out_for_delivery' => 'Orden en camino',
                'order.delivered'        => 'Orden entregada / completada',
                'order.cancelled'        => 'Orden cancelada',
            ],
            'ticket' => [
                'ticket.new'      => 'Ticket nuevo',
                'ticket.resolved' => 'Ticket resuelto',
            ],
            'lead' => [
                'lead.new' => 'Lead nuevo',
                'lead.won' => 'Lead ganado',
            ],
            'ai' => [
                'ai.budget_alert' => 'Alerta de presupuesto IA (umbral cruzado)',
            ],
        ];
    }

    public static function listForTenant(int $tenantId, ?string $entity = null): array
    {
        $sql = "SELECT * FROM notification_destinations WHERE tenant_id = :t AND deleted_at IS NULL";
        $params = ['t' => $tenantId];
        if ($entity) {
            $sql .= " AND entity = :e";
            $params['e'] = $entity;
        }
        $sql .= " ORDER BY id DESC";
        $rows = Database::fetchAll($sql, $params);
        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function find(int $tenantId, int $id): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM notification_destinations WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL",
            ['id' => $id, 't' => $tenantId]
        );
        return $row ? self::hydrate($row) : null;
    }

    public static function create(int $tenantId, array $data): int
    {
        return Database::insert('notification_destinations', [
            'tenant_id'  => $tenantId,
            'type'       => self::sanitizeType($data['type'] ?? 'email'),
            'label'      => mb_substr((string) ($data['label'] ?? 'Destino'), 0, 120),
            'config'     => json_encode($data['config'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'events'     => json_encode(array_values($data['events'] ?? []), JSON_UNESCAPED_UNICODE),
            'entity'     => mb_substr((string) ($data['entity'] ?? 'order'), 0, 40),
            'is_active'  => !empty($data['is_active']) ? 1 : 0,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    public static function update(int $tenantId, int $id, array $data): int
    {
        $update = [];
        if (isset($data['type']))      $update['type']      = self::sanitizeType($data['type']);
        if (isset($data['label']))     $update['label']     = mb_substr((string) $data['label'], 0, 120);
        if (isset($data['config']))    $update['config']    = json_encode($data['config'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (isset($data['events']))    $update['events']    = json_encode(array_values($data['events']), JSON_UNESCAPED_UNICODE);
        if (isset($data['entity']))    $update['entity']    = mb_substr((string) $data['entity'], 0, 40);
        if (isset($data['is_active'])) $update['is_active'] = !empty($data['is_active']) ? 1 : 0;
        if (empty($update)) return 0;
        return Database::update('notification_destinations', $update, ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function softDelete(int $tenantId, int $id): int
    {
        return Database::update(
            'notification_destinations',
            ['deleted_at' => date('Y-m-d H:i:s')],
            ['id' => $id, 'tenant_id' => $tenantId]
        );
    }

    /** Devuelve los destinos activos suscritos a un evento concreto. */
    public static function activeForEvent(int $tenantId, string $event): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM notification_destinations
             WHERE tenant_id = :t AND deleted_at IS NULL AND is_active = 1
             AND JSON_SEARCH(events, 'one', :e) IS NOT NULL",
            ['t' => $tenantId, 'e' => $event]
        );
        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function recordResult(int $id, bool $success, ?string $error = null): void
    {
        $sql = $success
            ? "UPDATE notification_destinations
               SET success_count = success_count + 1, last_used_at = NOW(), last_status = 'success', last_error = NULL
               WHERE id = :id"
            : "UPDATE notification_destinations
               SET failure_count = failure_count + 1, last_used_at = NOW(), last_status = 'failed', last_error = :err
               WHERE id = :id";
        $params = ['id' => $id];
        if (!$success) $params['err'] = mb_substr((string) $error, 0, 1000);
        Database::run($sql, $params);
    }

    private static function hydrate(array $row): array
    {
        $row['config'] = is_string($row['config']) ? (json_decode($row['config'], true) ?: []) : ($row['config'] ?? []);
        $row['events'] = is_string($row['events']) ? (json_decode($row['events'], true) ?: []) : ($row['events'] ?? []);
        return $row;
    }

    private static function sanitizeType(string $type): string
    {
        return in_array($type, self::TYPES, true) ? $type : 'email';
    }
}
