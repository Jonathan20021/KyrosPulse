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
        'new'              => ['confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'cancelled'],
        'confirmed'        => ['preparing', 'ready', 'out_for_delivery', 'delivered', 'cancelled'],
        'preparing'        => ['ready', 'out_for_delivery', 'delivered', 'cancelled'],
        'ready'            => ['out_for_delivery', 'delivered', 'cancelled'],
        'out_for_delivery' => ['delivered', 'cancelled'],
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

    /**
     * Disparar el evento order.new despues de que el caller haya insertado los
     * order_items y llamado recalcTotals. Antes este dispatch estaba dentro de
     * create(), pero al ejecutarse "demasiado pronto" el email/Slack llegaban
     * con "Sin items" y total 0. Ahora es responsabilidad del caller invocarlo
     * al final del flujo de creacion.
     */
    public static function dispatchCreated(int $tenantId, int $orderId): void
    {
        try {
            $full = self::findById($tenantId, $orderId);
            if (!$full) return;
            $full['items'] = self::items($tenantId, $orderId);
            (new \App\Services\NotificationDispatcher($tenantId))
                ->dispatchOrderEvent($full, 'order.new');
        } catch (\Throwable $e) {
            \App\Core\Logger::warning('NotificationDispatcher order.new fallo', ['msg' => $e->getMessage()]);
        }
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

        // Sincronizar con CRM/pipeline
        try {
            (new \App\Services\LeadSyncService($tenantId))->syncOrderToLead($orderId);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('LeadSync status fallo', ['msg' => $e->getMessage()]);
        }

        // Evento global
        try {
            \App\Core\Events::dispatch('order.status_changed', [
                'tenant_id'   => $tenantId,
                'order_id'    => $orderId,
                'from_status' => $currentStatus,
                'to_status'   => $newStatus,
            ]);
        } catch (\Throwable) {}

        // Notificaciones multi-canal a destinos configurados
        try {
            $fullOrder = self::findById($tenantId, $orderId) ?? $order;
            $fullOrder['items'] = self::items($tenantId, $orderId);
            (new \App\Services\NotificationDispatcher($tenantId))
                ->dispatchOrderEvent($fullOrder, 'order.' . $newStatus);
        } catch (\Throwable $e) {
            \App\Core\Logger::warning('NotificationDispatcher fallo', ['msg' => $e->getMessage()]);
        }

        // Mensaje WhatsApp al cliente con el cambio de estado.
        // Centralizado aqui para que cualquier flujo (REST, KDS, automation)
        // lo dispare igual sin duplicar logica.
        try {
            $fresh = self::findById($tenantId, $orderId);
            if ($fresh && !empty($fresh['customer_phone'])) {
                $msg = self::customerStatusMessage($fresh, $newStatus);
                if ($msg !== '') {
                    (new \App\Services\ChannelDispatcher($tenantId))->sendText(
                        (string) $fresh['customer_phone'],
                        $msg,
                        !empty($fresh['channel_id']) ? (int) $fresh['channel_id'] : null
                    );
                }
            }
        } catch (\Throwable $e) {
            \App\Core\Logger::warning('ChannelDispatcher customer-notify fallo', ['msg' => $e->getMessage()]);
        }

        return true;
    }

    /**
     * Mensaje al cliente segun nuevo estado. Centralizado para reuso.
     */
    private static function customerStatusMessage(array $order, string $status): string
    {
        $name = trim((string) ($order['customer_name'] ?? '')) ?: 'cliente';
        $code = (string) ($order['code'] ?? '');
        return match ($status) {
            'confirmed'        => "Hola $name! Tu orden #$code fue confirmada. La estamos preparando 🍽",
            'preparing'        => "Tu orden #$code esta en cocina 👨‍🍳",
            'ready'            => "Tu orden #$code esta lista 🛎",
            'out_for_delivery' => "Tu orden #$code salio a entrega 🛵 — llega pronto.",
            'delivered'        => "Entregamos tu orden #$code ✅. Buen provecho!",
            'cancelled'        => "Lamentamos informar que tu orden #$code fue cancelada. Motivo: " . ($order['cancelled_reason'] ?? 'sin especificar') . '. Cualquier duda, escribenos.',
            default => '',
        };
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
        $discount    = (float) $order['discount'];
        $tip         = (float) $order['tip'];

        // ITBIS: calcular como subtotal * tax_rate / 100 desde restaurant_settings
        // del tenant. Asi todas las ordenes (manual, menu publico, IA) llevan
        // impuesto consistente sin depender de quien las creo.
        $taxRate = self::tenantTaxRate($tenantId);
        $tax     = $taxRate > 0 ? round($subtotal * ($taxRate / 100), 2) : 0.0;

        $total   = max(0, $subtotal + $deliveryFee + $tax + $tip - $discount);

        self::update($tenantId, $orderId, [
            'subtotal' => $subtotal,
            'tax'      => $tax,
            'total'    => $total,
        ]);
    }

    public static function tenantTaxRate(int $tenantId): float
    {
        $settings = Database::fetchColumn(
            "SELECT restaurant_settings FROM tenants WHERE id = :t",
            ['t' => $tenantId]
        );
        if (!$settings) return 0.0;
        $decoded = json_decode((string) $settings, true);
        if (!is_array($decoded)) return 0.0;
        return (float) ($decoded['tax_rate'] ?? 0);
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
