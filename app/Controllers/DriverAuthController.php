<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Models\Driver;

/**
 * Login del portal mobile del driver. Independiente del Auth principal:
 * el driver se autentica con telefono + PIN y se guarda su id en sesion
 * bajo la clave _driver_id. Asi puede coexistir con un user logueado.
 */
final class DriverAuthController extends Controller
{
    public const SESSION_KEY = '_driver_id';

    public function showLogin(Request $request): void
    {
        if (self::currentDriverId()) {
            $this->redirect('/driver/portal');
            return;
        }
        $this->view('driver.login', [
            'page' => 'driver_login',
        ], 'layouts.driver');
    }

    public function login(Request $request): void
    {
        if (!Csrf::validate((string) $request->input('_csrf', ''))) {
            $this->redirect('/driver/login');
            return;
        }
        $phone = trim((string) $request->input('phone', ''));
        $pin   = trim((string) $request->input('pin', ''));
        if ($phone === '' || $pin === '') {
            Session::flash('error', 'Ingresa telefono y PIN.');
            $this->redirect('/driver/login');
            return;
        }
        $driver = Driver::authenticate($phone, $pin);
        if (!$driver) {
            Session::flash('error', 'Credenciales invalidas.');
            $this->redirect('/driver/login');
            return;
        }
        Session::set(self::SESSION_KEY, (int) $driver['id']);
        Session::regenerate();
        // Auto-online al login
        Database::update('drivers', [
            'status' => 'online',
            'last_ping_at' => date('Y-m-d H:i:s'),
        ], ['id' => (int) $driver['id']]);
        $this->redirect('/driver/portal');
    }

    public function logout(Request $request): void
    {
        $id = self::currentDriverId();
        if ($id) {
            Database::update('drivers', ['status' => 'offline'], ['id' => $id]);
        }
        Session::forget(self::SESSION_KEY);
        $this->redirect('/driver/login');
    }

    public static function currentDriverId(): ?int
    {
        $id = Session::get(self::SESSION_KEY);
        return $id ? (int) $id : null;
    }

    /**
     * Carga el driver actual o aborta. Reutilizable desde otros controllers.
     */
    public static function currentDriver(): ?array
    {
        $id = self::currentDriverId();
        if (!$id) return null;
        return Database::fetch("SELECT * FROM drivers WHERE id = :id AND deleted_at IS NULL", ['id' => $id]);
    }
}
