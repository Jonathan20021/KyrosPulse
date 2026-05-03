<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Models\DeliveryZone;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\WhatsappChannel;

/**
 * Menu publico web compartible. El cliente abre el link {/m/{uuid}}, arma su
 * carrito y al confirmar se abre WhatsApp con la orden ya redactada y el
 * registro persistido del lado del restaurante.
 */
final class PublicMenuController extends Controller
{
    public function show(Request $request, array $params): void
    {
        $tenant = $this->resolveTenant((string) ($params['uuid'] ?? ''));

        $tenantId   = (int) $tenant['id'];
        $categories = MenuCategory::listForTenant($tenantId, true);
        $items      = MenuItem::listForTenant($tenantId, ['available_only' => true]);

        // Agrupar items por categoria preservando orden
        $grouped = ['_uncat' => []];
        foreach ($categories as $c) $grouped[(int) $c['id']] = [];
        foreach ($items as $i) {
            $key = !empty($i['category_id']) ? (int) $i['category_id'] : '_uncat';
            $grouped[$key] ??= [];
            $grouped[$key][] = $i;
        }

        $zones   = DeliveryZone::listForTenant($tenantId, true);
        $channel = WhatsappChannel::findDefault($tenantId);
        $waPhone = $channel['phone'] ?? ($tenant['wasapi_phone'] ?? '');

        \App\Core\View::display('public.menu', [
            'tenant'     => $tenant,
            'categories' => $categories,
            'items'      => $items,
            'grouped'    => $grouped,
            'zones'      => $zones,
            'waPhone'    => $this->normalizePhone((string) $waPhone),
            'currency'   => $items[0]['currency'] ?? 'USD',
            'menuUrl'    => url('/m/' . $tenant['uuid']),
        ]);
    }

    /**
     * Recibe el carrito (JSON), persiste la orden y devuelve el link wa.me
     * con el resumen redactado para que el cliente solo confirme con un tap.
     */
    public function checkout(Request $request, array $params): void
    {
        $tenant   = $this->resolveTenant((string) ($params['uuid'] ?? ''));
        $tenantId = (int) $tenant['id'];

        $payload = $request->input();
        $items   = $payload['items'] ?? [];
        if (!is_array($items) || empty($items)) {
            $this->json(['success' => false, 'error' => 'Tu carrito esta vacio.'], 422);
            return;
        }

        $name  = mb_substr(trim((string) ($payload['name']  ?? '')), 0, 120);
        $phone = mb_substr(trim((string) ($payload['phone'] ?? '')), 0, 40);
        if ($name === '' || $phone === '') {
            $this->json(['success' => false, 'error' => 'Nombre y telefono son obligatorios.'], 422);
            return;
        }

        $deliveryType = (string) ($payload['delivery_type'] ?? 'delivery');
        if (!in_array($deliveryType, ['delivery','pickup','dine_in'], true)) $deliveryType = 'delivery';

        $zoneId = !empty($payload['delivery_zone_id']) ? (int) $payload['delivery_zone_id'] : null;
        $deliveryFee = 0.0;
        if ($zoneId) {
            $zone = DeliveryZone::findById($tenantId, $zoneId);
            if ($zone) $deliveryFee = (float) $zone['fee'];
            else $zoneId = null;
        }

        // Validar y normalizar items contra el menu real (precio autoritativo)
        $clean = [];
        $subtotal = 0.0;
        $currency = 'USD';
        foreach ($items as $line) {
            if (!is_array($line)) continue;
            $itemId = !empty($line['id']) ? (int) $line['id'] : 0;
            if ($itemId <= 0) continue;
            $item = MenuItem::findById($tenantId, $itemId);
            if (!$item || empty($item['is_available'])) continue;

            $qty = max(1, min(99, (int) ($line['qty'] ?? 1)));
            $price = (float) $item['price'];
            $note  = mb_substr(trim((string) ($line['notes'] ?? '')), 0, 200) ?: null;

            $clean[] = [
                'menu_item_id' => (int) $item['id'],
                'name'         => (string) $item['name'],
                'qty'          => $qty,
                'unit_price'   => $price,
                'subtotal'     => $price * $qty,
                'notes'        => $note,
            ];
            $subtotal += $price * $qty;
            $currency = (string) ($item['currency'] ?? $currency);
        }

        if (empty($clean)) {
            $this->json(['success' => false, 'error' => 'Ningun articulo del carrito esta disponible.'], 422);
            return;
        }

        $address = mb_substr(trim((string) ($payload['address'] ?? '')), 0, 500) ?: null;
        $note    = mb_substr(trim((string) ($payload['note']    ?? '')), 0, 500) ?: null;

        $orderId = Order::create([
            'tenant_id'        => $tenantId,
            'customer_name'    => $name,
            'customer_phone'   => $phone,
            'delivery_type'    => $deliveryType,
            'delivery_zone_id' => $zoneId,
            'delivery_address' => $address,
            'delivery_notes'   => $note,
            'kitchen_notes'    => null,
            'status'           => 'new',
            'payment_method'   => 'cash',
            'payment_status'   => 'pending',
            'delivery_fee'     => $deliveryFee,
            'tax'              => 0,
            'discount'         => 0,
            'tip'              => 0,
            'currency'         => $currency,
            'is_ai_generated'  => 0,
            'metadata'         => json_encode(['source' => 'public_menu'], JSON_UNESCAPED_UNICODE),
        ]);

        foreach ($clean as $line) {
            Database::insert('order_items', array_merge(
                ['tenant_id' => $tenantId, 'order_id' => $orderId],
                $line
            ));
        }
        Order::recalcTotals($tenantId, $orderId);

        Database::insert('order_events', [
            'tenant_id' => $tenantId,
            'order_id'  => $orderId,
            'user_id'   => null,
            'event'     => 'created',
            'to_status' => 'new',
            'note'      => 'Orden creada desde menu publico web',
        ]);

        // Sync con CRM/leads (no bloqueante)
        try {
            (new \App\Services\LeadSyncService($tenantId))->ensureRestaurantStages();
            (new \App\Services\LeadSyncService($tenantId))->syncOrderToLead($orderId);
        } catch (\Throwable $e) {
            Logger::error('LeadSync menu publico fallo', ['msg' => $e->getMessage()]);
        }

        // Asegurar contexto de tenant antes de auditar (request publico, no hay sesion)
        \App\Core\Tenant::setCurrent($tenant);
        Audit::log('order.created', 'order', $orderId, [], ['source' => 'public_menu']);

        $order = Order::findById($tenantId, $orderId);

        // Construir wa.me con el resumen pre-cargado (cliente confirma con un tap)
        $channel = WhatsappChannel::findDefault($tenantId);
        $waPhone = $this->normalizePhone((string) ($channel['phone'] ?? ($tenant['wasapi_phone'] ?? '')));
        $msg     = $this->renderWhatsAppMessage($order, $clean, $tenant);
        $waUrl   = 'https://wa.me/' . $waPhone . '?text=' . rawurlencode($msg);

        $this->json([
            'success'   => true,
            'order_code' => $order['code'],
            'total'      => (float) $order['total'],
            'currency'   => $currency,
            'wa_url'     => $waUrl,
        ]);
    }

    private function resolveTenant(string $uuid): array
    {
        $uuid = trim($uuid);
        if ($uuid === '') $this->abort(404, 'Menu no encontrado.');

        $tenant = Database::fetch(
            "SELECT * FROM tenants WHERE uuid = :u AND deleted_at IS NULL",
            ['u' => $uuid]
        );
        if (!$tenant) $this->abort(404, 'Menu no encontrado.');
        if (empty($tenant['is_restaurant'])) $this->abort(404, 'Este negocio no tiene menu publico.');
        if (isset($tenant['public_menu_enabled']) && (int) $tenant['public_menu_enabled'] === 0) {
            $this->abort(403, 'El menu publico esta desactivado.');
        }
        return $tenant;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        return ltrim($digits, '0');
    }

    private function renderWhatsAppMessage(array $order, array $items, array $tenant): string
    {
        $brand = (string) ($tenant['name'] ?? 'Restaurante');
        $code  = (string) $order['code'];
        $cur   = (string) $order['currency'];
        $lines = ["Hola $brand! Quiero confirmar mi orden #$code:", ''];
        foreach ($items as $line) {
            $lines[] = sprintf('• %dx %s — %s %.2f',
                $line['qty'],
                $line['name'],
                $cur,
                $line['subtotal']
            );
        }
        $lines[] = '';
        if ((float) $order['delivery_fee'] > 0) {
            $lines[] = sprintf('Delivery: %s %.2f', $cur, (float) $order['delivery_fee']);
        }
        $lines[] = sprintf('TOTAL: %s %.2f', $cur, (float) $order['total']);
        if (!empty($order['delivery_address'])) {
            $lines[] = '';
            $lines[] = 'Direccion: ' . $order['delivery_address'];
        }
        if (!empty($order['delivery_notes'])) {
            $lines[] = 'Nota: ' . $order['delivery_notes'];
        }
        return implode("\n", $lines);
    }
}
