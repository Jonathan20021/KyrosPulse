<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Notification
{
    public static function create(int $tenantId, int $userId, array $data): int
    {
        return Database::insert('notifications', array_merge([
            'tenant_id' => $tenantId,
            'user_id'   => $userId,
            'type'      => 'info',
            'icon'      => 'bell',
        ], $data));
    }

    public static function listForUser(int $userId, int $limit = 20): array
    {
        return Database::fetchAll(
            "SELECT * FROM notifications WHERE user_id = :u ORDER BY created_at DESC LIMIT $limit",
            ['u' => $userId]
        );
    }

    public static function unreadCount(int $userId): int
    {
        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM notifications WHERE user_id = :u AND read_at IS NULL",
            ['u' => $userId]
        );
    }

    public static function markRead(int $userId, int $id): void
    {
        Database::run(
            "UPDATE notifications SET read_at = NOW() WHERE id = :id AND user_id = :u AND read_at IS NULL",
            ['id' => $id, 'u' => $userId]
        );
    }

    public static function markAllRead(int $userId): void
    {
        Database::run("UPDATE notifications SET read_at = NOW() WHERE user_id = :u AND read_at IS NULL", ['u' => $userId]);
    }
}
