<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\DeliveryZone;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Tenant;

/**
 * Toma payloads de la IA ([ORDER:...]) y crea ordenes en la base.
 * Resuelve nombres de items contra el menu, calcula totales, asigna zona,
 * y dispara mensajes de confirmacion al cliente.
 */
final class RestaurantOrderEngine
{
    public function __construct(private int $tenantId) {}

    /**
     * Crea una orden a partir del payload JSON que emite la IA.
     *
     * Estructura esperada:
     * {
     *   "items": [{"name":"Hamburguesa Clasica","qty":2,"notes":"sin cebolla","modifiers":[{"name":"Extra queso","price":1.5}]}],
     *   "delivery_type": "delivery|pickup|dine_in",
     *   "address": "Calle X #123",
     *   "zone": "Naco",
     *   "payment": "cash|card|transfer|online",
     *   "customer_name": "Maria Lopez",
     *   "customer_phone": "+18091234567",
     *   "notes": "Tocar el timbre 2 veces"
     * }
     */
    public function createFromAi(array $payload, int $conversationId, int $contactId): array
    {
        $items = $payload['items'] ?? [];
        if (!is_array($items) || empty($items)) {
            return ['success' => false, 'error' => 'items vacios'];
        }

        $deliveryType = strtolower((string) ($payload['delivery_type'] ?? 'delivery'));
        if (!in_array($deliveryType, ['delivery','pickup','dine_in'], true)) $deliveryType = 'delivery';

        $payment = strtolower((string) ($payload['payment'] ?? 'cash'));
        if (!in_array($payment, ['cash','card','transfer','online','other'], true)) $payment = 'cash';

        $tenant = Tenant::findById($this->tenantId);
        $settings = !empty($tenant['restaurant_settings'])
            ? (json_decode((string) $tenant['restaurant_settings'], true) ?: [])
            : [];
        $currency = (string) ($settings['currency'] ?? $tenant['currency'] ?? 'USD');
        $taxRate  = (float) ($settings['tax_rate'] ?? 0);

        $zoneId = null;
        $deliveryFee = 0.0;
        if ($deliveryType === 'delivery' && !empty($payload['zone'])) {
            $zone = $this->resolveZoneByName((string) $payload['zone']);
            if ($zone) {
                $zoneId = (int) $zone['id'];
                $deliveryFee = (float) $zone['fee'];
            }
        }

        $contact = $contactId ? Database::fetch("SELECT phone, whatsapp, first_name, last_name FROM contacts WHERE id = :c AND tenant_id = :t", ['c' => $contactId, 't' => $this->tenantId]) : null;

        // Crear orden cabecera
        $orderId = Order::create([
            'tenant_id'        => $this->tenantId,
            'contact_id'       => $contactId ?: null,
            'conversation_id'  => $conversationId ?: null,
            'customer_name'    => trim((string) ($payload['customer_name'] ?? ($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''))) ?: 'Cliente',
            'customer_phone'   => trim((string) ($payload['customer_phone'] ?? ($contact['whatsapp'] ?? $contact['phone'] ?? ''))) ?: null,
            'delivery_type'    => $deliveryType,
            'delivery_zone_id' => $zoneId,
            'delivery_address' => trim((string) ($payload['address'] ?? '')) ?: null,
            'delivery_notes'   => trim((string) ($payload['notes'] ?? '')) ?: null,
            'kitchen_notes'    => trim((string) ($payload['kitchen_notes'] ?? '')) ?: null,
            'status'           => !empty($settings['auto_accept']) ? 'confirmed' : 'new',
            'payment_method'   => $payment,
            'payment_status'   => 'pending',
            'delivery_fee'     => $deliveryFee,
            'currency'         => $currency,
            'is_ai_generated'  => 1,
            'metadata'         => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        $subtotal = 0.0;
        foreach ($items as $line) {
            if (!is_array($line)) continue;
            $name = trim((string) ($line['name'] ?? ''));
            $qty  = max(1, (int) ($line['qty'] ?? $line['quantity'] ?? 1));
            $price = isset($line['unit_price']) ? (float) $line['unit_price'] : null;
            $itemId = null;

            if ($name !== '') {
                $found = MenuItem::findByNameFuzzy($this->tenantId, $name);
                if ($found) {
                    $itemId = (int) $found['id'];
                    if ($price === null) $price = (float) $found['price'];
                    $name = (string) $found['name']; // usar nombre canonico
                }
            }
            if ($price === null) $price = 0.0;

            // Modifiers extra price
            $modPrice = 0.0;
            $modifiers = [];
            if (!empty($line['modifiers']) && is_array($line['modifiers'])) {
                foreach ($line['modifiers'] as $m) {
                    if (!is_array($m)) continue;
                    $modName = trim((string) ($m['name'] ?? ''));
                    $modVal  = (float) ($m['price'] ?? 0);
                    if ($modName !== '') {
                        $modifiers[] = ['name' => $modName, 'price' => $modVal];
                        $modPrice += $modVal;
                    }
                }
            }

            $unitTotal = $price + $modPrice;
            $lineSubtotal = $unitTotal * $qty;
            $subtotal += $lineSubtotal;

            Database::insert('order_items', [
                'tenant_id'    => $this->tenantId,
                'order_id'     => $orderId,
                'menu_item_id' => $itemId,
                'name'         => $name !== '' ? $name : 'Item sin nombre',
                'qty'          => $qty,
                'unit_price'   => $unitTotal,
                'subtotal'     => $lineSubtotal,
                'modifiers'    => !empty($modifiers) ? json_encode($modifiers, JSON_UNESCAPED_UNICODE) : null,
                'notes'        => trim((string) ($line['notes'] ?? '')) ?: null,
            ]);
        }

        // Tax como porcentaje
        $tax = $taxRate > 0 ? round($subtotal * ($taxRate / 100), 2) : 0;
        $total = max(0, $subtotal + $deliveryFee + $tax);

        Order::update($this->tenantId, $orderId, [
            'subtotal' => $subtotal,
            'tax'      => $tax,
            'total'    => $total,
        ]);

        Database::insert('order_events', [
            'tenant_id' => $this->tenantId,
            'order_id'  => $orderId,
            'event'     => 'created',
            'to_status' => 'new',
            'note'      => 'Orden creada por IA desde conversacion #' . $conversationId,
        ]);

        Logger::info('AI order creada', ['tenant' => $this->tenantId, 'order' => $orderId, 'total' => $total]);

        return ['success' => true, 'order_id' => $orderId];
    }

    /**
     * @param string $ref codigo OR-... o id numerico
     */
    public function updateStatusByRef(string $ref, string $newStatus): bool
    {
        $orderId = $this->resolveOrderId($ref);
        if (!$orderId) return false;
        return Order::transitionStatus($this->tenantId, $orderId, $newStatus, null, 'Cambio aplicado por IA');
    }

    public function generatePaymentLink(string $ref, int $conversationId): ?string
    {
        $orderId = $this->resolveOrderId($ref);
        if (!$orderId) return null;
        $order = Order::findById($this->tenantId, $orderId);
        if (!$order) return null;

        // Si hay integracion stripe configurada en la BD, generar link real
        $url = $this->stripePaymentUrl($order);
        if ($url) {
            Order::update($this->tenantId, $orderId, ['payment_link' => $url]);
            // Enviar al cliente
            if (!empty($order['customer_phone']) || !empty($order['contact_phone'])) {
                $phone = (string) ($order['customer_phone'] ?? $order['contact_phone']);
                try {
                    (new ChannelDispatcher($this->tenantId))->sendText(
                        $phone,
                        "Aqui tu link de pago para la orden #{$order['code']}: $url\nTotal: {$order['currency']} " . number_format((float) $order['total'], 2),
                        $order['channel_id'] ? (int) $order['channel_id'] : null
                    );
                } catch (\Throwable) {}
            }
            return $url;
        }
        return null;
    }

    private function resolveOrderId(string $ref): ?int
    {
        $ref = trim($ref);
        if ($ref === '') return null;
        if (ctype_digit($ref)) {
            $row = Database::fetch("SELECT id FROM orders WHERE id = :id AND tenant_id = :t", ['id' => (int) $ref, 't' => $this->tenantId]);
            return $row ? (int) $row['id'] : null;
        }
        $row = Database::fetch("SELECT id FROM orders WHERE code = :c AND tenant_id = :t", ['c' => $ref, 't' => $this->tenantId]);
        return $row ? (int) $row['id'] : null;
    }

    private function resolveZoneByName(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') return null;
        return Database::fetch(
            "SELECT * FROM delivery_zones WHERE tenant_id = :t AND is_active = 1 AND LOWER(name) LIKE :n LIMIT 1",
            ['t' => $this->tenantId, 'n' => '%' . mb_strtolower($name) . '%']
        );
    }

    /**
     * Genera URL de pago Stripe via integracion configurada por tenant.
     * Devuelve null si Stripe no esta configurado.
     */
    private function stripePaymentUrl(array $order): ?string
    {
        try {
            $integration = Database::fetch(
                "SELECT credentials FROM integrations WHERE tenant_id = :t AND slug = 'stripe' AND status = 'connected'",
                ['t' => $this->tenantId]
            );
            if (!$integration) return null;
            $creds = json_decode((string) ($integration['credentials'] ?? '{}'), true) ?: [];
            $secret = (string) ($creds['secret_key'] ?? '');
            if ($secret === '') return null;

            $form = http_build_query([
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower((string) ($order['currency'] ?? 'usd')),
                        'product_data' => ['name' => 'Orden ' . $order['code']],
                        'unit_amount' => (int) round(((float) $order['total']) * 100),
                    ],
                    'quantity' => 1,
                ]],
                'metadata' => [
                    'order_id'   => (string) $order['id'],
                    'order_code' => (string) $order['code'],
                ],
            ]);
            $resp = HttpClient::post(
                'https://api.stripe.com/v1/payment_links',
                $form,
                [
                    'Authorization' => 'Bearer ' . $secret,
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ],
                30
            );
            if (!empty($resp['success']) && !empty($resp['body']['url'])) {
                return (string) $resp['body']['url'];
            }
        } catch (\Throwable $e) {
            Logger::error('Stripe payment_link fallo', ['msg' => $e->getMessage()]);
        }
        return null;
    }
}
