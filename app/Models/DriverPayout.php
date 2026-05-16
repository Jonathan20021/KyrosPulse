<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class DriverPayout extends Model
{
    protected static string $table = 'driver_payouts';

    public static function create(array $data): int
    {
        return Database::insert('driver_payouts', $data);
    }

    public static function listForTenant(int $tenantId, array $filters = []): array
    {
        $where  = ['p.tenant_id = :t'];
        $params = ['t' => $tenantId];
        if (!empty($filters['driver_id'])) {
            $where[] = 'p.driver_id = :d';
            $params['d'] = (int) $filters['driver_id'];
        }
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = 'p.status = :s';
            $params['s'] = $filters['status'];
        }
        return Database::fetchAll(
            "SELECT p.*, d.name AS driver_name, d.phone AS driver_phone
             FROM driver_payouts p
             LEFT JOIN drivers d ON d.id = p.driver_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY p.created_at DESC LIMIT 200",
            $params
        );
    }

    public static function findById(int $tenantId, int $id): ?array
    {
        return Database::fetch(
            "SELECT p.*, d.name AS driver_name, d.phone AS driver_phone
             FROM driver_payouts p
             LEFT JOIN drivers d ON d.id = p.driver_id
             WHERE p.id = :id AND p.tenant_id = :t",
            ['id' => $id, 't' => $tenantId]
        );
    }

    public static function markPaid(int $tenantId, int $id, int $userId, array $extra = []): bool
    {
        $row = self::findById($tenantId, $id);
        if (!$row || $row['status'] === 'paid') return false;
        Database::update('driver_payouts', array_merge([
            'status'        => 'paid',
            'paid_at'       => date('Y-m-d H:i:s'),
            'paid_by'       => $userId,
            'payment_method'=> $extra['payment_method'] ?? null,
            'payment_ref'   => $extra['payment_ref'] ?? null,
            'notes'         => $extra['notes'] ?? null,
        ], []), ['id' => $id, 'tenant_id' => $tenantId]);
        return true;
    }

    public static function cancel(int $tenantId, int $id): bool
    {
        $row = self::findById($tenantId, $id);
        if (!$row || $row['status'] === 'paid') return false;
        Database::update('driver_payouts', ['status' => 'cancelled'], ['id' => $id, 'tenant_id' => $tenantId]);
        return true;
    }
}
