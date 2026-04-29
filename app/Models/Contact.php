<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Core\Tenant;

final class Contact extends Model
{
    protected static string $table = 'contacts';
    protected static bool $tenantScoped = true;
    protected static bool $softDelete = true;

    public static function findById(int $id): ?array
    {
        $tenantId = Tenant::id();
        if ($tenantId === null) {
            return Database::fetch("SELECT * FROM contacts WHERE id = :id AND deleted_at IS NULL", ['id' => $id]);
        }
        return Database::fetch(
            "SELECT * FROM contacts WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL",
            ['id' => $id, 't' => $tenantId]
        );
    }

    public static function findByPhone(int $tenantId, string $phone): ?array
    {
        return Database::fetch(
            "SELECT * FROM contacts WHERE tenant_id = :t AND (phone = :p OR whatsapp = :p) AND deleted_at IS NULL LIMIT 1",
            ['t' => $tenantId, 'p' => $phone]
        );
    }

    public static function createMinimal(int $tenantId, array $data): int
    {
        $data['tenant_id'] = $tenantId;
        $data['uuid'] = $data['uuid'] ?? uuid4();
        $data['status'] = $data['status'] ?? 'lead';
        return Database::insert('contacts', $data);
    }

    public static function touchInteraction(int $tenantId, int $contactId): void
    {
        Database::run(
            "UPDATE contacts SET last_interaction = NOW() WHERE id = :id AND tenant_id = :t",
            ['id' => $contactId, 't' => $tenantId]
        );
    }

    public static function search(int $tenantId, string $query, int $limit = 20): array
    {
        $like = '%' . $query . '%';
        return Database::fetchAll(
            "SELECT * FROM contacts
             WHERE tenant_id = :t AND deleted_at IS NULL
               AND (first_name LIKE :q OR last_name LIKE :q OR email LIKE :q OR phone LIKE :q OR whatsapp LIKE :q OR company LIKE :q)
             ORDER BY first_name ASC
             LIMIT $limit",
            ['t' => $tenantId, 'q' => $like]
        );
    }

    public static function fullName(array $contact): string
    {
        return trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''));
    }

    public static function listFiltered(int $tenantId, array $filters, int $limit = 25, int $offset = 0): array
    {
        $where  = ['c.tenant_id = :t', 'c.deleted_at IS NULL'];
        $params = ['t' => $tenantId];

        if (!empty($filters['q'])) {
            $where[] = '(c.first_name LIKE :q OR c.last_name LIKE :q OR c.email LIKE :q OR c.phone LIKE :q OR c.whatsapp LIKE :q OR c.company LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['status'])) { $where[] = 'c.status = :st'; $params['st'] = $filters['status']; }
        if (!empty($filters['source'])) { $where[] = 'c.source = :sr'; $params['sr'] = $filters['source']; }
        if (!empty($filters['agent']))  { $where[] = 'c.assigned_to = :ag'; $params['ag'] = (int) $filters['agent']; }
        if (!empty($filters['tag'])) {
            $where[] = 'EXISTS (SELECT 1 FROM contact_tags ct INNER JOIN tags tg ON tg.id = ct.tag_id WHERE ct.contact_id = c.id AND tg.name = :tg)';
            $params['tg'] = $filters['tag'];
        }

        $sql = "SELECT c.*, u.first_name AS agent_first, u.last_name AS agent_last
                FROM contacts c
                LEFT JOIN users u ON u.id = c.assigned_to
                WHERE " . implode(' AND ', $where) . "
                ORDER BY c.created_at DESC
                LIMIT $limit OFFSET $offset";

        return Database::fetchAll($sql, $params);
    }

    public static function countFiltered(int $tenantId, array $filters): int
    {
        $where  = ['tenant_id = :t', 'deleted_at IS NULL'];
        $params = ['t' => $tenantId];
        if (!empty($filters['q'])) {
            $where[] = '(first_name LIKE :q OR last_name LIKE :q OR email LIKE :q OR phone LIKE :q OR company LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['status'])) { $where[] = 'status = :st'; $params['st'] = $filters['status']; }
        if (!empty($filters['source'])) { $where[] = 'source = :sr'; $params['sr'] = $filters['source']; }
        if (!empty($filters['agent']))  { $where[] = 'assigned_to = :ag'; $params['ag'] = (int) $filters['agent']; }

        $sql = "SELECT COUNT(*) FROM contacts WHERE " . implode(' AND ', $where);
        return (int) Database::fetchColumn($sql, $params);
    }

    public static function update(int $tenantId, int $id, array $data): int
    {
        return Database::update('contacts', $data, ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function softDelete(int $tenantId, int $id): int
    {
        return self::update($tenantId, $id, ['deleted_at' => date('Y-m-d H:i:s')]);
    }

    public static function timeline(int $tenantId, int $contactId): array
    {
        $msgs = Database::fetchAll(
            "SELECT 'message' AS kind, id, direction, content AS body, created_at, status, type
             FROM messages WHERE tenant_id = :t AND contact_id = :c
             ORDER BY created_at DESC LIMIT 50",
            ['t' => $tenantId, 'c' => $contactId]
        );
        $tasks = Database::fetchAll(
            "SELECT 'task' AS kind, id, title AS body, status, created_at
             FROM tasks WHERE tenant_id = :t AND contact_id = :c
             ORDER BY created_at DESC LIMIT 20",
            ['t' => $tenantId, 'c' => $contactId]
        );
        $tickets = Database::fetchAll(
            "SELECT 'ticket' AS kind, id, subject AS body, status, created_at
             FROM tickets WHERE tenant_id = :t AND contact_id = :c AND deleted_at IS NULL
             ORDER BY created_at DESC LIMIT 20",
            ['t' => $tenantId, 'c' => $contactId]
        );
        $all = array_merge($msgs, $tasks, $tickets);
        usort($all, fn($a, $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));
        return array_slice($all, 0, 50);
    }
}
