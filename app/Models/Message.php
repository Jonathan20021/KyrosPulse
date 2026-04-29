<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class Message extends Model
{
    protected static string $table = 'messages';

    public static function create(array $data): int
    {
        return Database::insert('messages', $data);
    }

    public static function listByConversation(int $tenantId, int $conversationId, int $limit = 200): array
    {
        return Database::fetchAll(
            "SELECT m.*, u.first_name, u.last_name
             FROM messages m
             LEFT JOIN users u ON u.id = m.user_id
             WHERE m.tenant_id = :t AND m.conversation_id = :c
             ORDER BY m.created_at ASC
             LIMIT $limit",
            ['t' => $tenantId, 'c' => $conversationId]
        );
    }

    public static function countLast24h(int $tenantId): int
    {
        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM messages WHERE tenant_id = :t AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)",
            ['t' => $tenantId]
        );
    }
}
