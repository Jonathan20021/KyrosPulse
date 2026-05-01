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
use App\Models\MenuCategory;
use App\Models\MenuItem;

final class MenuController extends Controller
{
    public function index(Request $request): void
    {
        $tenantId = Tenant::id();
        $categories = MenuCategory::listForTenant($tenantId);
        $items      = MenuItem::listForTenant($tenantId);

        // Agrupar items por categoria para render
        $grouped = ['_uncat' => []];
        foreach ($categories as $c) $grouped[(int) $c['id']] = [];
        foreach ($items as $i) {
            $key = !empty($i['category_id']) ? (int) $i['category_id'] : '_uncat';
            $grouped[$key] ??= [];
            $grouped[$key][] = $i;
        }

        $this->view('menu.index', [
            'page'       => 'menu',
            'categories' => $categories,
            'items'      => $items,
            'grouped'    => $grouped,
            'totals'     => [
                'categories' => count($categories),
                'items'      => count($items),
                'available'  => count(array_filter($items, fn ($i) => !empty($i['is_available']))),
                'featured'   => count(array_filter($items, fn ($i) => !empty($i['is_featured']))),
            ],
        ], 'layouts.app');
    }

    // ---------- Categorias ----------
    public function categoryStore(Request $request): void
    {
        $tenantId = Tenant::id();
        $data = $this->validate($request, ['name' => 'required|min:1|max:120']);
        $id = MenuCategory::create([
            'tenant_id'   => $tenantId,
            'name'        => $data['name'],
            'description' => trim((string) $request->input('description', '')) ?: null,
            'icon'        => trim((string) $request->input('icon', '🍽')) ?: '🍽',
            'is_active'   => 1,
            'sort_order'  => (int) ($request->input('sort_order') ?: 0),
        ]);
        Audit::log('menu.category_created', 'menu_category', $id);
        Session::flash('success', 'Categoria creada.');
        $this->redirect('/menu');
    }

    public function categoryUpdate(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $existing = MenuCategory::findById($tenantId, $id);
        if (!$existing) $this->abort(404);
        MenuCategory::update($tenantId, $id, [
            'name'        => trim((string) $request->input('name', $existing['name'])),
            'description' => trim((string) $request->input('description', '')) ?: null,
            'icon'        => trim((string) $request->input('icon', $existing['icon'] ?? '🍽')) ?: '🍽',
            'is_active'   => !empty($request->input('is_active')) ? 1 : 0,
            'sort_order'  => (int) ($request->input('sort_order') ?: $existing['sort_order'] ?? 0),
        ]);
        Session::flash('success', 'Categoria actualizada.');
        $this->redirect('/menu');
    }

    public function categoryDelete(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        MenuCategory::delete($tenantId, (int) ($params['id'] ?? 0));
        Session::flash('success', 'Categoria eliminada.');
        $this->redirect('/menu');
    }

    // ---------- Items ----------
    public function itemStore(Request $request): void
    {
        $tenantId = Tenant::id();
        $data = $this->validate($request, [
            'name'  => 'required|min:1|max:160',
            'price' => 'required',
        ]);

        $modifiers = $this->parseModifiers((string) $request->input('modifiers_json', ''));

        $id = MenuItem::create([
            'tenant_id'    => $tenantId,
            'category_id'  => $request->input('category_id') ? (int) $request->input('category_id') : null,
            'sku'          => trim((string) $request->input('sku', '')) ?: null,
            'name'         => $data['name'],
            'description'  => trim((string) $request->input('description', '')) ?: null,
            'price'        => (float) $data['price'],
            'compare_price' => $request->input('compare_price') !== '' ? (float) $request->input('compare_price') : null,
            'currency'     => (string) ($request->input('currency') ?: 'USD'),
            'photo'        => trim((string) $request->input('photo', '')) ?: null,
            'prep_time_min' => $request->input('prep_time_min') !== '' ? (int) $request->input('prep_time_min') : null,
            'is_available' => !empty($request->input('is_available')) ? 1 : 1,
            'is_featured'  => !empty($request->input('is_featured')) ? 1 : 0,
            'is_combo'     => !empty($request->input('is_combo')) ? 1 : 0,
            'modifiers'    => !empty($modifiers) ? json_encode($modifiers, JSON_UNESCAPED_UNICODE) : null,
            'allergens'    => trim((string) $request->input('allergens', '')) ?: null,
            'sort_order'   => (int) ($request->input('sort_order') ?: 0),
        ]);
        Audit::log('menu.item_created', 'menu_item', $id);
        Session::flash('success', 'Articulo creado.');
        $this->redirect('/menu');
    }

    public function itemUpdate(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $existing = MenuItem::findById($tenantId, $id);
        if (!$existing) $this->abort(404);

        $modifiers = $this->parseModifiers((string) $request->input('modifiers_json', ''));

        MenuItem::update($tenantId, $id, [
            'category_id'  => $request->input('category_id') ? (int) $request->input('category_id') : null,
            'sku'          => trim((string) $request->input('sku', $existing['sku'] ?? '')) ?: null,
            'name'         => trim((string) $request->input('name', $existing['name'])),
            'description'  => trim((string) $request->input('description', '')) ?: null,
            'price'        => (float) ($request->input('price') ?: $existing['price']),
            'compare_price' => $request->input('compare_price') !== '' ? (float) $request->input('compare_price') : null,
            'currency'     => (string) ($request->input('currency') ?: ($existing['currency'] ?? 'USD')),
            'photo'        => trim((string) $request->input('photo', $existing['photo'] ?? '')) ?: null,
            'prep_time_min' => $request->input('prep_time_min') !== '' ? (int) $request->input('prep_time_min') : null,
            'is_available' => !empty($request->input('is_available')) ? 1 : 0,
            'is_featured'  => !empty($request->input('is_featured')) ? 1 : 0,
            'is_combo'     => !empty($request->input('is_combo')) ? 1 : 0,
            'modifiers'    => !empty($modifiers) ? json_encode($modifiers, JSON_UNESCAPED_UNICODE) : null,
            'allergens'    => trim((string) $request->input('allergens', '')) ?: null,
            'sort_order'   => (int) ($request->input('sort_order') ?: ($existing['sort_order'] ?? 0)),
        ]);
        Session::flash('success', 'Articulo actualizado.');
        $this->redirect('/menu');
    }

    public function itemToggle(Request $request, array $params): void
    {
        MenuItem::toggle(Tenant::id(), (int) ($params['id'] ?? 0));
        $this->redirect('/menu');
    }

    public function itemDelete(Request $request, array $params): void
    {
        MenuItem::softDelete(Tenant::id(), (int) ($params['id'] ?? 0));
        Session::flash('success', 'Articulo eliminado.');
        $this->redirect('/menu');
    }

    private function parseModifiers(string $raw): array
    {
        if ($raw === '') return [];
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) return $decoded;

        // Formato simple "Salsa BBQ:0.50, Sin cebolla:0, Extra queso:1.50"
        $out = [];
        foreach (preg_split('/[,;\n]+/', $raw) ?: [] as $piece) {
            $piece = trim($piece);
            if ($piece === '') continue;
            $parts = explode(':', $piece);
            $name  = trim($parts[0]);
            $price = isset($parts[1]) ? (float) trim($parts[1]) : 0.0;
            if ($name !== '') $out[] = ['name' => $name, 'price' => $price];
        }
        return $out;
    }
}
