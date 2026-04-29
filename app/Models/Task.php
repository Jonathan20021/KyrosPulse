<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Task
{
    public static function create(array $data): int
    {
        return Database::insert('tasks', $data);
    }

    public static function findById(int $tenantId, int $id): ?array
    {
        return Database::fetch(
            "SELECT t.*, u.first_name, u.last_name, c.first_name AS contact_first, c.last_name AS contact_last
             FROM tasks t
             LEFT JOIN users u ON u.id = t.assigned_to
             LEFT JOIN contacts c ON c.id = t.contact_id
             WHERE t.id = :id AND t.tenant_id = :t",
            ['id' => $id, 't' => $tenantId]
        );
    }

    public static function listFiltered(int $tenantId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where  = ['t.tenant_id = :t'];
        $params = ['t' => $tenantId];

        if (!empty($filters['status'])) {
            $where[] = 't.status = :st';
            $params['st'] = $filters['status'];
        }
        if (!empty($filters['assigned_to'])) {
            $where[] = 't.assigned_to = :ag';
            $params['ag'] = (int) $filters['assigned_to'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(t.title LIKE :q OR t.description LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['due_today'])) {
            $where[] = 'DATE(t.due_at) = CURDATE()';
        }
        if (!empty($filters['overdue'])) {
            $where[] = 't.due_at < NOW() AND t.status NOT IN (\'completed\',\'cancelled\')';
        }

        $sql = "SELECT t.*, u.first_name, u.last_name, c.first_name AS contact_first
                FROM tasks t
                LEFT JOIN users u ON u.id = t.assigned_to
                LEFT JOIN contacts c ON c.id = t.contact_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY t.due_at IS NULL, t.due_at ASC
                LIMIT $limit OFFSET $offset";

        return Database::fetchAll($sql, $params);
    }

    public static function count(int $tenantId, array $filters = []): int
    {
        $where  = ['tenant_id = :t'];
        $params = ['t' => $tenantId];
        if (!empty($filters['status']))      { $where[] = 'status = :st';      $params['st'] = $filters['status']; }
        if (!empty($filters['assigned_to'])) { $where[] = 'assigned_to = :ag'; $params['ag'] = (int) $filters['assigned_to']; }
        $sql = "SELECT COUNT(*) FROM tasks WHERE " . implode(' AND ', $where);
        return (int) Database::fetchColumn($sql, $params);
    }

    public static function update(int $tenantId, int $id, array $data): int
    {
        return Database::update('tasks', $data, ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function delete(int $tenantId, int $id): int
    {
        return Database::delete('tasks', ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function complete(int $tenantId, int $id): void
    {
        self::update($tenantId, $id, ['status' => 'completed', 'completed_at' => date('Y-m-d H:i:s')]);
    }

    /** Tareas vencidas que requieren recordatorio (utilizado por cron) */
    public static function dueReminders(int $minutesAhead = 60): array
    {
        return Database::fetchAll(
            "SELECT t.*, u.email AS user_email, u.first_name AS user_first, c.phone AS contact_phone
             FROM tasks t
             LEFT JOIN users u ON u.id = t.assigned_to
             LEFT JOIN contacts c ON c.id = t.contact_id
             WHERE t.status = 'pending'
               AND t.due_at IS NOT NULL
               AND t.due_at <= DATE_ADD(NOW(), INTERVAL $minutesAhead MINUTE)
               AND t.due_at > NOW()
               AND (t.remind_email = 1 OR t.remind_whatsapp = 1)"
        );
    }
}
