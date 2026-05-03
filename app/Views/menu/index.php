<?php
/** @var array $categories */
/** @var array $items */
/** @var array $grouped */
/** @var array $totals */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('components.page_header', [
    'title'    => 'Menu del restaurante',
    'subtitle' => 'Categorias, platos, modificadores y disponibilidad. La IA lee este menu para tomar pedidos.',
]); ?>

<?php
$tenantInfo = tenant();
$publicMenuUrl = !empty($tenantInfo['uuid']) ? url('/m/' . $tenantInfo['uuid']) : '';
$publicMenuOn  = !empty($tenantInfo['uuid']) && (int) ($tenantInfo['public_menu_enabled'] ?? 1) === 1;
?>
<?php if ($publicMenuUrl !== '' && $publicMenuOn): ?>
<div class="rounded-2xl p-5 mb-4 relative overflow-hidden border" style="background: linear-gradient(135deg, rgba(124,58,237,.08), rgba(6,182,212,.06)); border-color: rgba(124,58,237,.25);"
     x-data="{ copied: false, copy() { navigator.clipboard.writeText('<?= e($publicMenuUrl) ?>'); this.copied = true; setTimeout(() => this.copied = false, 1800); } }">
    <div class="absolute -top-12 -right-12 w-44 h-44 rounded-full opacity-30" style="background: radial-gradient(circle, rgba(124,58,237,.4), transparent 70%); filter: blur(40px);"></div>
    <div class="relative grid md:grid-cols-[1fr_auto] gap-4 items-center">
        <div class="min-w-0">
            <div class="flex items-center gap-2 mb-1">
                <span class="text-2xl">🔗</span>
                <h3 class="font-bold dark:text-white text-slate-900">Menu publico online</h3>
                <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-300 font-bold uppercase tracking-wider">Live</span>
            </div>
            <p class="text-xs dark:text-slate-400 text-slate-500 mb-2">Comparte este link a tus clientes. Arman su orden y vuelven a WhatsApp con el resumen listo para confirmar. La IA lo envia automaticamente cuando alguien pide ver el menu.</p>
            <code class="block text-xs font-mono px-3 py-2 rounded-lg dark:bg-white/5 bg-slate-100 break-all dark:text-cyan-300 text-cyan-700"><?= e($publicMenuUrl) ?></code>
        </div>
        <div class="flex flex-row md:flex-col gap-2 flex-shrink-0">
            <button @click="copy()" class="px-4 py-2 rounded-xl text-sm font-semibold dark:bg-white/10 bg-slate-200 dark:text-white text-slate-900 hover:bg-white/20 transition-colors flex items-center justify-center gap-2 whitespace-nowrap">
                <span x-text="copied ? '✓ Copiado' : '📋 Copiar'"></span>
            </button>
            <a href="<?= e($publicMenuUrl) ?>" target="_blank" rel="noopener" class="px-4 py-2 rounded-xl text-white text-sm font-semibold flex items-center justify-center gap-2 whitespace-nowrap" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">
                Abrir menu →
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#34D399;"><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(244,63,94,.08); border-color: rgba(244,63,94,.3); color:#FB7185;"><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<!-- KPIs -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
    <?php foreach ([
        ['Categorias', $totals['categories'], '#7C3AED', '🗂'],
        ['Articulos',  $totals['items'],      '#06B6D4', '🍔'],
        ['Disponibles', $totals['available'], '#10B981', '✅'],
        ['Destacados', $totals['featured'],   '#F59E0B', '⭐'],
    ] as [$lbl, $val, $col, $em]): ?>
    <div class="surface p-4">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] uppercase tracking-wider font-semibold" style="color: var(--color-text-tertiary);"><?= e($lbl) ?></span>
            <span class="text-xl"><?= $em ?></span>
        </div>
        <div class="text-2xl font-bold" style="color: var(--color-text-primary);"><?= number_format($val) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="flex flex-wrap items-center gap-2 mb-4" x-data="{ catOpen: false, itemOpen: false }">
    <button @click="catOpen = !catOpen; itemOpen = false" type="button" class="px-4 py-2 rounded-xl text-sm font-semibold flex items-center gap-2"
            style="background: var(--color-bg-subtle); color: var(--color-text-primary);">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14-7H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V6a2 2 0 00-2-2z"/></svg>
        Nueva categoria
    </button>
    <button @click="itemOpen = !itemOpen; catOpen = false" type="button" class="px-4 py-2 rounded-xl text-white font-semibold shadow-lg flex items-center gap-2"
            style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Nuevo articulo
    </button>
    <a href="<?= url('/orders') ?>" class="ml-auto px-3 py-2 rounded-xl text-sm font-medium" style="background: var(--color-bg-subtle); color: var(--color-primary);">Ver ordenes →</a>

    <!-- Form categoria -->
    <div x-show="catOpen" x-cloak x-transition class="surface p-4 w-full">
        <form action="<?= url('/menu/categories') ?>" method="POST" class="grid md:grid-cols-4 gap-3">
            <?= csrf_field() ?>
            <div class="md:col-span-2">
                <label class="label">Nombre</label>
                <input type="text" name="name" required class="input" placeholder="Ej: Pizzas, Bebidas, Postres">
            </div>
            <div>
                <label class="label">Icono / emoji</label>
                <input type="text" name="icon" maxlength="4" class="input" placeholder="🍕">
            </div>
            <div>
                <label class="label">Orden</label>
                <input type="number" name="sort_order" value="0" class="input">
            </div>
            <div class="md:col-span-4 flex justify-end gap-2">
                <button @click="catOpen = false" type="button" class="px-4 py-2 rounded-xl text-sm" style="color: var(--color-text-secondary);">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-xl text-white font-semibold" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">Crear</button>
            </div>
        </form>
    </div>

    <!-- Form item -->
    <div x-show="itemOpen" x-cloak x-transition class="surface p-4 w-full">
        <form action="<?= url('/menu/items') ?>" method="POST" class="grid md:grid-cols-4 gap-3">
            <?= csrf_field() ?>
            <div class="md:col-span-2">
                <label class="label">Nombre del articulo</label>
                <input type="text" name="name" required maxlength="160" class="input" placeholder="Hamburguesa Clasica">
            </div>
            <div>
                <label class="label">Categoria</label>
                <select name="category_id" class="input">
                    <option value="">Sin categoria</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="label">SKU (opcional)</label>
                <input type="text" name="sku" class="input" placeholder="HAM-001">
            </div>
            <div class="md:col-span-2">
                <label class="label">Descripcion</label>
                <input type="text" name="description" class="input" placeholder="Carne 200g, queso cheddar, lechuga, tomate">
            </div>
            <div>
                <label class="label">Precio</label>
                <input type="number" name="price" step="0.01" min="0" required class="input" placeholder="350.00">
            </div>
            <div>
                <label class="label">Moneda</label>
                <input type="text" name="currency" maxlength="3" value="DOP" class="input">
            </div>
            <div class="md:col-span-4">
                <label class="label">Modificadores (formato: "Nombre:precio, Nombre:precio")</label>
                <input type="text" name="modifiers_json" class="input" placeholder="Extra queso:50, Sin cebolla:0, Salsa BBQ:30">
            </div>
            <div>
                <label class="label">Tiempo prep. (min)</label>
                <input type="number" name="prep_time_min" class="input" placeholder="15">
            </div>
            <div>
                <label class="label">Foto URL</label>
                <input type="url" name="photo" class="input" placeholder="https://...">
            </div>
            <div class="md:col-span-2 flex items-end gap-3">
                <label class="flex items-center gap-2"><input type="checkbox" name="is_available" value="1" checked> Disponible</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="is_featured" value="1"> Destacar</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="is_combo" value="1"> Combo</label>
            </div>
            <div class="md:col-span-4 flex justify-end gap-2">
                <button @click="itemOpen = false" type="button" class="px-4 py-2 rounded-xl text-sm" style="color: var(--color-text-secondary);">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-xl text-white font-semibold" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">Crear articulo</button>
            </div>
        </form>
    </div>
</div>

<!-- Categorias + items -->
<?php if (empty($categories) && empty($items)): ?>
<div class="surface p-10 text-center">
    <div class="w-16 h-16 mx-auto mb-4 rounded-2xl flex items-center justify-center text-3xl" style="background: var(--gradient-mesh);">🍽</div>
    <h3 class="font-bold text-lg mb-1" style="color: var(--color-text-primary);">Empieza creando tu menu</h3>
    <p class="text-sm" style="color: var(--color-text-tertiary);">Anade tus categorias (Entradas, Platos, Bebidas) y luego los articulos. La IA los usara para tomar pedidos.</p>
</div>
<?php else: ?>
<div class="space-y-5">
    <?php foreach ($categories as $cat):
        $catItems = $grouped[(int) $cat['id']] ?? [];
    ?>
    <div class="surface p-5">
        <div class="flex items-center gap-2 mb-3 flex-wrap">
            <span class="text-2xl"><?= e((string) ($cat['icon'] ?? '🍽')) ?></span>
            <h3 class="font-bold text-base" style="color: var(--color-text-primary);"><?= e($cat['name']) ?></h3>
            <span class="badge badge-slate"><?= count($catItems) ?> items</span>
            <?php if (empty($cat['is_active'])): ?><span class="badge badge-rose">Pausada</span><?php endif; ?>
            <form action="<?= url('/menu/categories/' . $cat['id']) ?>" method="POST" class="ml-auto inline" onsubmit="return confirm('Eliminar categoria? Los articulos quedaran sin categoria.')">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="text-xs" style="color:#F87171;">Eliminar categoria</button>
            </form>
        </div>

        <?php if (empty($catItems)): ?>
        <p class="text-xs" style="color: var(--color-text-tertiary);">Esta categoria no tiene articulos. Crea uno arriba y eligela.</p>
        <?php else: ?>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <?php foreach ($catItems as $item): ?>
            <?php \App\Core\View::include('menu._item_card', ['item' => $item]); ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if (!empty($grouped['_uncat'])): ?>
    <div class="surface p-5">
        <div class="flex items-center gap-2 mb-3">
            <span class="text-2xl">📦</span>
            <h3 class="font-bold text-base" style="color: var(--color-text-primary);">Sin categoria</h3>
            <span class="badge badge-slate"><?= count($grouped['_uncat']) ?> items</span>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <?php foreach ($grouped['_uncat'] as $item): ?>
            <?php \App\Core\View::include('menu._item_card', ['item' => $item]); ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php \App\Core\View::stop(); ?>
