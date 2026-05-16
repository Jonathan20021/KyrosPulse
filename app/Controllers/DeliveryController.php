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
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\DriverPayout;
use App\Models\Order;
use App\Services\DeliveryService;

/**
 * Panel de despacho (dispatcher) para el tenant restaurante. Permite ver
 * deliveries pendientes/activas, asignar drivers manualmente, monitorear
 * tracking en tiempo real y gestionar payouts a repartidores.
 */
final class DeliveryController extends Controller
{
    public function index(Request $request): void
    {
        $this->ensureRestaurant();
        $tenantId = Tenant::id();

        $filters = [
            'status'    => (string) $request->query('status', 'active'),
            'driver_id' => $request->query('driver_id') ? (int) $request->query('driver_id') : null,
        ];
        if ($filters['status'] === 'active') {
            // Vista por defecto = todo lo en curso (no entregado/cancelado)
            $rows = Delivery::listFiltered($tenantId, [], 200);
            $rows = array_filter($rows, fn($r) => in_array($r['status'], ['pending','assigned','accepted','picked_up','arriving'], true));
            $rows = array_values($rows);
        } else {
            $rows = Delivery::listFiltered($tenantId, $filters, 200);
        }

        $this->view('delivery.index', [
            'page'     => 'delivery',
            'rows'     => $rows,
            'stats'    => Delivery::dispatcherStats($tenantId),
            'drivers'  => Driver::listForTenant($tenantId, ['active_only' => true]),
            'statuses' => Delivery::STATUSES,
            'filters'  => $filters,
        ], 'layouts.app');
    }

    public function show(Request $request, array $params): void
    {
        $this->ensureRestaurant();
        $tenantId = Tenant::id();
        $delivery = Delivery::findById($tenantId, (int) ($params['id'] ?? 0));
        if (!$delivery) $this->abort(404);

        $this->view('delivery.show', [
            'page'     => 'delivery',
            'delivery' => $delivery,
            'order'    => Order::findById($tenantId, (int) $delivery['order_id']),
            'drivers'  => Driver::listForTenant($tenantId, ['active_only' => true]),
            'statuses' => Delivery::STATUSES,
            'flow'     => Delivery::STATUS_FLOW,
            'trail'    => \App\Models\DeliveryLocation::trailForDelivery((int) $delivery['id']),
        ], 'layouts.app');
    }

    /**
     * Endpoint JSON consumido por el dispatcher (auto-refresh cada 10s) para
     * mostrar en vivo deliveries activas + KPIs + drivers online.
     */
    public function liveFeed(Request $request): void
    {
        $this->ensureRestaurant();
        $tenantId = Tenant::id();

        $rows = Delivery::listFiltered($tenantId, [], 200);
        $active = array_values(array_filter($rows, fn($r) => in_array($r['status'], ['pending','assigned','accepted','picked_up','arriving'], true)));

        $this->json([
            'success'        => true,
            'server_time'    => date('c'),
            'stats'          => Delivery::dispatcherStats($tenantId),
            'deliveries'     => $active,
            'drivers_online' => Driver::listAvailable($tenantId),
        ]);
    }

    /**
     * Asignar un driver manualmente (desde el dispatcher).
     * POST /delivery/{id}/assign  { driver_id }
     */
    public function assign(Request $request, array $params): void
    {
        $this->ensureRestaurant();
        $tenantId = Tenant::id();
        $deliveryId = (int) ($params['id'] ?? 0);
        $driverId = (int) $request->input('driver_id', 0);
        if (!$driverId) {
            $this->jsonOrRedirect($request, false, 'Driver requerido.', '/delivery/' . $deliveryId);
            return;
        }
        $svc = new DeliveryService($tenantId);
        $ok = $svc->assignDriver($deliveryId, $driverId);
        Audit::log('delivery.assigned', 'delivery', $deliveryId, [], ['driver_id' => $driverId]);
        $this->jsonOrRedirect($request, $ok, $ok ? 'Driver asignado.' : 'No se pudo asignar.', '/delivery/' . $deliveryId);
    }

    /**
     * Auto-asignacion: elige el mejor driver disponible.
     * POST /delivery/{id}/auto-assign
     */
    public function autoAssign(Request $request, array $params): void
    {
        $this->ensureRestaurant();
        $tenantId = Tenant::id();
        $deliveryId = (int) ($params['id'] ?? 0);
        $svc = new DeliveryService($tenantId);
        $driverId = $svc->autoAssign($deliveryId);
        if (!$driverId) {
            $this->jsonOrRedirect($request, false, 'No hay drivers disponibles.', '/delivery');
            return;
        }
        Audit::log('delivery.auto_assigned', 'delivery', $deliveryId, [], ['driver_id' => $driverId]);
        $this->jsonOrRedirect($request, true, 'Driver asignado automaticamente.', '/delivery/' . $deliveryId);
    }

    /**
     * Cambiar estado manualmente desde el dispatcher (override).
     * POST /delivery/{id}/status  { status, reason?, cash_collected? }
     */
    public function status(Request $request, array $params): void
    {
        $this->ensureRestaurant();
        $tenantId = Tenant::id();
        $deliveryId = (int) ($params['id'] ?? 0);
        $newStatus = (string) $request->input('status', '');
        $extra = [
            'reason'         => (string) $request->input('reason', ''),
            'cash_collected' => $request->input('cash_collected'),
        ];
        $svc = new DeliveryService($tenantId);
        $ok = $svc->transition($deliveryId, $newStatus, Auth::id(), $extra);
        Audit::log('delivery.status', 'delivery', $deliveryId, [], ['status' => $newStatus]);
        $this->jsonOrRedirect($request, $ok, $ok ? 'Estado actualizado.' : 'Transicion no permitida.', '/delivery/' . $deliveryId);
    }

    // ----------------------------------------------------------------------
    //  Payouts
    // ----------------------------------------------------------------------

    public function payouts(Request $request): void
    {
        $this->ensureRestaurant();
        $tenantId = Tenant::id();
        $rows = DriverPayout::listForTenant($tenantId, [
            'driver_id' => $request->query('driver_id') ? (int) $request->query('driver_id') : null,
            'status'    => (string) $request->query('status', 'all'),
        ]);
        $this->view('delivery.payouts', [
            'page'    => 'delivery',
            'rows'    => $rows,
            'drivers' => Driver::listForTenant($tenantId, ['active_only' => true]),
        ], 'layouts.app');
    }

    /**
     * Generar payouts para un driver (o todos) en un rango de fechas.
     * POST /delivery/payouts/generate  { driver_id?, from, to }
     */
    public function payoutsGenerate(Request $request): void
    {
        $this->ensureRestaurant();
        $tenantId = Tenant::id();
        $from = (string) $request->input('from', date('Y-m-d', strtotime('-7 days')));
        $to   = (string) $request->input('to', date('Y-m-d'));
        $driverId = $request->input('driver_id') ? (int) $request->input('driver_id') : null;

        $svc = new DeliveryService($tenantId);
        $drivers = $driverId
            ? [['id' => $driverId]]
            : Database::fetchAll("SELECT id FROM drivers WHERE tenant_id = :t AND deleted_at IS NULL", ['t' => $tenantId]);
        $generated = 0;
        foreach ($drivers as $d) {
            if ($svc->generatePayout((int) $d['id'], $from, $to)) {
                $generated++;
            }
        }
        Session::flash('success', "Se generaron $generated payouts.");
        $this->redirect('/delivery/payouts');
    }

    public function payoutsMarkPaid(Request $request, array $params): void
    {
        $this->ensureRestaurant();
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $ok = DriverPayout::markPaid($tenantId, $id, (int) Auth::id(), [
            'payment_method' => (string) $request->input('payment_method', ''),
            'payment_ref'    => (string) $request->input('payment_ref', ''),
            'notes'          => (string) $request->input('notes', ''),
        ]);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Payout marcado como pagado.' : 'No se pudo marcar.');
        $this->redirect('/delivery/payouts');
    }

    public function payoutsCancel(Request $request, array $params): void
    {
        $this->ensureRestaurant();
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        DriverPayout::cancel($tenantId, $id);
        Session::flash('success', 'Payout cancelado.');
        $this->redirect('/delivery/payouts');
    }

    // ----------------------------------------------------------------------
    //  Helpers
    // ----------------------------------------------------------------------

    private function ensureRestaurant(): void
    {
        $tenant = Tenant::current();
        if (empty($tenant['is_restaurant'])) {
            $this->abort(404);
        }
    }

    private function jsonOrRedirect(Request $request, bool $ok, string $msg, string $redirectTo): void
    {
        if ($request->expectsJson() || (string) $request->header('X-Requested-With') === 'XMLHttpRequest') {
            $this->json(['success' => $ok, 'message' => $msg], $ok ? 200 : 422);
            return;
        }
        Session::flash($ok ? 'success' : 'error', $msg);
        $this->redirect($redirectTo);
    }
}
