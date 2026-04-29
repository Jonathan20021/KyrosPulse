<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
$products = $products ?? [];
?>
<?php \App\Core\View::include('components.page_header', ['title' => 'Productos y servicios', 'subtitle' => 'Catalogo que tus agentes IA usan para vender y cotizar.']); ?>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#34D399;">
    <?= e((string) $flash) ?>
</div>
<?php endif; ?>

<div class="grid lg:grid-cols-3 gap-4">
    <!-- Form alta -->
    <form action="<?= url('/products') ?>" method="POST" class="glass rounded-2xl p-5 lg:col-span-1 self-start">
        <?= csrf_field() ?>
        <h3 class="font-bold dark:text-white text-slate-900 mb-3">Agregar producto</h3>
        <div class="space-y-3">
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Nombre</label>
                <input type="text" name="name" required placeholder="Plan Pro mensual" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">SKU</label>
                    <input type="text" name="sku" placeholder="PRO-MO" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Categoria</label>
                    <input type="text" name="category" placeholder="planes" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                </div>
            </div>
            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Precio</label>
                    <input type="number" step="0.01" name="price" required class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Moneda</label>
                    <select name="currency" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                        <option value="USD">USD</option>
                        <option value="DOP">DOP</option>
                        <option value="EUR">EUR</option>
                        <option value="MXN">MXN</option>
                        <option value="COP">COP</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Stock</label>
                    <input type="number" name="stock" placeholder="opcional" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                </div>
            </div>
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Descripcion</label>
                <textarea name="description" rows="3" placeholder="Caracteristicas, beneficios, condiciones..."
                          class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900"></textarea>
            </div>
            <label class="flex items-center gap-2 text-sm dark:text-slate-300 text-slate-700">
                <input type="checkbox" name="is_active" value="1" checked class="w-4 h-4 rounded">
                Activo (la IA lo puede ofrecer)
            </label>
            <button type="submit" class="w-full px-5 py-2 rounded-xl text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Guardar producto</button>
        </div>
    </form>

    <!-- Listado -->
    <div class="lg:col-span-2 space-y-3">
        <?php if (empty($products)): ?>
        <div class="glass rounded-2xl p-8 text-center">
            <div class="text-4xl mb-2">📦</div>
            <h4 class="font-bold dark:text-white text-slate-900 mb-1">Aun no tienes productos</h4>
            <p class="text-sm dark:text-slate-400 text-slate-500">Agrega tu catalogo para que los agentes IA puedan ofrecer y vender automaticamente.</p>
        </div>
        <?php else: foreach ($products as $p): ?>
        <details class="glass rounded-2xl p-4">
            <summary class="cursor-pointer list-none flex items-center justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                        <span class="font-bold dark:text-white text-slate-900"><?= e($p['name']) ?></span>
                        <?php if (!empty($p['sku'])): ?><span class="text-[10px] font-mono px-2 py-0.5 rounded bg-violet-500/10 text-violet-300"><?= e($p['sku']) ?></span><?php endif; ?>
                        <?php if (!empty($p['category'])): ?><span class="text-[10px] px-2 py-0.5 rounded bg-cyan-500/10 text-cyan-300"><?= e($p['category']) ?></span><?php endif; ?>
                        <?php if (empty($p['is_active'])): ?><span class="text-[10px] px-2 py-0.5 rounded bg-slate-500/20 text-slate-400">Inactivo</span><?php endif; ?>
                    </div>
                    <div class="text-xs dark:text-slate-400 text-slate-500 truncate"><?= e((string) ($p['description'] ?? '')) ?></div>
                </div>
                <div class="text-right flex-shrink-0">
                    <div class="font-bold text-emerald-400"><?= format_currency((float) $p['price'], (string) $p['currency']) ?></div>
                    <?php if ($p['stock'] !== null): ?>
                    <div class="text-[11px] dark:text-slate-400 text-slate-500">stock: <?= (int) $p['stock'] ?></div>
                    <?php endif; ?>
                </div>
            </summary>
            <form action="<?= url('/products/' . $p['id']) ?>" method="POST" class="mt-4 pt-4 border-t dark:border-white/10 border-slate-200 grid md:grid-cols-2 gap-3">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="PUT">
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Nombre</label>
                    <input type="text" name="name" value="<?= e($p['name']) ?>" required class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">SKU</label>
                    <input type="text" name="sku" value="<?= e((string) ($p['sku'] ?? '')) ?>" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Categoria</label>
                    <input type="text" name="category" value="<?= e((string) ($p['category'] ?? '')) ?>" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Precio</label>
                        <input type="number" step="0.01" name="price" value="<?= e((string) $p['price']) ?>" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                    </div>
                    <div>
                        <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Moneda</label>
                        <input type="text" name="currency" value="<?= e($p['currency']) ?>" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                    </div>
                    <div>
                        <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Stock</label>
                        <input type="number" name="stock" value="<?= e((string) ($p['stock'] ?? '')) ?>" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Descripcion</label>
                    <textarea name="description" rows="2" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900"><?= e((string) ($p['description'] ?? '')) ?></textarea>
                </div>
                <label class="flex items-center gap-2 text-sm dark:text-slate-300 text-slate-700">
                    <input type="checkbox" name="is_active" value="1" <?= !empty($p['is_active']) ? 'checked' : '' ?> class="w-4 h-4 rounded">
                    Activo
                </label>
                <div class="flex items-center gap-2 justify-end">
                    <button type="submit" class="px-4 py-2 rounded-lg text-white text-xs font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Guardar cambios</button>
                </div>
            </form>
            <form action="<?= url('/products/' . $p['id']) ?>" method="POST" class="mt-2 text-right" onsubmit="return confirm('Eliminar este producto?')">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="DELETE">
                <button class="text-xs text-red-500 hover:underline">Eliminar producto</button>
            </form>
        </details>
        <?php endforeach; endif; ?>
    </div>
</div>

<?php \App\Core\View::stop(); ?>
