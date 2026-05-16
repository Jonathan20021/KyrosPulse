<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Delivery;
use App\Models\DeliveryLocation;
use App\Models\Driver;
use App\Models\Order;

/**
 * Orquesta todo el ciclo de vida del modulo Delivery:
 *
 *  - Crear delivery a partir de una orden de tipo "delivery".
 *  - Asignar driver (manual o auto).
 *  - Transicionar estados respetando STATUS_FLOW.
 *  - Calcular comision al driver y cash-to-collect.
 *  - Registrar pings GPS y mantener al cliente notificado por WhatsApp.
 *  - Generar payouts por periodo.
 */
final class DeliveryService
{
    public function __construct(private int $tenantId) {}

    /**
     * Crea (o devuelve la existente) delivery para una orden. Idempotente.
     * Calcula cash_to_collect basado en payment_method + total.
     */
    public function ensureForOrder(int $orderId): ?array
    {
        $order = Order::findById($this->tenantId, $orderId);
        if (!$order) return null;
        if (($order['delivery_type'] ?? '') !== 'delivery') return null;

        $existing = Delivery::findByOrder($this->tenantId, $orderId);
        if ($existing) return $existing;

        $cashToCollect = 0.0;
        if (($order['payment_method'] ?? '') === 'cash' && ($order['payment_status'] ?? '') !== 'paid') {
            $cashToCollect = (float) $order['total'];
        }

        $deliveryId = Delivery::create([
            'tenant_id'       => $this->tenantId,
            'order_id'        => $orderId,
            'status'          => 'pending',
            'dropoff_address' => $order['delivery_address'] ?? null,
            'delivery_fee'    => (float) ($order['delivery_fee'] ?? 0),
            'cash_to_collect' => $cashToCollect,
            'payment_method'  => $order['payment_method'] ?? null,
        ]);

        // Sincronizar tracking_token en la orden para link publico unico
        $delivery = Delivery::findById($this->tenantId, $deliveryId);
        if ($delivery) {
            Order::update($this->tenantId, $orderId, [
                'delivery_id'    => $deliveryId,
                'tracking_token' => $delivery['tracking_token'],
            ]);
        }
        return $delivery;
    }

    /**
     * Asigna un driver a una delivery. Calcula comision en base al perfil del
     * driver y la orden. Cambia driver.status a "on_delivery".
     */
    public function assignDriver(int $deliveryId, int $driverId): bool
    {
        $delivery = Delivery::findById($this->tenantId, $deliveryId);
        $driver = Driver::findById($this->tenantId, $driverId);
        if (!$delivery || !$driver) return false;
        if (!in_array($delivery['status'], ['pending','cancelled','failed'], true)) {
            // re-asignar: si ya tenia un driver, devolverlo a "online"
            if (!empty($delivery['driver_id'])) {
                Driver::setStatus($this->tenantId, (int) $delivery['driver_id'], 'online');
            }
        }

        $order = Order::findById($this->tenantId, (int) $delivery['order_id']);
        $commission = $this->calculateCommission($driver, $order ?? [], $delivery);

        Delivery::update($this->tenantId, $deliveryId, [
            'driver_id'         => $driverId,
            'status'            => 'assigned',
            'driver_commission' => $commission,
            'assigned_at'       => date('Y-m-d H:i:s'),
        ]);
        Driver::setStatus($this->tenantId, $driverId, 'on_delivery');

        // Notificar al cliente que su pedido fue asignado
        $this->notifyCustomerAssignment($delivery, $driver, $order);

        return true;
    }

    /**
     * Auto-asignacion: elige el driver disponible con menos entregas activas.
     */
    public function autoAssign(int $deliveryId): ?int
    {
        $candidates = Driver::listAvailable($this->tenantId);
        if (!$candidates) return null;
        // El primer candidato ya viene ordenado por active_deliveries ASC
        $chosen = $candidates[0];
        $ok = $this->assignDriver($deliveryId, (int) $chosen['id']);
        return $ok ? (int) $chosen['id'] : null;
    }

    /**
     * Transicion validada de estado. Si el nuevo estado es delivered/failed/cancelled,
     * libera al driver (vuelve a "online" si no tiene mas pedidos activos).
     * Tambien sincroniza con la orden: picked_up -> out_for_delivery, delivered -> delivered.
     */
    public function transition(int $deliveryId, string $newStatus, ?int $userId = null, array $extra = []): bool
    {
        $delivery = Delivery::findById($this->tenantId, $deliveryId);
        if (!$delivery) return false;
        $current = (string) $delivery['status'];
        $allowed = Delivery::STATUS_FLOW[$current] ?? [];
        if (!in_array($newStatus, $allowed, true) && $newStatus !== 'cancelled') {
            return false;
        }

        $update = ['status' => $newStatus];
        $now = date('Y-m-d H:i:s');
        switch ($newStatus) {
            case 'accepted':  $update['accepted_at']  = $now; break;
            case 'picked_up': $update['picked_up_at'] = $now; break;
            case 'arriving':  $update['arriving_at']  = $now; break;
            case 'delivered':
                $update['delivered_at']   = $now;
                $update['cash_collected'] = isset($extra['cash_collected'])
                    ? (float) $extra['cash_collected']
                    : (float) $delivery['cash_to_collect'];
                break;
            case 'failed':
                $update['failed_at']      = $now;
                $update['failure_reason'] = (string) ($extra['reason'] ?? 'No especificado');
                break;
            case 'cancelled':
                $update['cancelled_at']   = $now;
                $update['failure_reason'] = (string) ($extra['reason'] ?? 'Cancelada');
                break;
        }
        Delivery::update($this->tenantId, $deliveryId, $update);

        // Sincronizar orden
        if ($newStatus === 'picked_up') {
            Order::transitionStatus($this->tenantId, (int) $delivery['order_id'], 'out_for_delivery', $userId, 'Driver recogio el pedido');
        } elseif ($newStatus === 'delivered') {
            Order::transitionStatus($this->tenantId, (int) $delivery['order_id'], 'delivered', $userId, 'Driver entrego el pedido');
            // Marcar la orden como pagada si era cash
            $order = Order::findById($this->tenantId, (int) $delivery['order_id']);
            if ($order && ($order['payment_method'] ?? '') === 'cash' && ($order['payment_status'] ?? '') !== 'paid') {
                Order::update($this->tenantId, (int) $delivery['order_id'], ['payment_status' => 'paid']);
            }
            // Incrementar total_deliveries del driver
            if (!empty($delivery['driver_id'])) {
                Database::run(
                    "UPDATE drivers SET total_deliveries = total_deliveries + 1 WHERE id = :id",
                    ['id' => (int) $delivery['driver_id']]
                );
            }
        }

        // Liberar driver si la entrega ya termino
        if (in_array($newStatus, ['delivered','failed','cancelled'], true) && !empty($delivery['driver_id'])) {
            $stillActive = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM deliveries
                 WHERE driver_id = :d AND status IN ('assigned','accepted','picked_up','arriving')",
                ['d' => (int) $delivery['driver_id']]
            );
            if ($stillActive === 0) {
                Driver::setStatus($this->tenantId, (int) $delivery['driver_id'], 'online');
            }
        }

        // Notificar al cliente segun el estado
        $this->notifyCustomerTransition($deliveryId, $newStatus);

        return true;
    }

    /**
     * Calcula la comision al driver segun su perfil.
     * Tipos: flat | percent (sobre subtotal) | per_km (necesita distance_km) | mixed.
     */
    public function calculateCommission(array $driver, array $order, array $delivery): float
    {
        $type = (string) ($driver['commission_type'] ?? 'flat');
        $subtotal = (float) ($order['subtotal'] ?? $order['total'] ?? 0);
        $km = (float) ($delivery['distance_km'] ?? 0);
        $flat = (float) ($driver['commission_flat'] ?? 0);
        $pct = (float) ($driver['commission_percent'] ?? 0);
        $perKm = (float) ($driver['commission_per_km'] ?? 0);

        $value = match ($type) {
            'percent' => $subtotal * ($pct / 100),
            'per_km'  => $km * $perKm,
            'mixed'   => $flat + ($km * $perKm),
            default   => $flat,
        };
        return round(max(0.0, $value), 2);
    }

    /**
     * Registra una posicion GPS del driver y la propaga al driver record.
     */
    public function recordLocation(int $deliveryId, int $driverId, float $lat, float $lng, array $extra = []): void
    {
        DeliveryLocation::create([
            'tenant_id'   => $this->tenantId,
            'delivery_id' => $deliveryId,
            'driver_id'   => $driverId,
            'lat'         => $lat,
            'lng'         => $lng,
            'accuracy'    => $extra['accuracy'] ?? null,
            'speed_kmh'   => $extra['speed_kmh'] ?? null,
            'heading'     => $extra['heading'] ?? null,
        ]);
        Driver::updateLocation($driverId, $lat, $lng);
    }

    /**
     * Genera (sin marcar como pagado) un payout por driver para el periodo.
     */
    public function generatePayout(int $driverId, string $from, string $to): ?int
    {
        $agg = Database::fetch(
            "SELECT COUNT(*) AS c,
                    COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN cash_collected ELSE 0 END),0) AS cash,
                    COALESCE(SUM(driver_commission),0) AS comm
             FROM deliveries
             WHERE tenant_id = :t AND driver_id = :d
               AND status = 'delivered'
               AND DATE(delivered_at) BETWEEN :from AND :to",
            ['t' => $this->tenantId, 'd' => $driverId, 'from' => $from, 'to' => $to]
        );
        if (!$agg || (int) $agg['c'] === 0) return null;

        $cash = (float) $agg['cash'];
        $comm = (float) $agg['comm'];
        // Si el driver tiene cash en mano (de ventas a contado), el "neto" que
        // se le debe pagar es: comision - efectivo recolectado.
        // Si es negativo, el driver debe efectivo al local.
        $net = round($comm - $cash, 2);

        return \App\Models\DriverPayout::create([
            'tenant_id'        => $this->tenantId,
            'driver_id'        => $driverId,
            'period_from'      => $from,
            'period_to'        => $to,
            'deliveries_count' => (int) $agg['c'],
            'cash_collected'   => $cash,
            'commission_owed'  => $comm,
            'net_amount'       => $net,
            'status'           => 'pending',
        ]);
    }

    // ----------------------------------------------------------------------
    // Notificaciones al cliente
    // ----------------------------------------------------------------------

    private function notifyCustomerAssignment(array $delivery, array $driver, ?array $order): void
    {
        if (!$order || empty($order['customer_phone'])) return;
        $appUrl = rtrim((string) config('app.url', ''), '/');
        $link = $appUrl . '/d/' . ($delivery['tracking_token'] ?? '');
        $name = trim((string) ($order['customer_name'] ?? '')) ?: 'cliente';
        $msg = "Hola $name! Tu pedido #" . ($order['code'] ?? '') . " fue asignado a "
             . ($driver['name'] ?? 'tu repartidor') . " 🛵\n"
             . "Sigue en vivo: " . $link;
        try {
            (new ChannelDispatcher($this->tenantId))->sendText(
                (string) $order['customer_phone'], $msg,
                !empty($order['channel_id']) ? (int) $order['channel_id'] : null
            );
        } catch (\Throwable $e) {
            Logger::warning('DeliveryService assignment-notify fallo', ['msg' => $e->getMessage()]);
        }
    }

    private function notifyCustomerTransition(int $deliveryId, string $status): void
    {
        $fresh = Delivery::findById($this->tenantId, $deliveryId);
        if (!$fresh || empty($fresh['customer_phone'])) return;
        $appUrl = rtrim((string) config('app.url', ''), '/');
        $link = $appUrl . '/d/' . ($fresh['tracking_token'] ?? '');
        $name = trim((string) ($fresh['customer_name'] ?? '')) ?: 'cliente';
        $code = (string) ($fresh['order_code'] ?? '');

        $msg = match ($status) {
            'picked_up' => "Hola $name! Tu pedido #$code ya salio del local 🛵. Sigue en vivo: $link",
            'arriving'  => "Tu repartidor esta llegando con tu pedido #$code 🚦",
            'delivered' => "Entregamos tu pedido #$code ✅ Buen provecho! Califica a tu repartidor: $link",
            'failed'    => "Hubo un problema entregando tu pedido #$code. Te contactaremos en breve.",
            default     => '',
        };
        if ($msg === '') return;
        try {
            (new ChannelDispatcher($this->tenantId))->sendText(
                (string) $fresh['customer_phone'], $msg,
                !empty($fresh['channel_id']) ? (int) $fresh['channel_id'] : null
            );
        } catch (\Throwable $e) {
            Logger::warning('DeliveryService transition-notify fallo', ['msg' => $e->getMessage()]);
        }
    }
}
