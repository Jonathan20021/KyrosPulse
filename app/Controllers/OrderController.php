<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Tenant;
use App\Models\DeliveryZone;
use App\Models\MenuItem;
use App\Models\Order;
use App\Services\ChannelDispatcher;

final class OrderController extends Controller
{
    public function index(Request $request): void
    {
        $tenantId = Tenant::id();

        $columns = ['new', 'confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'cancelled'];
        $kanban = [];
        foreach ($columns as $st) {
            $kanban[$st] = Order::listByStatus($tenantId, $st, 30);
        }

        $stats = Order::dashboardStats($tenantId);

        $this->view('orders.index', [
            'page'    => 'orders',
            'kanban'  => $kanban,
            'stats'   => $stats,
            'statuses' => Order::STATUSES,
        ], 'layouts.app');
    }

    public function show(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $order = Order::findById($tenantId, (int) ($params['id'] ?? 0));
        if (!$order) $this->abort(404);

        $items  = Order::items($tenantId, (int) $order['id']);
        $events = Order::events($tenantId, (int) $order['id']);

        $this->view('orders.show', [
            'page'   => 'orders',
            'order'  => $order,
            'items'  => $items,
            'events' => $events,
            'statuses' => Order::STATUSES,
            'flow'   => Order::STATUS_FLOW,
            'zones'  => DeliveryZone::listForTenant($tenantId, true),
        ], 'layouts.app');
    }

    public function create(Request $request): void
    {
        $tenantId = Tenant::id();
        $this->view('orders.create', [
            'page'    => 'orders',
            'menu'    => MenuItem::listForTenant($tenantId, ['available_only' => true]),
            'zones'   => DeliveryZone::listForTenant($tenantId, true),
        ], 'layouts.app');
    }

    public function store(Request $request): void
    {
        $tenantId = Tenant::id();
        $items = $request->input('items', []);
        if (!is_array($items) || empty($items)) {
            Session::flash('error', 'Agrega al menos un articulo.');
            $this->redirect('/orders/create');
            return;
        }

        $deliveryType = (string) $request->input('delivery_type', 'delivery');
        if (!in_array($deliveryType, ['delivery','pickup','dine_in'], true)) $deliveryType = 'delivery';

        $zoneId = $request->input('delivery_zone_id') ? (int) $request->input('delivery_zone_id') : null;
        $deliveryFee = 0.0;
        if ($zoneId) {
            $zone = DeliveryZone::findById($tenantId, $zoneId);
            if ($zone) $deliveryFee = (float) $zone['fee'];
        }

        $orderId = Order::create([
            'tenant_id'        => $tenantId,
            'created_by'       => Auth::id(),
            'customer_name'    => trim((string) $request->input('customer_name', 'Walk-in')),
            'customer_phone'   => trim((string) $request->input('customer_phone', '')) ?: null,
            'delivery_type'    => $deliveryType,
            'delivery_zone_id' => $zoneId,
            'delivery_address' => trim((string) $request->input('delivery_address', '')) ?: null,
            'delivery_notes'   => trim((string) $request->input('delivery_notes', '')) ?: null,
            'kitchen_notes'    => trim((string) $request->input('kitchen_notes', '')) ?: null,
            'status'           => 'new',
            'payment_method'   => $request->input('payment_method') ?: 'cash',
            'payment_status'   => 'pending',
            'delivery_fee'     => $deliveryFee,
            'tax'              => (float) ($request->input('tax') ?: 0),
            'discount'         => (float) ($request->input('discount') ?: 0),
            'tip'              => (float) ($request->input('tip') ?: 0),
            'currency'         => (string) ($request->input('currency') ?: 'USD'),
        ]);

        // Insertar items
        foreach ($items as $line) {
            if (!is_array($line)) continue;
            $itemId = !empty($line['menu_item_id']) ? (int) $line['menu_item_id'] : null;
            $name   = trim((string) ($line['name'] ?? ''));
            $qty    = max(1, (int) ($line['qty'] ?? 1));
            $price  = (float) ($line['unit_price'] ?? 0);
            if ($itemId) {
                $item = MenuItem::findById($tenantId, $itemId);
                if ($item) {
                    $name  = $name !== '' ? $name : (string) $item['name'];
                    $price = $price > 0 ? $price : (float) $item['price'];
                }
            }
            if ($name === '' || $price <= 0) continue;
            Database::insert('order_items', [
                'tenant_id'    => $tenantId,
                'order_id'     => $orderId,
                'menu_item_id' => $itemId,
                'name'         => $name,
                'qty'          => $qty,
                'unit_price'   => $price,
                'subtotal'     => $price * $qty,
                'modifiers'    => isset($line['modifiers']) && is_array($line['modifiers'])
                                    ? json_encode($line['modifiers'], JSON_UNESCAPED_UNICODE) : null,
                'notes'        => trim((string) ($line['notes'] ?? '')) ?: null,
            ]);
        }
        Order::recalcTotals($tenantId, $orderId);

        Database::insert('order_events', [
            'tenant_id' => $tenantId,
            'order_id'  => $orderId,
            'user_id'   => Auth::id(),
            'event'     => 'created',
            'to_status' => 'new',
            'note'      => 'Orden creada manualmente',
        ]);

        Audit::log('order.created', 'order', $orderId);
        Session::flash('success', 'Orden creada.');
        $this->redirect('/orders/' . $orderId);
    }

    public function status(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $orderId = (int) ($params['id'] ?? 0);
        $newStatus = (string) $request->input('status', '');
        $note = (string) $request->input('note', '');

        $ok = Order::transitionStatus($tenantId, $orderId, $newStatus, Auth::id(), $note ?: null);
        if (!$ok) {
            $this->json(['success' => false, 'error' => 'Transicion no permitida desde el estado actual.'], 422);
            return;
        }

        // Notificar al cliente por WhatsApp si tenemos telefono
        $order = Order::findById($tenantId, $orderId);
        if ($order && !empty($order['customer_phone'])) {
            $msg = $this->statusMessage($order, $newStatus);
            if ($msg !== '') {
                try {
                    (new ChannelDispatcher($tenantId))->sendText(
                        (string) $order['customer_phone'],
                        $msg,
                        $order['channel_id'] ? (int) $order['channel_id'] : null
                    );
                } catch (\Throwable) { /* silent */ }
            }
        }

        Audit::log('order.status_changed', 'order', $orderId, [], ['status' => $newStatus]);
        if ($request->expectsJson()) {
            $this->json(['success' => true, 'status' => $newStatus]);
            return;
        }
        Session::flash('success', 'Estado actualizado.');
        $this->redirect('/orders/' . $orderId);
    }

    public function cancel(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $orderId = (int) ($params['id'] ?? 0);
        $reason = (string) $request->input('reason', 'Cancelada');
        Order::transitionStatus($tenantId, $orderId, 'cancelled', Auth::id(), $reason);
        Session::flash('success', 'Orden cancelada.');
        $this->redirect('/orders/' . $orderId);
    }

    public function notes(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        Order::update($tenantId, $id, [
            'kitchen_notes' => trim((string) $request->input('kitchen_notes', '')) ?: null,
            'delivery_notes' => trim((string) $request->input('delivery_notes', '')) ?: null,
        ]);
        Session::flash('success', 'Notas guardadas.');
        $this->redirect('/orders/' . $id);
    }

    public function liveSnapshot(Request $request): void
    {
        $tenantId = Tenant::id();
        $columns = ['new','confirmed','preparing','ready','out_for_delivery','delivered','cancelled'];
        $counts = [];
        foreach ($columns as $c) {
            $counts[$c] = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM orders WHERE tenant_id = :t AND status = :s AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)",
                ['t' => $tenantId, 's' => $c]
            );
        }
        $latest = Database::fetchColumn(
            "SELECT MAX(updated_at) FROM orders WHERE tenant_id = :t",
            ['t' => $tenantId]
        );
        $this->json(['success' => true, 'counts' => $counts, 'latest' => $latest]);
    }

    private function statusMessage(array $order, string $status): string
    {
        $name = $order['customer_name'] ?: 'cliente';
        return match ($status) {
            'confirmed'        => "Hola $name! Tu orden #{$order['code']} fue confirmada. La estamos preparando 🍽",
            'preparing'        => "Tu orden #{$order['code']} esta en cocina 👨‍🍳",
            'ready'            => "Tu orden #{$order['code']} esta lista 🛎",
            'out_for_delivery' => "Tu orden #{$order['code']} salio a entrega 🛵 — llega pronto.",
            'delivered'        => "Entregamos tu orden #{$order['code']} ✅. Buen provecho!",
            'cancelled'        => "Lamentamos informar que tu orden #{$order['code']} fue cancelada. Motivo: " . ($order['cancelled_reason'] ?? 'sin especificar') . '. Cualquier duda, escribenos.',
            default => '',
        };
    }
}
