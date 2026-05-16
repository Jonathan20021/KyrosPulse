<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class DriverShift extends Model
{
    protected static string $table = 'driver_shifts';

    public static function openShift(int $tenantId, int $driverId, ?float $lat = null, ?float $lng = null): int
    {
        return Database::insert('driver_shifts', [
            'tenant_id'  => $tenantId,
            'driver_id'  => $driverId,
            'started_at' => date('Y-m-d H:i:s'),
            'start_lat'  => $lat,
            'start_lng'  => $lng,
        ]);
    }

    public static function closeShift(int $tenantId, int $driverId, ?float $lat = null, ?float $lng = null): ?array
    {
        $open = self::currentForDriver($driverId);
        if (!$open) return null;

        // Recalcular agregados del turno
        $agg = Database::fetch(
            "SELECT COUNT(*) AS c,
                    COALESCE(SUM(distance_km),0) AS km,
                    COALESCE(SUM(cash_collected),0) AS cash,
                    COALESCE(SUM(driver_commission),0) AS comm
             FROM deliveries
             WHERE tenant_id = :t AND driver_id = :d
               AND status = 'delivered'
               AND created_at >= :st",
            ['t' => $tenantId, 'd' => $driverId, 'st' => $open['started_at']]
        );

        Database::update('driver_shifts', [
            'ended_at'         => date('Y-m-d H:i:s'),
            'end_lat'          => $lat,
            'end_lng'          => $lng,
            'deliveries_count' => (int) ($agg['c'] ?? 0),
            'distance_km'      => (float) ($agg['km'] ?? 0),
            'cash_collected'   => (float) ($agg['cash'] ?? 0),
            'commission_earned'=> (float) ($agg['comm'] ?? 0),
        ], ['id' => (int) $open['id']]);

        return self::find((int) $open['id']);
    }

    public static function currentForDriver(int $driverId): ?array
    {
        return Database::fetch(
            "SELECT * FROM driver_shifts WHERE driver_id = :d AND ended_at IS NULL ORDER BY id DESC LIMIT 1",
            ['d' => $driverId]
        );
    }

    public static function listForDriver(int $driverId, int $limit = 20): array
    {
        $limit = (int) max(1, min(100, $limit));
        return Database::fetchAll(
            "SELECT * FROM driver_shifts WHERE driver_id = :d ORDER BY id DESC LIMIT $limit",
            ['d' => $driverId]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::fetch("SELECT * FROM driver_shifts WHERE id = :id", ['id' => $id]);
    }
}
