<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Ticket
{
    public static function generateCode(): string
    {
        return 'TKT-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
    }

    public static function create(array $data): int
    {
        if (empty($data['code'])) $data['code'] = self::generateCode();
        return Database::insert('tickets', $data);
    }

    public static function findById(int $tenantId, int $id): ?array
    {
        return Database::fetch(
            "SELECT tk.*, c.first_name, c.last_name, c.email AS contact_email, c.phone AS contact_phone,
                    u.first_name AS agent_first, u.last_name AS agent_last
             FROM tickets tk
             LEFT JOIN contacts c ON c.id = tk.contact_id
             LEFT JOIN users u ON u.id = tk.assigned_to
             WHERE tk.id = :id AND tk.tenant_id = :t AND tk.deleted_at IS NULL",
            ['id' => $id, 't' => $tenantId]
        );
    }

    public static function listFiltered(int $tenantId, array $filters, int $limit = 25, int $offset = 0): array
    {
        $where  = ['tk.tenant_id = :t', 'tk.deleted_at IS NULL'];
        $params = ['t' => $tenantId];

        if (!empty($filters['status']))   { $where[] = 'tk.status = :st';      $params['st'] = $filters['status']; }
        if (!empty($filters['priority'])) { $where[] = 'tk.priority = :pr';    $params['pr'] = $filters['priority']; }
        if (!empty($filters['agent']))    { $where[] = 'tk.assigned_to = :ag'; $params['ag'] = (int) $filters['agent']; }
        if (!empty($filters['q']))        { $where[] = '(tk.subject LIKE :q OR tk.code LIKE :q)'; $params['q'] = '%' . $filters['q'] . '%'; }

        $sql = "SELECT tk.*, c.first_name, c.last_name,
                       u.first_name AS agent_first, u.last_name AS agent_last
                FROM tickets tk
                LEFT JOIN contacts c ON c.id = tk.contact_id
                LEFT JOIN users u ON u.id = tk.assigned_to
                WHERE " . implode(' AND ', $where) . "
                ORDER BY
                    FIELD(tk.priority, 'critical','high','medium','low'),
                    tk.created_at DESC
                LIMIT $limit OFFSET $offset";

        return Database::fetchAll($sql, $params);
    }

    public static function count(int $tenantId, array $filters): int
    {
        $where  = ['tenant_id = :t', 'deleted_at IS NULL'];
        $params = ['t' => $tenantId];
        if (!empty($filters['status']))   { $where[] = 'status = :st';      $params['st'] = $filters['status']; }
        if (!empty($filters['priority'])) { $where[] = 'priority = :pr';    $params['pr'] = $filters['priority']; }
        if (!empty($filters['agent']))    { $where[] = 'assigned_to = :ag'; $params['ag'] = (int) $filters['agent']; }
        $sql = "SELECT COUNT(*) FROM tickets WHERE " . implode(' AND ', $where);
        return (int) Database::fetchColumn($sql, $params);
    }

    public static function update(int $tenantId, int $id, array $data): int
    {
        return Database::update('tickets', $data, ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function softDelete(int $tenantId, int $id): int
    {
        return self::update($tenantId, $id, ['deleted_at' => date('Y-m-d H:i:s')]);
    }

    public static function comments(int $tenantId, int $ticketId): array
    {
        return Database::fetchAll(
            "SELECT tc.*, u.first_name, u.last_name
             FROM ticket_comments tc
             LEFT JOIN users u ON u.id = tc.user_id
             WHERE tc.ticket_id = :id AND tc.tenant_id = :t
             ORDER BY tc.created_at ASC",
            ['id' => $ticketId, 't' => $tenantId]
        );
    }

    public static function addComment(int $tenantId, int $ticketId, int $userId, string $body, bool $internal = false): int
    {
        return Database::insert('ticket_comments', [
            'tenant_id'   => $tenantId,
            'ticket_id'   => $ticketId,
            'user_id'     => $userId,
            'body'        => $body,
            'is_internal' => $internal ? 1 : 0,
        ]);
    }

    public static function setStatus(int $tenantId, int $id, string $status): void
    {
        $extra = [];
        if ($status === 'resolved') $extra['resolved_at'] = date('Y-m-d H:i:s');
        if ($status === 'closed')   $extra['closed_at']   = date('Y-m-d H:i:s');
        self::update($tenantId, $id, array_merge(['status' => $status], $extra));
    }

    /** Calcula SLA basado en prioridad (en horas) */
    public static function slaHoursFor(string $priority): int
    {
        return match ($priority) {
            'critical' => 2,
            'high'     => 8,
            'medium'   => 24,
            'low'      => 72,
            default    => 24,
        };
    }
}
