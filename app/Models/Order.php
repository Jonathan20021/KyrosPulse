<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class Order extends Model
{
    protected static string $table = 'orders';

    public const STATUSES = [
        'new'              => ['Nuevo',          '#06B6D4', '🆕'],
        'confirmed'        => ['Confirmado',     '#3B82F6', '✓'],
        'preparing'        => ['En cocina',      '#F59E0B', '👨‍🍳'],
        'ready'            => ['Listo',          '#A855F7', '🛎'],
        'out_for_delivery' => ['En camino',      '#EC4899', '🛵'],
        'delivered'        => ['Entregado',      '#22C55E', '✅'],
        'cancelled'        => ['Cancelado',      '#EF4444', '✗'],
    ];

    public const STATUS_FLOW = [
        'new'              => ['confirmed', 'cancelled'],
        'confirmed'        => ['preparing', 'cancelled'],
        'preparing'        => ['ready', 'cancelled'],
        'ready'            => ['out_for_delivery', 'delivered'],
        'out_for_delivery' => ['delivered'],
    ];

    public static function generateCode(int $tenantId): string
    {
        $today = date('ymd');
        $count = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM orders WHERE tenant_id = :t AND DATE(created_at) = CURDATE()",
            ['t' => $tenantId]
        );
        return 'OR-' . $today . '-' . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }

    public static function create(array $data): int
    {
        $data['code'] = $data['code'] ?? self::generateCode((int) $data['tenant_id']);
        return Database::insert('orders', $data);
    }

    public static function update(int $tenantId, int $id, array $data): int
    {
        return Database::update('orders', $data, ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function findById(int $tenantId, int $id): ?array
    {
        return Database::fetch(
            "SELECT o.*, c.first_name, c.last_name, c.phone AS contact_phone, c.whatsapp,
                    dz.name AS zone_name, dz.fee AS zone_fee
             FROM orders o
             LEFT JOIN contacts c ON c.id = o.contact_id
             LEFT JOIN delivery_zones dz ON dz.id = o.delivery_zone_id
             WHERE o.id = :id AND o.tenant_id = :t",
            ['id' => $id, 't' => $tenantId]
        );
    }

    public static function listByStatus(int $tenantId, string $status, int $limit = 50): array
    {
        return Database::fetchAll(
            "SELECT o.*, c.first_name, c.last_name
             FROM orders o
             LEFT JOIN contacts c ON c.id = o.contact_id
             WHERE o.tenant_id = :t AND o.status = :s
             ORDER BY o.created_at DESC
             LIMIT $limit",
            ['t' => $tenantId, 's' => $status]
        );
    }

    public static function listFiltered(int $tenantId, array $filters = [], int $limit = 100): array
    {
        $where = ['o.tenant_id = :t'];
        $params = ['t' => $tenantId];
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = 'o.status = :st';
            $params['st'] = $filters['status'];
        }
        if (!empty($filters['from'])) { $where[] = 'o.created_at >= :from'; $params['from'] = $filters['from']; }
        if (!empty($filters['to']))   { $where[] = 'o.created_at <= :to';   $params['to']   = $filters['to']; }
        if (!empty($filters['q'])) {
            $where[] = '(o.code LIKE :q OR o.customer_name LIKE :q OR o.customer_phone LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }
        return Database::fetchAll(
            "SELECT o.*, c.first_name, c.last_name
             FROM orders o
             LEFT JOIN contacts c ON c.id = o.contact_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY o.created_at DESC
             LIMIT $limit",
            $params
        );
    }

    public static function transitionStatus(int $tenantId, int $orderId, string $newStatus, ?int $userId = null, ?string $note = null): bool
    {
        $order = self::findById($tenantId, $orderId);
        if (!$order) return false;
        $currentStatus = (string) $order['status'];
        $allowed = self::STATUS_FLOW[$currentStatus] ?? [];
        $allowAny = $newStatus === 'cancelled';
        if (!in_array($newStatus, $allowed, true) && !$allowAny) {
            return false;
        }

        $update = ['status' => $newStatus];
        $now = date('Y-m-d H:i:s');
        if ($newStatus === 'confirmed')        $update['confirmed_at'] = $now;
        if ($newStatus === 'ready')            $update['ready_at']     = $now;
        if ($newStatus === 'delivered')        $update['delivered_at'] = $now;
        if ($newStatus === 'cancelled')        { $update['cancelled_at'] = $now; if ($note) $update['cancelled_reason'] = mb_substr($note, 0, 250); }

        self::update($tenantId, $orderId, $update);

        Database::insert('order_events', [
            'tenant_id'   => $tenantId,
            'order_id'    => $orderId,
            'user_id'     => $userId,
            'event'       => 'status_changed',
            'from_status' => $currentStatus,
            'to_status'   => $newStatus,
            'note'        => $note ? mb_substr($note, 0, 500) : null,
        ]);

        return true;
    }

    public static function recalcTotals(int $tenantId, int $orderId): void
    {
        $order = self::findById($tenantId, $orderId);
        if (!$order) return;
        $items = Database::fetchAll(
            "SELECT subtotal FROM order_items WHERE order_id = :o AND tenant_id = :t",
            ['o' => $orderId, 't' => $tenantId]
        );
        $subtotal = 0.0;
        foreach ($items as $it) $subtotal += (float) $it['subtotal'];

        $deliveryFee = (float) $order['delivery_fee'];
        $tax         = (float) $order['tax'];
        $discount    = (float) $order['discount'];
        $tip         = (float) $order['tip'];
        $total       = max(0, $subtotal + $deliveryFee + $tax + $tip - $discount);

        self::update($tenantId, $orderId, [
            'subtotal' => $subtotal,
            'total'    => $total,
        ]);
    }

    public static function items(int $tenantId, int $orderId): array
    {
        return Database::fetchAll(
            "SELECT * FROM order_items WHERE order_id = :o AND tenant_id = :t ORDER BY id ASC",
            ['o' => $orderId, 't' => $tenantId]
        );
    }

    public static function events(int $tenantId, int $orderId, int $limit = 50): array
    {
        return Database::fetchAll(
            "SELECT e.*, u.first_name, u.last_name
             FROM order_events e
             LEFT JOIN users u ON u.id = e.user_id
             WHERE e.order_id = :o AND e.tenant_id = :t
             ORDER BY e.id DESC
             LIMIT $limit",
            ['o' => $orderId, 't' => $tenantId]
        );
    }

    public static function dashboardStats(int $tenantId): array
    {
        $today = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM orders WHERE tenant_id = :t AND DATE(created_at) = CURDATE() AND status != 'cancelled'",
            ['t' => $tenantId]
        );
        $revenueToday = (float) Database::fetchColumn(
            "SELECT COALESCE(SUM(total),0) FROM orders WHERE tenant_id = :t AND DATE(created_at) = CURDATE() AND status NOT IN ('cancelled')",
            ['t' => $tenantId]
        );
        $pending = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM orders WHERE tenant_id = :t AND status IN ('new','confirmed','preparing','ready','out_for_delivery')",
            ['t' => $tenantId]
        );
        $avgTicket = (float) Database::fetchColumn(
            "SELECT COALESCE(AVG(total),0) FROM orders WHERE tenant_id = :t AND status NOT IN ('cancelled') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            ['t' => $tenantId]
        );
        return [
            'today'         => $today,
            'revenue_today' => $revenueToday,
            'pending'       => $pending,
            'avg_ticket'    => $avgTicket,
        ];
    }
}
