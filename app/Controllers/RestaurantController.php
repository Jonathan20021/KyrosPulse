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
use App\Models\Tenant as TenantModel;

final class RestaurantController extends Controller
{
    public function index(Request $request): void
    {
        $tenantId = Tenant::id();
        $tenant   = TenantModel::findById($tenantId);
        $settings = $this->settings($tenant);

        $this->view('settings.restaurant', [
            'page'     => 'configuracion',
            'tab'      => 'restaurant',
            'tenant'   => $tenant,
            'settings' => $settings,
            'zones'    => DeliveryZone::listForTenant($tenantId),
        ], 'layouts.app');
    }

    public function update(Request $request): void
    {
        $tenantId = Tenant::id();
        $tenant = TenantModel::findById($tenantId);
        $current = $this->settings($tenant);

        $settings = array_merge($current, [
            'tax_rate'         => (float) ($request->input('tax_rate') ?: 0),
            'tip_default'      => (float) ($request->input('tip_default') ?: 0),
            'min_order'        => (float) ($request->input('min_order') ?: 0),
            'currency'         => (string) ($request->input('currency') ?: 'USD'),
            'allow_delivery'   => !empty($request->input('allow_delivery')) ? 1 : 0,
            'allow_pickup'     => !empty($request->input('allow_pickup')) ? 1 : 0,
            'allow_dine_in'    => !empty($request->input('allow_dine_in')) ? 1 : 0,
            'payment_methods'  => array_values(array_filter((array) $request->input('payment_methods', []))),
            'order_prep_min'   => (int) ($request->input('order_prep_min') ?: 25),
            'auto_accept'      => !empty($request->input('auto_accept')) ? 1 : 0,
            'show_calories'    => !empty($request->input('show_calories')) ? 1 : 0,
            'whatsapp_menu_pdf' => trim((string) $request->input('whatsapp_menu_pdf', '')) ?: null,
            'address'          => trim((string) $request->input('address', '')) ?: null,
            'cuisine_type'     => trim((string) $request->input('cuisine_type', '')) ?: null,
        ]);

        Database::update('tenants', [
            'is_restaurant'        => !empty($request->input('is_restaurant')) ? 1 : 0,
            'restaurant_settings'  => json_encode($settings, JSON_UNESCAPED_UNICODE),
        ], ['id' => $tenantId]);

        Audit::log('restaurant.settings_updated', 'tenant', $tenantId);
        Session::flash('success', 'Configuracion del restaurante guardada.');
        $this->redirect('/settings/restaurant');
    }

    // ---------- Zonas ----------
    public function zoneStore(Request $request): void
    {
        $tenantId = Tenant::id();
        $data = $this->validate($request, [
            'name' => 'required|min:1|max:120',
            'fee'  => 'required',
        ]);
        DeliveryZone::create([
            'tenant_id' => $tenantId,
            'name'      => $data['name'],
            'fee'       => (float) $data['fee'],
            'eta_min'   => $request->input('eta_min') !== '' ? (int) $request->input('eta_min') : null,
            'min_order' => $request->input('min_order') !== '' ? (float) $request->input('min_order') : null,
            'area'      => trim((string) $request->input('area', '')) ?: null,
            'is_active' => 1,
        ]);
        Session::flash('success', 'Zona de entrega creada.');
        $this->redirect('/settings/restaurant');
    }

    public function zoneUpdate(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        DeliveryZone::update($tenantId, $id, [
            'name'      => trim((string) $request->input('name', '')),
            'fee'       => (float) ($request->input('fee') ?: 0),
            'eta_min'   => $request->input('eta_min') !== '' ? (int) $request->input('eta_min') : null,
            'min_order' => $request->input('min_order') !== '' ? (float) $request->input('min_order') : null,
            'area'      => trim((string) $request->input('area', '')) ?: null,
            'is_active' => !empty($request->input('is_active')) ? 1 : 0,
        ]);
        Session::flash('success', 'Zona actualizada.');
        $this->redirect('/settings/restaurant');
    }

    public function zoneDelete(Request $request, array $params): void
    {
        DeliveryZone::delete(Tenant::id(), (int) ($params['id'] ?? 0));
        Session::flash('success', 'Zona eliminada.');
        $this->redirect('/settings/restaurant');
    }

    private function settings(?array $tenant): array
    {
        $defaults = [
            'tax_rate'         => 18.0,
            'tip_default'      => 10.0,
            'min_order'        => 0,
            'currency'         => 'DOP',
            'allow_delivery'   => 1,
            'allow_pickup'     => 1,
            'allow_dine_in'    => 0,
            'payment_methods'  => ['cash', 'card', 'transfer'],
            'order_prep_min'   => 25,
            'auto_accept'      => 0,
            'show_calories'    => 0,
            'whatsapp_menu_pdf' => null,
            'address'          => null,
            'cuisine_type'     => null,
        ];
        $stored = !empty($tenant['restaurant_settings'])
            ? (json_decode((string) $tenant['restaurant_settings'], true) ?: [])
            : [];
        return array_merge($defaults, $stored);
    }
}
