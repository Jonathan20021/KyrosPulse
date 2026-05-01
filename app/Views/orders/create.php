<?php
/** @var array $menu */
/** @var array $zones */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<a href="<?= url('/orders') ?>" class="text-xs flex items-center gap-1 mb-4" style="color: var(--color-text-tertiary);">
    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    Volver a ordenes
</a>

<?php \App\Core\View::include('components.page_header', [
    'title'    => 'Nueva orden manual',
    'subtitle' => 'Registra una orden tomada en local o por telefono.',
]); ?>

<form action="<?= url('/orders') ?>" method="POST" x-data="orderBuilder()" class="grid lg:grid-cols-3 gap-4">
    <?= csrf_field() ?>

    <div class="lg:col-span-2 space-y-4">
        <div class="surface p-5">
            <h3 class="font-bold mb-3" style="color: var(--color-text-primary);">Articulos</h3>

            <div class="grid md:grid-cols-3 gap-2 mb-3">
                <select x-model="picker" class="input md:col-span-2">
                    <option value="">Selecciona un articulo del menu…</option>
                    <?php foreach ($menu as $m): ?>
                    <option value="<?= (int) $m['id'] ?>" data-name="<?= e($m['name']) ?>" data-price="<?= e((string) $m['price']) ?>">
                        <?= e($m['name']) ?> — <?= e((string) $m['currency']) ?> <?= number_format((float) $m['price'], 2) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" @click="addLine()" class="px-4 py-2 rounded-xl text-white text-sm font-semibold" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">+ Agregar</button>
            </div>

            <template x-if="lines.length === 0">
                <p class="text-xs py-4 text-center" style="color: var(--color-text-tertiary);">Aun no hay items en la orden.</p>
            </template>

            <template x-for="(line, idx) in lines" :key="idx">
                <div class="flex items-center gap-2 p-2 rounded-lg mb-1" style="background: var(--color-bg-subtle);">
                    <input type="hidden" :name="'items[' + idx + '][menu_item_id]'" :value="line.menu_item_id">
                    <input type="hidden" :name="'items[' + idx + '][name]'" :value="line.name">
                    <input type="hidden" :name="'items[' + idx + '][unit_price]'" :value="line.unit_price">
                    <input type="number" :name="'items[' + idx + '][qty]'" x-model="line.qty" min="1" class="w-16 px-2 py-1 rounded text-sm" style="background: var(--color-bg-elevated); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
                    <span class="flex-1 font-semibold text-sm" x-text="line.name" style="color: var(--color-text-primary);"></span>
                    <input type="text" :name="'items[' + idx + '][notes]'" x-model="line.notes" placeholder="Notas" class="px-2 py-1 rounded text-xs flex-1" style="background: var(--color-bg-elevated); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
                    <span class="text-sm font-bold" style="color: var(--color-primary);" x-text="(line.unit_price * line.qty).toFixed(2)"></span>
                    <button type="button" @click="removeLine(idx)" class="text-xs" style="color:#F87171;">✗</button>
                </div>
            </template>

            <div class="mt-4 pt-4 border-t flex justify-between font-bold" style="border-color: var(--color-border-subtle); color: var(--color-text-primary);">
                <span>Subtotal</span>
                <span x-text="subtotal().toFixed(2)" style="color: var(--color-primary);"></span>
            </div>
        </div>
    </div>

    <aside class="space-y-4">
        <div class="surface p-4">
            <h3 class="font-bold mb-3" style="color: var(--color-text-primary);">Cliente</h3>
            <input type="text" name="customer_name" required placeholder="Nombre" class="input mb-2">
            <input type="tel" name="customer_phone" placeholder="+1 809 555 1234" class="input">
        </div>

        <div class="surface p-4">
            <h3 class="font-bold mb-3" style="color: var(--color-text-primary);">Entrega</h3>
            <select name="delivery_type" x-model="deliveryType" class="input mb-2">
                <option value="delivery">🛵 Delivery</option>
                <option value="pickup">🛍 Pickup</option>
                <option value="dine_in">🍴 Mesa</option>
            </select>
            <template x-if="deliveryType === 'delivery'">
                <div>
                    <select name="delivery_zone_id" class="input mb-2">
                        <option value="">Sin zona</option>
                        <?php foreach ($zones as $z): ?>
                        <option value="<?= (int) $z['id'] ?>"><?= e($z['name']) ?> — $<?= number_format((float) $z['fee'], 2) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="delivery_address" placeholder="Direccion completa" class="input mb-2">
                </div>
            </template>
            <textarea name="delivery_notes" placeholder="Notas de entrega" class="textarea text-xs" rows="2"></textarea>
        </div>

        <div class="surface p-4">
            <h3 class="font-bold mb-3" style="color: var(--color-text-primary);">Pago</h3>
            <select name="payment_method" class="input mb-2">
                <option value="cash">Efectivo</option>
                <option value="card">Tarjeta</option>
                <option value="transfer">Transferencia</option>
                <option value="online">Pago online</option>
            </select>
            <input type="number" name="tax" step="0.01" placeholder="Impuesto" class="input mb-2">
            <input type="number" name="discount" step="0.01" placeholder="Descuento" class="input mb-2">
            <input type="number" name="tip" step="0.01" placeholder="Propina" class="input">
        </div>

        <button type="submit" class="w-full px-4 py-3 rounded-xl text-white font-semibold shadow-lg" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">
            Crear orden
        </button>
    </aside>
</form>

<script>
function orderBuilder() {
    return {
        picker: '',
        deliveryType: 'delivery',
        lines: [],
        addLine() {
            const select = document.querySelector('select[x-model="picker"]');
            const opt = select.querySelector('option[value="' + this.picker + '"]');
            if (!opt) return;
            this.lines.push({
                menu_item_id: parseInt(this.picker, 10),
                name: opt.dataset.name,
                unit_price: parseFloat(opt.dataset.price),
                qty: 1,
                notes: '',
            });
            this.picker = '';
        },
        removeLine(idx) { this.lines.splice(idx, 1); },
        subtotal() { return this.lines.reduce((s, l) => s + (l.unit_price * l.qty), 0); },
    };
}
</script>

<?php \App\Core\View::stop(); ?>
