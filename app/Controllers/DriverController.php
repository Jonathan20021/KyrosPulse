<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\Tenant;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\DriverShift;

/**
 * Gestion de drivers (lado admin del tenant restaurante). CRUD basico,
 * activacion/suspension, perfil de comisiones y vista de stats.
 */
final class DriverController extends Controller
{
    public function index(Request $request): void
    {
        $this->ensureRestaurant();
        $tenantId = Tenant::id();
        $drivers = Driver::listForTenant($tenantId, [
            'status' => (string) $request->query('status', 'all'),
            'q'      => (string) $request->query('q', ''),
        ]);
        $this->view('drivers.index', [
            'page'     => 'drivers',
            'drivers'  => $drivers,
            'statuses' => Driver::STATUSES,
        ], 'layouts.app');
    }

    public function create(Request $request): void
    {
        $this->ensureRestaurant();
        $this->view('drivers.create', [
            'page'        => 'drivers',
            'commissions' => Driver::COMMISSION_TYPES,
        ], 'layouts.app');
    }

    public function store(Request $request): void
    {
        $this->ensureRestaurant();
        $tenantId = Tenant::id();

        $phone = trim((string) $request->input('phone', ''));
        $name  = trim((string) $request->input('name', ''));
        $pin   = trim((string) $request->input('pin', ''));
        if ($name === '' || $phone === '' || strlen($pin) < 4 || !ctype_digit($pin)) {
            Session::flash('error', 'Nombre, telefono y PIN (4+ digitos) son requeridos.');
            $this->redirect('/drivers/create');
            return;
        }
        if (Driver::findByPhone($tenantId, $phone)) {
            Session::flash('error', 'Ya existe un driver con ese telefono.');
            $this->redirect('/drivers/create');
            return;
        }

        $id = Driver::create([
            'tenant_id'         => $tenantId,
            'name'              => $name,
            'phone'             => $phone,
            'email'             => trim((string) $request->input('email', '')) ?: null,
            'pin'               => $pin,
            'vehicle_type'      => (string) $request->input('vehicle_type', 'motorcycle'),
            'vehicle_plate'     => trim((string) $request->input('vehicle_plate', '')) ?: null,
            'commission_type'   => (string) $request->input('commission_type', 'flat'),
            'commission_flat'   => (float) $request->input('commission_flat', 0),
            'commission_percent'=> (float) $request->input('commission_percent', 0),
            'commission_per_km' => (float) $request->input('commission_per_km', 0),
            'is_active'         => 1,
            'status'            => 'offline',
        ]);
        Audit::log('driver.created', 'driver', $id);
        Session::flash('success', 'Driver creado. PIN provisto al repartidor.');
        $this->redirect('/drivers/' . $id);
    }

    public function show(Request $request, array $params): void
    {
        $this->ensureRestaurant();
        $tenantId = Tenant::id();
        $driver = Driver::findById($tenantId, (int) ($params['id'] ?? 0));
        if (!$driver) $this->abort(404);

        $active = Delivery::listActiveForDriver((int) $driver['id']);
        $history = Delivery::listHistoryForDriver((int) $driver['id'], 25);
        $shift = DriverShift::currentForDriver((int) $driver['id']);
        $shifts = DriverShift::listForDriver((int) $driver['id'], 14);

        $this->view('drivers.show', [
            'page'        => 'drivers',
            'driver'      => $driver,
            'active'      => $active,
            'history'     => $history,
            'current_shift' => $shift,
            'shifts'      => $shifts,
            'stats'       => Driver::stats($tenantId, (int) $driver['id']),
            'commissions' => Driver::COMMISSION_TYPES,
            'statuses'    => Driver::STATUSES,
        ], 'layouts.app');
    }

    public function update(Request $request, array $params): void
    {
        $this->ensureRestaurant();
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);

        $data = [
            'name'              => trim((string) $request->input('name', '')),
            'phone'             => trim((string) $request->input('phone', '')),
            'email'             => trim((string) $request->input('email', '')) ?: null,
            'vehicle_type'      => (string) $request->input('vehicle_type', 'motorcycle'),
            'vehicle_plate'     => trim((string) $request->input('vehicle_plate', '')) ?: null,
            'commission_type'   => (string) $request->input('commission_type', 'flat'),
            'commission_flat'   => (float) $request->input('commission_flat', 0),
            'commission_percent'=> (float) $request->input('commission_percent', 0),
            'commission_per_km' => (float) $request->input('commission_per_km', 0),
            'notes'             => trim((string) $request->input('notes', '')) ?: null,
        ];
        $newPin = trim((string) $request->input('pin', ''));
        if ($newPin !== '') {
            $data['pin'] = $newPin;
        }
        Driver::update($tenantId, $id, $data);
        Audit::log('driver.updated', 'driver', $id);
        Session::flash('success', 'Driver actualizado.');
        $this->redirect('/drivers/' . $id);
    }

    public function toggle(Request $request, array $params): void
    {
        $this->ensureRestaurant();
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $driver = Driver::findById($tenantId, $id);
        if (!$driver) $this->abort(404);
        Driver::update($tenantId, $id, [
            'is_active' => $driver['is_active'] ? 0 : 1,
            'status'    => $driver['is_active'] ? 'suspended' : 'offline',
        ]);
        Session::flash('success', 'Estado del driver actualizado.');
        $this->redirect('/drivers/' . $id);
    }

    public function destroy(Request $request, array $params): void
    {
        $this->ensureRestaurant();
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        Driver::softDelete($tenantId, $id);
        Session::flash('success', 'Driver eliminado.');
        $this->redirect('/drivers');
    }

    private function ensureRestaurant(): void
    {
        $tenant = Tenant::current();
        if (empty($tenant['is_restaurant'])) {
            $this->abort(404);
        }
    }
}
