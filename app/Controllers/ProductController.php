<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\Tenant;
use App\Models\Product;

final class ProductController extends Controller
{
    public function index(Request $request): void
    {
        $tenantId = Tenant::id();
        $this->view('products.index', [
            'page'     => 'productos',
            'products' => Product::listForTenant($tenantId),
        ], 'layouts.app');
    }

    public function store(Request $request): void
    {
        $tenantId = Tenant::id();
        $data = $this->validate($request, [
            'name'  => 'required|min:2|max:180',
            'price' => 'required',
        ]);

        Product::create([
            'tenant_id'   => $tenantId,
            'name'        => $data['name'],
            'sku'         => trim((string) $request->input('sku', '')) ?: null,
            'category'    => trim((string) $request->input('category', '')) ?: null,
            'description' => trim((string) $request->input('description', '')),
            'price'       => (float) $data['price'],
            'currency'    => (string) ($request->input('currency') ?: 'USD'),
            'cost'        => $request->input('cost') !== '' ? (float) $request->input('cost') : null,
            'stock'       => $request->input('stock') !== '' ? (int) $request->input('stock') : null,
            'is_active'   => !empty($request->input('is_active')) ? 1 : 0,
            'priority'    => (int) ($request->input('priority') ?: 0),
            'created_by'  => Auth::id(),
        ]);

        Session::flash('success', 'Producto creado.');
        $this->redirect('/products');
    }

    public function update(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        if (!Product::findById($tenantId, $id)) $this->abort(404);

        $data = [
            'name'        => trim((string) $request->input('name')),
            'sku'         => trim((string) $request->input('sku', '')) ?: null,
            'category'    => trim((string) $request->input('category', '')) ?: null,
            'description' => trim((string) $request->input('description', '')),
            'price'       => (float) $request->input('price'),
            'currency'    => (string) ($request->input('currency') ?: 'USD'),
            'cost'        => $request->input('cost') !== '' ? (float) $request->input('cost') : null,
            'stock'       => $request->input('stock') !== '' ? (int) $request->input('stock') : null,
            'is_active'   => !empty($request->input('is_active')) ? 1 : 0,
            'priority'    => (int) ($request->input('priority') ?: 0),
        ];
        Product::update($tenantId, $id, $data);
        Session::flash('success', 'Producto actualizado.');
        $this->redirect('/products');
    }

    public function destroy(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        Product::softDelete($tenantId, (int) ($params['id'] ?? 0));
        Session::flash('success', 'Producto eliminado.');
        $this->redirect('/products');
    }
}
