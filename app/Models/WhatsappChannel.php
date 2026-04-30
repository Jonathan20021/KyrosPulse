<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class WhatsappChannel extends Model
{
    protected static string $table = 'whatsapp_channels';

    public const PROVIDERS = [
        'wasapi'    => ['Wasapi', '#10B981'],
        'cloud'     => ['WhatsApp Cloud API (Meta)', '#25D366'],
        'twilio'    => ['Twilio', '#F22F46'],
        'dialog360' => ['360dialog', '#0EA5E9'],
        'custom'    => ['HTTP custom', '#7C3AED'],
    ];

    public static function listForTenant(int $tenantId, bool $onlyActive = false): array
    {
        $where = 'tenant_id = :t AND deleted_at IS NULL';
        if ($onlyActive) $where .= " AND status = 'active'";
        return Database::fetchAll(
            "SELECT * FROM whatsapp_channels WHERE $where ORDER BY is_default DESC, id ASC",
            ['t' => $tenantId]
        );
    }

    public static function findById(int $tenantId, int $id): ?array
    {
        return Database::fetch(
            "SELECT * FROM whatsapp_channels WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL",
            ['id' => $id, 't' => $tenantId]
        );
    }

    public static function findByUuid(string $uuid): ?array
    {
        return Database::fetch(
            "SELECT * FROM whatsapp_channels WHERE uuid = :u AND deleted_at IS NULL",
            ['u' => $uuid]
        );
    }

    public static function findDefault(int $tenantId): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM whatsapp_channels
             WHERE tenant_id = :t AND deleted_at IS NULL AND status = 'active'
             ORDER BY is_default DESC, id ASC LIMIT 1",
            ['t' => $tenantId]
        );
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $data['uuid'] = $data['uuid'] ?? self::uuid();
        return Database::insert('whatsapp_channels', $data);
    }

    public static function update(int $tenantId, int $id, array $data): int
    {
        return Database::update('whatsapp_channels', $data, ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function softDelete(int $tenantId, int $id): int
    {
        return self::update($tenantId, $id, ['deleted_at' => date('Y-m-d H:i:s'), 'status' => 'disabled']);
    }

    public static function setDefault(int $tenantId, int $id): void
    {
        Database::run(
            "UPDATE whatsapp_channels SET is_default = 0 WHERE tenant_id = :t",
            ['t' => $tenantId]
        );
        self::update($tenantId, $id, ['is_default' => 1]);
    }

    public static function touchActivity(int $channelId): void
    {
        Database::run(
            "UPDATE whatsapp_channels
             SET last_message_at = NOW(),
                 messages_today = messages_today + 1
             WHERE id = :id",
            ['id' => $channelId]
        );
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
