<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Tenant;
use App\Core\Auth;
use App\Models\Order;

/**
 * Kitchen Display System (KDS) en tiempo real para tenants con
 * is_restaurant = 1. Muestra ordenes activas (no entregadas / no canceladas)
 * con auto-refresh por polling cada N segundos y permite transicionar
 * estados en linea.
 *
 * Rutas:
 *   GET  /kitchen              -> vista full-page
 *   GET  /kitchen/feed         -> JSON con ordenes + items + KPIs (polling)
 *   POST /kitchen/{id}/status  -> transicion rapida de estado (CSRF)
 */
final class KitchenController extends Controller
{
    /** Status que muestra la cocina por defecto (no entregados/cancelados). */
    private const ACTIVE_STATUSES = ['new','confirmed','preparing','ready','out_for_delivery'];

    public function index(Request $request): void
    {
        $this->ensureRestaurant();

        $this->view('kitchen.index', [
            'page'      => 'kitchen',
            'statuses'  => Order::STATUSES,
            'statusFlow'=> Order::STATUS_FLOW,
        ], 'layouts.app');
    }

    /**
     * Endpoint JSON consumido por el front cada N segundos.
     * Devuelve KPIs por estado + lista de ordenes activas con items.
     */
    public function feed(Request $request): void
    {
        $this->ensureRestaurant();

        $tenantId = Tenant::id();
        $showAll  = (string) $request->query('show', '') === 'all';
        $filterStatus = trim((string) $request->query('status', ''));

        $orders = $this->fetchActiveOrders($tenantId, $showAll, $filterStatus);

        // KPIs por estado (siempre del set activo, ignora filterStatus)
        $kpiRows = Database::fetchAll(
            "SELECT status, COUNT(*) AS c
               FROM orders
              WHERE tenant_id = :t
                AND status IN ('new','confirmed','preparing','ready','out_for_delivery')
              GROUP BY status",
            ['t' => $tenantId]
        );
        $kpis = ['new'=>0,'confirmed'=>0,'preparing'=>0,'ready'=>0,'out_for_delivery'=>0];
        foreach ($kpiRows as $r) {
            $kpis[(string) $r['status']] = (int) $r['c'];
        }

        // Entregadas hoy (para meter en header)
        $deliveredToday = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM orders
              WHERE tenant_id = :t AND status = 'delivered'
                AND DATE(delivered_at) = CURDATE()",
            ['t' => $tenantId]
        );

        // Hidratar items por orden en bulk
        $items = [];
        if ($orders) {
            $ids = array_column($orders, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge($ids, [$tenantId]);
            $rows = Database::fetchAll(
                "SELECT id, order_id, name, qty, unit_price, subtotal, modifiers, notes
                   FROM order_items
                  WHERE order_id IN ($placeholders) AND tenant_id = ?
                  ORDER BY id ASC",
                $params
            );
            foreach ($rows as $it) {
                $items[(int) $it['order_id']][] = [
                    'id'         => (int) $it['id'],
                    'name'       => (string) $it['name'],
                    'qty'        => (int) $it['qty'],
                    'unit_price' => (float) $it['unit_price'],
                    'subtotal'   => (float) $it['subtotal'],
                    'notes'      => (string) ($it['notes'] ?? ''),
                    'modifiers'  => $it['modifiers'] ? (json_decode((string) $it['modifiers'], true) ?: []) : [],
                ];
            }
        }

        $payload = [];
        $now = time();
        foreach ($orders as $o) {
            $oid = (int) $o['id'];
            $created = strtotime((string) $o['created_at']);
            $elapsed = max(0, $now - $created);
            $status  = (string) $o['status'];
            $allowed = Order::STATUS_FLOW[$status] ?? [];

            $payload[] = [
                'id'             => $oid,
                'code'           => (string) $o['code'],
                'status'         => $status,
                'customer_name'  => $this->customerName($o),
                'customer_phone' => (string) ($o['customer_phone'] ?? ''),
                'delivery_type'  => (string) ($o['delivery_type'] ?? 'pickup'),
                'delivery_address' => (string) ($o['delivery_address'] ?? ''),
                'delivery_notes' => (string) ($o['delivery_notes'] ?? ''),
                'kitchen_notes'  => (string) ($o['kitchen_notes'] ?? ''),
                'payment_method' => (string) ($o['payment_method'] ?? ''),
                'payment_status' => (string) ($o['payment_status'] ?? ''),
                'total'          => (float) ($o['total'] ?? 0),
                'currency'       => (string) ($o['currency'] ?? 'USD'),
                'is_ai'          => !empty($o['is_ai_generated']),
                'created_at'     => (string) $o['created_at'],
                'elapsed_sec'    => $elapsed,
                'elapsed_label'  => $this->elapsedLabel($elapsed),
                'urgency'        => $this->urgencyLevel($status, $elapsed, (int) ($o['prep_time_min'] ?? 0)),
                'next_states'    => array_values($allowed),
                'items'          => $items[$oid] ?? [],
            ];
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'              => true,
            'server_time'     => date('c'),
            'kpis'            => $kpis,
            'delivered_today' => $deliveredToday,
            'orders'          => $payload,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Transicion rapida desde la KDS. Reusa Order::transitionStatus para
     * validar el flow y emitir notificaciones / eventos.
     */
    public function transition(Request $request, array $params): void
    {
        $this->ensureRestaurant();

        $tenantId = Tenant::id();
        $orderId  = (int) ($params['id'] ?? 0);
        $newStatus = (string) $request->input('status', '');
        $note = trim((string) $request->input('note', ''));

        $ok = Order::transitionStatus($tenantId, $orderId, $newStatus, Auth::id(), $note ?: null);

        // Si el request es AJAX, JSON. Sino, redirect a la KDS.
        if ($request->expectsJson() || (string) $request->header('X-Requested-With') === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => $ok]);
            return;
        }

        if ($ok) {
            Session::flash('success', 'Orden actualizada.');
        } else {
            Session::flash('error', 'No se pudo cambiar el estado.');
        }
        $this->redirect('/kitchen');
    }

    // ----------------------------------------------------------------------

    private function fetchActiveOrders(int $tenantId, bool $showAll, string $filterStatus): array
    {
        if ($filterStatus !== '' && in_array($filterStatus, array_keys(Order::STATUSES), true)) {
            return Database::fetchAll(
                "SELECT o.*, c.first_name, c.last_name
                   FROM orders o
                   LEFT JOIN contacts c ON c.id = o.contact_id
                  WHERE o.tenant_id = :t AND o.status = :s
                  ORDER BY o.created_at DESC
                  LIMIT 100",
                ['t' => $tenantId, 's' => $filterStatus]
            );
        }

        if ($showAll) {
            return Database::fetchAll(
                "SELECT o.*, c.first_name, c.last_name
                   FROM orders o
                   LEFT JOIN contacts c ON c.id = o.contact_id
                  WHERE o.tenant_id = :t AND DATE(o.created_at) = CURDATE()
                  ORDER BY o.created_at DESC
                  LIMIT 200",
                ['t' => $tenantId]
            );
        }

        $placeholders = "'" . implode("','", self::ACTIVE_STATUSES) . "'";
        return Database::fetchAll(
            "SELECT o.*, c.first_name, c.last_name
               FROM orders o
               LEFT JOIN contacts c ON c.id = o.contact_id
              WHERE o.tenant_id = :t AND o.status IN ($placeholders)
              ORDER BY o.created_at ASC
              LIMIT 100",
            ['t' => $tenantId]
        );
    }

    private function customerName(array $order): string
    {
        $fromContact = trim(((string) ($order['first_name'] ?? '')) . ' ' . ((string) ($order['last_name'] ?? '')));
        if ($fromContact !== '') return $fromContact;
        return (string) ($order['customer_name'] ?? '—');
    }

    private function elapsedLabel(int $seconds): string
    {
        if ($seconds < 60)       return $seconds . 's';
        if ($seconds < 3600)     return (int) floor($seconds / 60) . 'm';
        return (int) floor($seconds / 3600) . 'h ' . (int) floor(($seconds % 3600) / 60) . 'm';
    }

    /**
     * Niveles: ok (0) | watch (1, amarillo) | late (2, rojo).
     * Reglas pragmaticas: new > 5min = watch, > 10min = late.
     * preparing > prep_time_min = late.
     */
    private function urgencyLevel(string $status, int $elapsedSec, int $prepTimeMin): int
    {
        $elapsedMin = (int) floor($elapsedSec / 60);
        switch ($status) {
            case 'new':
                if ($elapsedMin >= 10) return 2;
                if ($elapsedMin >= 5)  return 1;
                return 0;
            case 'confirmed':
                if ($elapsedMin >= 15) return 2;
                if ($elapsedMin >= 8)  return 1;
                return 0;
            case 'preparing':
                $limit = $prepTimeMin > 0 ? $prepTimeMin : 25;
                if ($elapsedMin >= $limit) return 2;
                if ($elapsedMin >= ($limit - 5)) return 1;
                return 0;
            case 'ready':
                if ($elapsedMin >= 15) return 2;
                if ($elapsedMin >= 7)  return 1;
                return 0;
            case 'out_for_delivery':
                if ($elapsedMin >= 45) return 2;
                if ($elapsedMin >= 30) return 1;
                return 0;
        }
        return 0;
    }

    /** Aborta con 404 si el tenant no es restaurante. */
    private function ensureRestaurant(): void
    {
        $tenant = Tenant::current();
        if (empty($tenant['is_restaurant'])) {
            $this->abort(404);
        }
    }
}
