<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\Request;
use App\Models\Order;

/**
 * Endpoints de ordenes.
 *
 *   GET    /api/v1/orders             listar (?status=, ?contact_id=, ?page=)
 *   GET    /api/v1/orders/{id}        detalle (con items y eventos)
 *   PATCH  /api/v1/orders/{id}/status cambiar status (transicion validada)
 */
final class ApiOrderController extends ApiController
{
    public function index(Request $request): void
    {
        $tenantId = $this->tenantId();
        [$page, $perPage, $offset] = $this->pagination($request, 25, 100);

        $filters = [];
        if ($s = (string) $request->query('status', '')) {
            $filters['status'] = $s;
        }
        if ($c = (int) $request->query('contact_id', 0)) {
            $filters['contact_id'] = $c;
        }

        $rows = Order::listFiltered($tenantId, $filters, $perPage);
        $items = array_map(fn($r) => $this->transform($r), $rows);
        $this->paginated($items, $page, $perPage);
    }

    public function show(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        $row = Order::findById($tenantId, $id);
        if (!$row) {
            $this->error('not_found', 'Order not found.', 404);
        }
        $items  = Order::items($tenantId, $id);
        $events = Order::events($tenantId, $id, 50);
        $out = $this->transform($row);
        $out['items']  = $items;
        $out['events'] = $events;
        $this->ok($out);
    }

    public function updateStatus(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        $clean = $this->validateApi($request, [
            'status' => 'required|string|in:new,confirmed,preparing,ready,out_for_delivery,delivered,cancelled',
            'note'   => 'string|max:500',
        ]);

        $row = Order::findById($tenantId, $id);
        if (!$row) {
            $this->error('not_found', 'Order not found.', 404);
        }

        $ok = Order::transitionStatus($tenantId, $id, (string) $clean['status'], null, (string) ($clean['note'] ?? ''));
        if (!$ok) {
            $this->error('invalid_transition', 'Status transition is not allowed.', 409, [
                'from' => (string) $row['status'],
                'to'   => (string) $clean['status'],
            ]);
        }

        $row = Order::findById($tenantId, $id);
        $this->ok($this->transform($row ?? []));
    }

    private function transform(array $o): array
    {
        return [
            'id'              => (int)    ($o['id'] ?? 0),
            'code'            => (string) ($o['code'] ?? ''),
            'status'          => (string) ($o['status'] ?? ''),
            'contact_id'      => (int)    ($o['contact_id'] ?? 0),
            'customer_name'   => trim((string) ($o['first_name'] ?? '') . ' ' . (string) ($o['last_name'] ?? '')),
            'customer_phone'  => $o['contact_phone'] ?? $o['whatsapp'] ?? null,
            'delivery_type'   => $o['delivery_type'] ?? null,
            'delivery_zone'   => $o['zone_name']     ?? null,
            'delivery_fee'    => isset($o['delivery_fee']) ? (float) $o['delivery_fee'] : null,
            'subtotal'        => isset($o['subtotal']) ? (float) $o['subtotal'] : 0.0,
            'tax'             => isset($o['tax_amount']) ? (float) $o['tax_amount'] : 0.0,
            'total'           => isset($o['total']) ? (float) $o['total'] : 0.0,
            'currency'        => (string) ($o['currency'] ?? 'USD'),
            'note'            => $o['notes'] ?? null,
            'address'         => $o['delivery_address'] ?? null,
            'created_at'      => $o['created_at'] ?? null,
            'updated_at'      => $o['updated_at'] ?? null,
        ];
    }
}
