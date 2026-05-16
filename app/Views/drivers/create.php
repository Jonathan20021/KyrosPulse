<?php
/** @var array $commissions */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('components.page_header', [
    'title'    => 'Nuevo repartidor',
    'subtitle' => 'Alta de un driver. Recibira un PIN para entrar al portal mobile en /driver/login.',
]); ?>

<?php if ($flash = flash('error')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(239,68,68,.08); border-color: rgba(239,68,68,.3); color:#F87171;"><?= e((string) $flash) ?></div>
<?php endif; ?>

<form method="POST" action="<?= url('/drivers') ?>" class="surface p-5 max-w-3xl">
    <?= csrf_field() ?>
    <div class="grid md:grid-cols-2 gap-4">
        <div>
            <label class="text-[10px] uppercase tracking-wider font-semibold block mb-1" style="color: var(--color-text-tertiary);">Nombre completo *</label>
            <input name="name" required class="w-full px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
        </div>
        <div>
            <label class="text-[10px] uppercase tracking-wider font-semibold block mb-1" style="color: var(--color-text-tertiary);">Telefono *</label>
            <input name="phone" type="tel" required placeholder="8095551234" class="w-full px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
        </div>
        <div>
            <label class="text-[10px] uppercase tracking-wider font-semibold block mb-1" style="color: var(--color-text-tertiary);">Email (opcional)</label>
            <input name="email" type="email" class="w-full px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
        </div>
        <div>
            <label class="text-[10px] uppercase tracking-wider font-semibold block mb-1" style="color: var(--color-text-tertiary);">PIN (4-8 digitos) *</label>
            <input name="pin" type="text" required inputmode="numeric" pattern="[0-9]{4,8}" placeholder="1234" class="w-full px-3 py-2 rounded-xl text-sm tracking-widest font-mono" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
        </div>
        <div>
            <label class="text-[10px] uppercase tracking-wider font-semibold block mb-1" style="color: var(--color-text-tertiary);">Vehiculo</label>
            <select name="vehicle_type" class="w-full px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
                <option value="motorcycle">🛵 Motocicleta</option>
                <option value="bike">🚲 Bicicleta</option>
                <option value="car">🚗 Carro</option>
                <option value="walk">🚶 A pie</option>
                <option value="other">Otro</option>
            </select>
        </div>
        <div>
            <label class="text-[10px] uppercase tracking-wider font-semibold block mb-1" style="color: var(--color-text-tertiary);">Placa / Identificacion</label>
            <input name="vehicle_plate" class="w-full px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
        </div>
    </div>

    <div class="mt-5 pt-5 border-t" style="border-color: var(--color-border-subtle);">
        <h3 class="font-bold text-sm mb-3">💸 Modelo de comision</h3>
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="text-[10px] uppercase tracking-wider font-semibold block mb-1" style="color: var(--color-text-tertiary);">Tipo</label>
                <select name="commission_type" class="w-full px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
                    <?php foreach ($commissions as $k => $v): ?>
                    <option value="<?= $k ?>"><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider font-semibold block mb-1" style="color: var(--color-text-tertiary);">Tarifa fija ($)</label>
                <input name="commission_flat" type="number" step="0.01" value="0" class="w-full px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider font-semibold block mb-1" style="color: var(--color-text-tertiary);">Porcentaje (%)</label>
                <input name="commission_percent" type="number" step="0.01" value="0" class="w-full px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider font-semibold block mb-1" style="color: var(--color-text-tertiary);">Por kilometro ($)</label>
                <input name="commission_per_km" type="number" step="0.01" value="0" class="w-full px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
            </div>
        </div>
    </div>

    <div class="mt-5 flex items-center gap-2">
        <button class="px-4 py-2 rounded-xl text-white font-semibold" style="background: linear-gradient(135deg,#10B981,#06B6D4);">Crear driver</button>
        <a href="<?= url('/drivers') ?>" class="px-4 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary);">Cancelar</a>
    </div>
</form>

<?php \App\Core\View::stop(); ?>
