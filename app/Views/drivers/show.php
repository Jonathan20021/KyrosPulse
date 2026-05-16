<?php
/** @var array $driver */
/** @var array $active */
/** @var array $history */
/** @var array|null $current_shift */
/** @var array $shifts */
/** @var array $stats */
/** @var array $commissions */
/** @var array $statuses */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

[$lbl, $color, $emoji] = $statuses[$driver['status']] ?? [$driver['status'], '#94A3B8', '•'];
?>
<?php \App\Core\View::include('components.page_header', [
    'title'    => $driver['name'],
    'subtitle' => 'Perfil del repartidor, performance y configuracion de comisiones.',
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#34D399;"><?= e((string) $flash) ?></div>
<?php endif; ?>

<div class="grid lg:grid-cols-3 gap-4">
    <!-- KPIs + perfil -->
    <div class="lg:col-span-2 space-y-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="surface p-3">
                <div class="text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">Hoy</div>
                <div class="text-2xl font-bold"><?= (int) $stats['today_deliveries'] ?></div>
            </div>
            <div class="surface p-3">
                <div class="text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">Semana</div>
                <div class="text-2xl font-bold"><?= (int) $stats['week_deliveries'] ?></div>
            </div>
            <div class="surface p-3">
                <div class="text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">Cash en mano</div>
                <div class="text-xl font-bold" style="color:#34D399;">$<?= number_format((float) $stats['cash_held'], 2) ?></div>
            </div>
            <div class="surface p-3">
                <div class="text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">Comision semana</div>
                <div class="text-xl font-bold" style="color:#22D3EE;">$<?= number_format((float) $stats['commission_week'], 2) ?></div>
            </div>
        </div>

        <!-- Form de edicion -->
        <form method="POST" action="<?= url('/drivers/' . $driver['id']) ?>" class="surface p-5">
            <?= csrf_field() ?>
            <input type="hidden" name="_method" value="PUT">
            <h3 class="font-bold text-sm mb-3">Editar driver</h3>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="text-[10px] uppercase tracking-wider font-semibold block mb-1" style="color: var(--color-text-tertiary);">Nombre</label>
                    <input name="name" required value="<?= e((string) $driver['name']) ?>" class="w-full px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wider font-semibold block mb-1" style="color: var(--color-text-tertiary);">Telefono</label>
                    <input name="phone" required value="<?= e((string) $driver['phone']) ?>" class="w-full px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wider font-semibold block mb-1" style="color: var(--color-text-tertiary);">Vehiculo</label>
                    <select name="vehicle_type" class="w-full px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
                        <?php foreach (['motorcycle'=>'🛵 Moto','bike'=>'🚲 Bicicleta','car'=>'🚗 Auto','walk'=>'🚶 A pie','other'=>'Otro'] as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $driver['vehicle_type'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wider font-semibold block mb-1" style="color: var(--color-text-tertiary);">Placa</label>
                    <input name="vehicle_plate" value="<?= e((string) ($driver['vehicle_plate'] ?? '')) ?>" class="w-full px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wider font-semibold block mb-1" style="color: var(--color-text-tertiary);">Tipo comision</label>
                    <select name="commission_type" class="w-full px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
                        <?php foreach ($commissions as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $driver['commission_type'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wider font-semibold block mb-1" style="color: var(--color-text-tertiary);">Tarifa fija $</label>
                    <input name="commission_flat" type="number" step="0.01" value="<?= (float) $driver['commission_flat'] ?>" class="w-full px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wider font-semibold block mb-1" style="color: var(--color-text-tertiary);">Porcentaje %</label>
                    <input name="commission_percent" type="number" step="0.01" value="<?= (float) $driver['commission_percent'] ?>" class="w-full px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wider font-semibold block mb-1" style="color: var(--color-text-tertiary);">Por km $</label>
                    <input name="commission_per_km" type="number" step="0.01" value="<?= (float) $driver['commission_per_km'] ?>" class="w-full px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
                </div>
                <div class="md:col-span-2">
                    <label class="text-[10px] uppercase tracking-wider font-semibold block mb-1" style="color: var(--color-text-tertiary);">Cambiar PIN (dejar vacio para no cambiar)</label>
                    <input name="pin" type="text" inputmode="numeric" pattern="[0-9]{4,8}" placeholder="••••" class="w-full px-3 py-2 rounded-xl text-sm tracking-widest font-mono" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
                </div>
                <div class="md:col-span-2">
                    <label class="text-[10px] uppercase tracking-wider font-semibold block mb-1" style="color: var(--color-text-tertiary);">Notas internas</label>
                    <textarea name="notes" rows="2" class="w-full px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);"><?= e((string) ($driver['notes'] ?? '')) ?></textarea>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-2">
                <button class="px-4 py-2 rounded-xl text-white font-semibold" style="background: linear-gradient(135deg,#10B981,#06B6D4);">Guardar cambios</button>
                <a href="<?= url('/drivers') ?>" class="px-4 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary);">Volver</a>
            </div>
        </form>

        <?php if (!empty($active)): ?>
        <div class="surface p-5">
            <h3 class="font-bold text-sm mb-3">🛵 Entregas activas</h3>
            <ul class="space-y-2">
                <?php foreach ($active as $a): ?>
                <li class="flex items-center gap-2 text-sm">
                    <a href="<?= url('/delivery/' . $a['id']) ?>" class="flex-1" style="color: var(--color-text-primary);">#<?= e((string) $a['order_code']) ?> · <?= e((string) ($a['customer_name'] ?? 'Cliente')) ?></a>
                    <span class="text-[10px] px-2 py-0.5 rounded" style="background: var(--color-bg-subtle); color: var(--color-text-secondary);"><?= e($statuses[$a['status']][0] ?? $a['status']) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <!-- Side: estado + acciones -->
    <div class="space-y-4">
        <div class="surface p-5">
            <div class="text-[10px] uppercase tracking-wider font-semibold mb-1" style="color: var(--color-text-tertiary);">Estado actual</div>
            <div class="text-xl font-bold mb-3" style="color: <?= $color ?>;"><?= $emoji ?> <?= e($lbl) ?></div>

            <?php if ($current_shift): ?>
            <div class="p-2 rounded-lg mb-3" style="background: rgba(16,185,129,.1);">
                <div class="text-[10px] uppercase font-bold" style="color:#34D399;">Turno abierto</div>
                <div class="text-xs" style="color: var(--color-text-secondary);">Desde <?= e(date('d/m H:i', strtotime((string) $current_shift['started_at']))) ?></div>
            </div>
            <?php endif; ?>

            <form method="POST" action="<?= url('/drivers/' . $driver['id'] . '/toggle') ?>">
                <?= csrf_field() ?>
                <button class="w-full px-3 py-2 rounded-xl text-sm font-semibold" style="background: <?= $driver['is_active'] ? 'rgba(239,68,68,.15)' : 'rgba(16,185,129,.15)' ?>; color: <?= $driver['is_active'] ? '#F87171' : '#34D399' ?>;">
                    <?= $driver['is_active'] ? 'Suspender driver' : 'Reactivar driver' ?>
                </button>
            </form>
            <form method="POST" action="<?= url('/drivers/' . $driver['id']) ?>" class="mt-2" onsubmit="return confirm('Eliminar driver? No se borraran las entregas pasadas.')">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="DELETE">
                <button class="w-full px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color:#F87171;">Eliminar driver</button>
            </form>
        </div>

        <?php if (!empty($shifts)): ?>
        <div class="surface p-5">
            <h3 class="font-bold text-sm mb-3">📅 Turnos recientes</h3>
            <ul class="space-y-1 text-xs">
                <?php foreach ($shifts as $s): ?>
                <li class="flex items-center justify-between">
                    <span style="color: var(--color-text-secondary);"><?= e(date('d/m', strtotime((string) $s['started_at']))) ?> · <?= (int) $s['deliveries_count'] ?> entregas</span>
                    <span class="font-semibold" style="color:#22D3EE;">$<?= number_format((float) $s['commission_earned'], 2) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php \App\Core\View::stop(); ?>
