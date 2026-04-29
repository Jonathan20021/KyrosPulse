<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class Conversation extends Model
{
    protected static string $table = 'conversations';

    public static function findOpenByContact(int $tenantId, int $contactId): ?array
    {
        return Database::fetch(
            "SELECT * FROM conversations
             WHERE tenant_id = :t AND contact_id = :c AND status NOT IN ('resolved','closed')
             ORDER BY updated_at DESC LIMIT 1",
            ['t' => $tenantId, 'c' => $contactId]
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('conversations', $data);
    }

    public static function touch(int $id, string $lastMessage): void
    {
        Database::run(
            "UPDATE conversations
             SET last_message = :m, last_message_at = NOW(),
                 unread_count = unread_count + 1,
                 status = CASE WHEN status IN ('resolved','closed') THEN 'open' ELSE status END
             WHERE id = :id",
            ['m' => mb_substr($lastMessage, 0, 200), 'id' => $id]
        );
    }

    public static function listOpen(int $tenantId, int $limit = 50): array
    {
        return Database::fetchAll(
            "SELECT c.*, ct.first_name, ct.last_name, ct.phone, ct.whatsapp, ct.email
             FROM conversations c
             INNER JOIN contacts ct ON ct.id = c.contact_id
             WHERE c.tenant_id = :t AND c.status NOT IN ('closed')
             ORDER BY c.last_message_at DESC
             LIMIT $limit",
            ['t' => $tenantId]
        );
    }

    public static function countByStatus(int $tenantId, string $status): int
    {
        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM conversations WHERE tenant_id = :t AND status = :s",
            ['t' => $tenantId, 's' => $status]
        );
    }

    public static function findById(int $tenantId, int $id): ?array
    {
        return Database::fetch(
            "SELECT c.*, ct.first_name, ct.last_name, ct.phone, ct.whatsapp, ct.email, ct.company, ct.score,
                    u.first_name AS agent_first, u.last_name AS agent_last
             FROM conversations c
             INNER JOIN contacts ct ON ct.id = c.contact_id
             LEFT JOIN users u ON u.id = c.assigned_to
             WHERE c.id = :id AND c.tenant_id = :t",
            ['id' => $id, 't' => $tenantId]
        );
    }

    public static function listFiltered(int $tenantId, array $filters, int $limit = 50): array
    {
        $where  = ['c.tenant_id = :t'];
        $params = ['t' => $tenantId];

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = 'c.status = :st';
            $params['st'] = $filters['status'];
        } elseif (empty($filters['status'])) {
            $where[] = "c.status NOT IN ('closed')";
        }
        if (!empty($filters['agent']))   { $where[] = 'c.assigned_to = :ag'; $params['ag'] = (int) $filters['agent']; }
        if (!empty($filters['channel'])) { $where[] = 'c.channel = :ch';    $params['ch'] = $filters['channel']; }
        if (!empty($filters['q'])) {
            $where[] = '(ct.first_name LIKE :q OR ct.last_name LIKE :q OR ct.phone LIKE :q OR c.last_message LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $sql = "SELECT c.*, ct.first_name, ct.last_name, ct.phone, ct.whatsapp, ct.email,
                       u.first_name AS agent_first, u.last_name AS agent_last
                FROM conversations c
                INNER JOIN contacts ct ON ct.id = c.contact_id
                LEFT JOIN users u ON u.id = c.assigned_to
                WHERE " . implode(' AND ', $where) . "
                ORDER BY c.last_message_at DESC, c.id DESC
                LIMIT $limit";

        return Database::fetchAll($sql, $params);
    }

    public static function update(int $tenantId, int $id, array $data): int
    {
        return Database::update('conversations', $data, ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function markRead(int $tenantId, int $id): void
    {
        self::update($tenantId, $id, ['unread_count' => 0]);
    }

    public static function close(int $tenantId, int $id, string $reason = ''): void
    {
        self::update($tenantId, $id, [
            'status' => 'closed',
            'closed_at' => date('Y-m-d H:i:s'),
            'closed_reason' => $reason ?: null,
        ]);
    }
}
