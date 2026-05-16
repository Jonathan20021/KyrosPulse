<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class Driver extends Model
{
    protected static string $table = 'drivers';

    public const STATUSES = [
        'offline'      => ['Offline',     '#94A3B8', '⚫'],
        'online'       => ['Disponible',  '#22C55E', '🟢'],
        'on_delivery'  => ['En ruta',     '#F59E0B', '🛵'],
        'suspended'    => ['Suspendido',  '#EF4444', '⛔'],
    ];

    public const COMMISSION_TYPES = [
        'flat'    => 'Tarifa fija por entrega',
        'percent' => 'Porcentaje del total',
        'per_km'  => 'Por kilometro',
        'mixed'   => 'Fija + por km',
    ];

    public static function create(array $data): int
    {
        $data['uuid'] = $data['uuid'] ?? uuid4();
        if (isset($data['pin'])) {
            $data['pin_hash'] = password_hash((string) $data['pin'], PASSWORD_BCRYPT);
            unset($data['pin']);
        }
        return Database::insert('drivers', $data);
    }

    public static function update(int $tenantId, int $id, array $data): int
    {
        if (isset($data['pin']) && $data['pin'] !== '') {
            $data['pin_hash'] = password_hash((string) $data['pin'], PASSWORD_BCRYPT);
        }
        unset($data['pin']);
        return Database::update('drivers', $data, ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function findById(int $tenantId, int $id): ?array
    {
        return Database::fetch(
            "SELECT * FROM drivers WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL",
            ['id' => $id, 't' => $tenantId]
        );
    }

    public static function findByUuid(string $uuid): ?array
    {
        return Database::fetch(
            "SELECT * FROM drivers WHERE uuid = :u AND deleted_at IS NULL",
            ['u' => $uuid]
        );
    }

    public static function findByPhone(int $tenantId, string $phone): ?array
    {
        return Database::fetch(
            "SELECT * FROM drivers WHERE tenant_id = :t AND phone = :p AND deleted_at IS NULL LIMIT 1",
            ['t' => $tenantId, 'p' => $phone]
        );
    }

    /**
     * Login por telefono + PIN (cualquier tenant). El portal del driver no
     * conoce el tenant; el primer driver con ese phone+PIN se autentica.
     */
    public static function authenticate(string $phone, string $pin): ?array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM drivers WHERE phone = :p AND deleted_at IS NULL AND is_active = 1",
            ['p' => $phone]
        );
        foreach ($rows as $row) {
            if (password_verify($pin, (string) $row['pin_hash'])) {
                return $row;
            }
        }
        return null;
    }

    public static function listForTenant(int $tenantId, array $filters = []): array
    {
        $where  = ['tenant_id = :t', 'deleted_at IS NULL'];
        $params = ['t' => $tenantId];

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = 'status = :s';
            $params['s'] = $filters['status'];
        }
        if (isset($filters['active_only']) && $filters['active_only']) {
            $where[] = 'is_active = 1';
        }
        if (!empty($filters['q'])) {
            $where[] = '(name LIKE :q OR phone LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        return Database::fetchAll(
            "SELECT d.*,
                    (SELECT COUNT(*) FROM deliveries dl
                       WHERE dl.driver_id = d.id
                         AND dl.status IN ('assigned','accepted','picked_up','arriving')) AS active_deliveries
             FROM drivers d
             WHERE " . implode(' AND ', $where) . "
             ORDER BY
                CASE d.status WHEN 'on_delivery' THEN 1 WHEN 'online' THEN 2 WHEN 'offline' THEN 3 ELSE 4 END,
                d.name ASC",
            $params
        );
    }

    /**
     * Drivers disponibles para asignacion: online o on_delivery con cupo.
     * Estrategia simple: prefiere "online" (sin pedido activo).
     */
    public static function listAvailable(int $tenantId): array
    {
        return Database::fetchAll(
            "SELECT d.*,
                    (SELECT COUNT(*) FROM deliveries dl
                       WHERE dl.driver_id = d.id
                         AND dl.status IN ('assigned','accepted','picked_up','arriving')) AS active_deliveries
             FROM drivers d
             WHERE d.tenant_id = :t
               AND d.deleted_at IS NULL
               AND d.is_active = 1
               AND d.status IN ('online','on_delivery')
             ORDER BY active_deliveries ASC, d.last_ping_at DESC",
            ['t' => $tenantId]
        );
    }

    public static function setStatus(int $tenantId, int $id, string $status): void
    {
        Database::update('drivers', ['status' => $status], ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function updateLocation(int $driverId, float $lat, float $lng): void
    {
        Database::update('drivers', [
            'last_lat' => $lat,
            'last_lng' => $lng,
            'last_ping_at' => date('Y-m-d H:i:s'),
        ], ['id' => $driverId]);
    }

    public static function softDelete(int $tenantId, int $id): void
    {
        Database::update('drivers', [
            'deleted_at' => date('Y-m-d H:i:s'),
            'is_active'  => 0,
            'status'     => 'offline',
        ], ['id' => $id, 'tenant_id' => $tenantId]);
    }

    /**
     * KPIs operativos del driver para el panel admin: hoy y semana actual.
     */
    public static function stats(int $tenantId, int $driverId): array
    {
        $todayDeliveries = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM deliveries
             WHERE tenant_id = :t AND driver_id = :d
               AND DATE(created_at) = CURDATE() AND status = 'delivered'",
            ['t' => $tenantId, 'd' => $driverId]
        );
        $weekDeliveries = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM deliveries
             WHERE tenant_id = :t AND driver_id = :d
               AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
               AND status = 'delivered'",
            ['t' => $tenantId, 'd' => $driverId]
        );
        $cashHeld = (float) Database::fetchColumn(
            "SELECT COALESCE(SUM(cash_collected),0) - COALESCE((
                SELECT SUM(cash_collected) FROM driver_payouts
                 WHERE driver_id = :d AND status = 'paid'
            ),0)
             FROM deliveries
             WHERE tenant_id = :t AND driver_id = :d
               AND status = 'delivered' AND payment_method = 'cash'",
            ['t' => $tenantId, 'd' => $driverId]
        );
        $commissionWeek = (float) Database::fetchColumn(
            "SELECT COALESCE(SUM(driver_commission),0) FROM deliveries
             WHERE tenant_id = :t AND driver_id = :d
               AND status = 'delivered'
               AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
            ['t' => $tenantId, 'd' => $driverId]
        );
        return [
            'today_deliveries' => $todayDeliveries,
            'week_deliveries'  => $weekDeliveries,
            'cash_held'        => max(0.0, $cashHeld),
            'commission_week'  => $commissionWeek,
        ];
    }
}
