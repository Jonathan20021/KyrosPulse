<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Session;
use App\Models\Delivery;
use App\Models\DeliveryLocation;
use App\Models\Driver;
use App\Models\DriverShift;
use App\Services\DeliveryService;

/**
 * Portal mobile del driver. Vista PWA-friendly (1 columna, sin sidebar).
 * Funcionalidad:
 *   - Ver entregas asignadas (con direccion, monto a cobrar, comision)
 *   - Aceptar/Recoger/Llegando/Entregar/Fallar
 *   - Abrir/Cerrar turno (shift)
 *   - Posicion GPS cada N segundos (background)
 *   - Historial reciente
 *   - Saldo de efectivo en mano
 */
final class DriverPortalController extends Controller
{
    public function portal(Request $request): void
    {
        $driver = $this->requireDriver();
        $active = Delivery::listActiveForDriver((int) $driver['id']);
        $history = Delivery::listHistoryForDriver((int) $driver['id'], 10);
        $shift = DriverShift::currentForDriver((int) $driver['id']);
        $stats = Driver::stats((int) $driver['tenant_id'], (int) $driver['id']);

        $this->view('driver.portal', [
            'page'    => 'driver_portal',
            'driver'  => $driver,
            'active'  => $active,
            'history' => $history,
            'shift'   => $shift,
            'stats'   => $stats,
            'statuses' => Delivery::STATUSES,
            'flow'    => Delivery::STATUS_FLOW,
        ], 'layouts.driver');
    }

    /**
     * JSON feed para el portal (polling cada ~10s). Devuelve activas + stats.
     */
    public function feed(Request $request): void
    {
        $driver = $this->requireDriver(json: true);
        $active = Delivery::listActiveForDriver((int) $driver['id']);
        $this->json([
            'success' => true,
            'driver'  => [
                'id'     => (int) $driver['id'],
                'name'   => (string) $driver['name'],
                'status' => (string) $driver['status'],
            ],
            'active'  => $active,
            'stats'   => Driver::stats((int) $driver['tenant_id'], (int) $driver['id']),
            'shift_open' => DriverShift::currentForDriver((int) $driver['id']) !== null,
            'server_time' => date('c'),
        ]);
    }

    /**
     * Cambia el estado de una delivery desde el portal (aceptar, recoger, etc.)
     * POST /driver/delivery/{id}/status  { status, cash_collected? }
     */
    public function transition(Request $request, array $params): void
    {
        $driver = $this->requireDriver(json: true);
        if (!$this->csrfOk($request)) {
            $this->json(['success' => false, 'message' => 'CSRF invalido.'], 419);
            return;
        }
        $deliveryId = (int) ($params['id'] ?? 0);
        $delivery = Delivery::findById((int) $driver['tenant_id'], $deliveryId);
        if (!$delivery || (int) $delivery['driver_id'] !== (int) $driver['id']) {
            $this->json(['success' => false, 'message' => 'No autorizado.'], 403);
            return;
        }
        $svc = new DeliveryService((int) $driver['tenant_id']);
        $newStatus = (string) $request->input('status', '');
        $extra = [
            'cash_collected' => $request->input('cash_collected'),
            'reason'         => (string) $request->input('reason', ''),
        ];
        $ok = $svc->transition($deliveryId, $newStatus, null, $extra);
        $this->json(['success' => $ok, 'status' => $newStatus]);
    }

    /**
     * Registra ping GPS del driver (background fetch desde el portal).
     * POST /driver/ping  { lat, lng, accuracy?, speed?, heading?, delivery_id? }
     */
    public function ping(Request $request): void
    {
        $driver = $this->requireDriver(json: true);
        $lat = (float) $request->input('lat', 0);
        $lng = (float) $request->input('lng', 0);
        if (abs($lat) < 0.0001 && abs($lng) < 0.0001) {
            $this->json(['success' => false], 422);
            return;
        }
        Driver::updateLocation((int) $driver['id'], $lat, $lng);

        $deliveryId = $request->input('delivery_id') ? (int) $request->input('delivery_id') : null;
        if ($deliveryId) {
            $delivery = Delivery::findById((int) $driver['tenant_id'], $deliveryId);
            if ($delivery && (int) $delivery['driver_id'] === (int) $driver['id']) {
                $svc = new DeliveryService((int) $driver['tenant_id']);
                $svc->recordLocation($deliveryId, (int) $driver['id'], $lat, $lng, [
                    'accuracy' => $request->input('accuracy'),
                    'speed_kmh'=> $request->input('speed'),
                    'heading'  => $request->input('heading'),
                ]);
            }
        }
        $this->json(['success' => true]);
    }

    public function shiftOpen(Request $request): void
    {
        $driver = $this->requireDriver();
        if (!$this->csrfOk($request)) { $this->redirect('/driver/portal'); return; }
        if (DriverShift::currentForDriver((int) $driver['id'])) {
            Session::flash('error', 'Ya tienes un turno abierto.');
            $this->redirect('/driver/portal');
            return;
        }
        $lat = $request->input('lat') ? (float) $request->input('lat') : null;
        $lng = $request->input('lng') ? (float) $request->input('lng') : null;
        DriverShift::openShift((int) $driver['tenant_id'], (int) $driver['id'], $lat, $lng);
        Database::update('drivers', ['status' => 'online'], ['id' => (int) $driver['id']]);
        Session::flash('success', 'Turno abierto. Buen viaje!');
        $this->redirect('/driver/portal');
    }

    public function shiftClose(Request $request): void
    {
        $driver = $this->requireDriver();
        if (!$this->csrfOk($request)) { $this->redirect('/driver/portal'); return; }
        $lat = $request->input('lat') ? (float) $request->input('lat') : null;
        $lng = $request->input('lng') ? (float) $request->input('lng') : null;
        DriverShift::closeShift((int) $driver['tenant_id'], (int) $driver['id'], $lat, $lng);
        Database::update('drivers', ['status' => 'offline'], ['id' => (int) $driver['id']]);
        Session::flash('success', 'Turno cerrado.');
        $this->redirect('/driver/portal');
    }

    public function setStatus(Request $request): void
    {
        $driver = $this->requireDriver(json: true);
        if (!$this->csrfOk($request)) {
            $this->json(['success' => false], 419);
            return;
        }
        $status = (string) $request->input('status', '');
        if (!in_array($status, ['online','offline'], true)) {
            $this->json(['success' => false, 'message' => 'Estado invalido.'], 422);
            return;
        }
        // No permitir cambiar a offline si tiene entregas activas
        if ($status === 'offline') {
            $active = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM deliveries WHERE driver_id = :d AND status IN ('assigned','accepted','picked_up','arriving')",
                ['d' => (int) $driver['id']]
            );
            if ($active > 0) {
                $this->json(['success' => false, 'message' => 'Tienes entregas activas.'], 422);
                return;
            }
        }
        Database::update('drivers', ['status' => $status], ['id' => (int) $driver['id']]);
        $this->json(['success' => true, 'status' => $status]);
    }

    // ----------------------------------------------------------------------
    //  Helpers
    // ----------------------------------------------------------------------

    private function requireDriver(bool $json = false): array
    {
        $driver = DriverAuthController::currentDriver();
        if (!$driver) {
            if ($json) {
                $this->json(['success' => false, 'message' => 'No autenticado.'], 401);
                exit;
            }
            $this->redirect('/driver/login');
            exit;
        }
        return $driver;
    }

    private function csrfOk(Request $request): bool
    {
        $token = (string) ($request->input('_csrf', '') ?: $request->header('X-CSRF-Token') ?: '');
        return Csrf::validate($token);
    }
}
