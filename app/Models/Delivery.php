<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class Delivery extends Model
{
    protected static string $table = 'deliveries';

    public const STATUSES = [
        'pending'    => ['Sin asignar',  '#94A3B8', '⏳'],
        'assigned'   => ['Asignado',     '#06B6D4', '📨'],
        'accepted'   => ['Aceptado',     '#3B82F6', '✓'],
        'picked_up'  => ['Recogido',     '#A855F7', '📦'],
        'arriving'   => ['Llegando',     '#EC4899', '🛵'],
        'delivered'  => ['Entregado',    '#22C55E', '✅'],
        'failed'     => ['Fallido',      '#EF4444', '✗'],
        'cancelled'  => ['Cancelado',    '#EF4444', '✗'],
    ];

    public const STATUS_FLOW = [
        'pending'   => ['assigned','cancelled'],
        'assigned'  => ['accepted','cancelled'],
        'accepted'  => ['picked_up','cancelled'],
        'picked_up' => ['arriving','delivered','failed'],
        'arriving'  => ['delivered','failed'],
    ];

    public static function create(array $data): int
    {
        $data['tracking_token'] = $data['tracking_token'] ?? uuid4();
        return Database::insert('deliveries', $data);
    }

    public static function update(int $tenantId, int $id, array $data): int
    {
        return Database::update('deliveries', $data, ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function findById(int $tenantId, int $id): ?array
    {
        return Database::fetch(
            "SELECT dl.*, d.name AS driver_name, d.phone AS driver_phone, d.vehicle_type, d.vehicle_plate,
                    o.code AS order_code, o.customer_name, o.customer_phone, o.total AS order_total,
                    o.currency, o.delivery_address AS order_address, o.payment_method AS order_payment_method,
                    o.payment_status AS order_payment_status
             FROM deliveries dl
             LEFT JOIN drivers d ON d.id = dl.driver_id
             LEFT JOIN orders o ON o.id = dl.order_id
             WHERE dl.id = :id AND dl.tenant_id = :t",
            ['id' => $id, 't' => $tenantId]
        );
    }

    public static function findByOrder(int $tenantId, int $orderId): ?array
    {
        return Database::fetch(
            "SELECT * FROM deliveries WHERE order_id = :o AND tenant_id = :t LIMIT 1",
            ['o' => $orderId, 't' => $tenantId]
        );
    }

    public static function findByToken(string $token): ?array
    {
        return Database::fetch(
            "SELECT dl.*, d.name AS driver_name, d.phone AS driver_phone, d.vehicle_type,
                    d.last_lat AS driver_lat, d.last_lng AS driver_lng, d.last_ping_at AS driver_ping,
                    o.code AS order_code, o.customer_name, o.total AS order_total, o.currency,
                    t.name AS tenant_name, t.phone AS tenant_phone, t.logo AS tenant_logo
             FROM deliveries dl
             LEFT JOIN drivers d ON d.id = dl.driver_id
             LEFT JOIN orders o ON o.id = dl.order_id
             LEFT JOIN tenants t ON t.id = dl.tenant_id
             WHERE dl.tracking_token = :tok LIMIT 1",
            ['tok' => $token]
        );
    }

    public static function listFiltered(int $tenantId, array $filters = [], int $limit = 100): array
    {
        $where  = ['dl.tenant_id = :t'];
        $params = ['t' => $tenantId];
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = 'dl.status = :s';
            $params['s'] = $filters['status'];
        }
        if (!empty($filters['driver_id'])) {
            $where[] = 'dl.driver_id = :did';
            $params['did'] = (int) $filters['driver_id'];
        }
        if (!empty($filters['today'])) {
            $where[] = 'DATE(dl.created_at) = CURDATE()';
        }
        if (!empty($filters['from'])) { $where[] = 'dl.created_at >= :from'; $params['from'] = $filters['from']; }
        if (!empty($filters['to']))   { $where[] = 'dl.created_at <= :to';   $params['to']   = $filters['to']; }

        return Database::fetchAll(
            "SELECT dl.*, d.name AS driver_name, d.phone AS driver_phone, d.vehicle_type,
                    o.code AS order_code, o.customer_name, o.customer_phone, o.total AS order_total,
                    o.currency, o.delivery_address AS order_address
             FROM deliveries dl
             LEFT JOIN drivers d ON d.id = dl.driver_id
             LEFT JOIN orders o ON o.id = dl.order_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY dl.created_at DESC LIMIT $limit",
            $params
        );
    }

    /**
     * Deliveries activas (no terminadas) para un driver.
     */
    public static function listActiveForDriver(int $driverId): array
    {
        return Database::fetchAll(
            "SELECT dl.*, o.code AS order_code, o.customer_name, o.customer_phone, o.total AS order_total,
                    o.currency, o.delivery_address AS order_address, o.delivery_notes,
                    o.kitchen_notes, o.payment_method AS order_payment_method,
                    o.payment_status AS order_payment_status
             FROM deliveries dl
             LEFT JOIN orders o ON o.id = dl.order_id
             WHERE dl.driver_id = :d
               AND dl.status IN ('assigned','accepted','picked_up','arriving')
             ORDER BY dl.assigned_at ASC",
            ['d' => $driverId]
        );
    }

    /**
     * Historial reciente del driver (ultimos N entregados/fallidos).
     */
    public static function listHistoryForDriver(int $driverId, int $limit = 30): array
    {
        $limit = (int) max(1, min(100, $limit));
        return Database::fetchAll(
            "SELECT dl.*, o.code AS order_code, o.customer_name, o.delivery_address AS order_address
             FROM deliveries dl
             LEFT JOIN orders o ON o.id = dl.order_id
             WHERE dl.driver_id = :d
               AND dl.status IN ('delivered','failed','cancelled')
             ORDER BY dl.created_at DESC LIMIT $limit",
            ['d' => $driverId]
        );
    }

    public static function dispatcherStats(int $tenantId): array
    {
        $pending = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM deliveries WHERE tenant_id = :t AND status = 'pending'",
            ['t' => $tenantId]
        );
        $inFlight = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM deliveries WHERE tenant_id = :t AND status IN ('assigned','accepted','picked_up','arriving')",
            ['t' => $tenantId]
        );
        $deliveredToday = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM deliveries
              WHERE tenant_id = :t AND status = 'delivered' AND DATE(delivered_at) = CURDATE()",
            ['t' => $tenantId]
        );
        $cashToday = (float) Database::fetchColumn(
            "SELECT COALESCE(SUM(cash_collected),0) FROM deliveries
              WHERE tenant_id = :t AND status = 'delivered' AND DATE(delivered_at) = CURDATE()",
            ['t' => $tenantId]
        );
        $commissionToday = (float) Database::fetchColumn(
            "SELECT COALESCE(SUM(driver_commission),0) FROM deliveries
              WHERE tenant_id = :t AND status = 'delivered' AND DATE(delivered_at) = CURDATE()",
            ['t' => $tenantId]
        );
        $driversOnline = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM drivers WHERE tenant_id = :t AND deleted_at IS NULL
              AND status IN ('online','on_delivery')",
            ['t' => $tenantId]
        );
        $avgEtaMin = (float) Database::fetchColumn(
            "SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, picked_up_at, delivered_at)),0)
             FROM deliveries
             WHERE tenant_id = :t AND status = 'delivered' AND picked_up_at IS NOT NULL
               AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            ['t' => $tenantId]
        );
        return [
            'pending'          => $pending,
            'in_flight'        => $inFlight,
            'delivered_today'  => $deliveredToday,
            'cash_today'       => $cashToday,
            'commission_today' => $commissionToday,
            'drivers_online'   => $driversOnline,
            'avg_eta_min'      => round($avgEtaMin, 1),
        ];
    }
}
