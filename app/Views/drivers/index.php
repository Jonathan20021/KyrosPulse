<?php
/** @var array $drivers */
/** @var array $statuses */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('components.page_header', [
    'title'    => 'Repartidores',
    'subtitle' => 'Gestiona los drivers del restaurante: comisiones, estado, performance.',
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#34D399;"><?= e((string) $flash) ?></div>
<?php endif; ?>

<div class="flex items-center gap-2 mb-4">
    <a href="<?= url('/drivers/create') ?>" class="px-4 py-2 rounded-xl text-white font-semibold shadow-lg flex items-center gap-2" style="background: linear-gradient(135deg,#10B981,#06B6D4);">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Nuevo driver
    </a>
    <a href="<?= url('/delivery') ?>" class="px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary);">Despacho</a>
    <a href="<?= url('/delivery/payouts') ?>" class="px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary);">Liquidaciones</a>
</div>

<div class="surface overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr style="background: var(--color-bg-subtle);">
                <th class="px-3 py-2 text-left text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">Driver</th>
                <th class="px-3 py-2 text-left text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">Telefono</th>
                <th class="px-3 py-2 text-center text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">Vehiculo</th>
                <th class="px-3 py-2 text-center text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">Estado</th>
                <th class="px-3 py-2 text-right text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">Activas</th>
                <th class="px-3 py-2 text-right text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">Total</th>
                <th class="px-3 py-2 text-right text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">Rating</th>
                <th class="px-3 py-2 text-right text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);"></th>
            </tr>
        </thead>
        <tbody class="divide-y" style="border-color: var(--color-border-subtle);">
        <?php if (empty($drivers)): ?>
        <tr><td colspan="8">
            <?php \App\Core\View::include('components.empty_state', [
                'icon' => '🛵',
                'title' => 'Aun no tienes drivers',
                'message' => 'Crea tu primer repartidor para empezar a despachar entregas. Cada driver recibe un PIN de acceso al portal mobile.',
                'ctaUrl' => url('/drivers/create'),
                'ctaLabel' => 'Crear primer driver',
            ]); ?>
        </td></tr>
        <?php else: foreach ($drivers as $d):
            [$lbl, $color, $emoji] = $statuses[$d['status']] ?? [$d['status'], '#94A3B8', '•'];
        ?>
        <tr>
            <td class="px-3 py-2">
                <a href="<?= url('/drivers/' . $d['id']) ?>" class="flex items-center gap-2">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm" style="background: linear-gradient(135deg,#10B981,#06B6D4); color: white;"><?= e(strtoupper(mb_substr((string) $d['name'], 0, 1))) ?></div>
                    <div>
                        <div class="font-semibold" style="color: var(--color-text-primary);"><?= e((string) $d['name']) ?></div>
                        <?php if (!$d['is_active']): ?><span class="text-[10px] px-1.5 py-0.5 rounded" style="background:rgba(239,68,68,.15); color:#F87171;">INACTIVO</span><?php endif; ?>
                    </div>
                </a>
            </td>
            <td class="px-3 py-2 text-xs" style="color: var(--color-text-secondary);"><?= e((string) $d['phone']) ?></td>
            <td class="px-3 py-2 text-center text-xs"><?= match($d['vehicle_type']) { 'bike'=>'🚲','motorcycle'=>'🛵','car'=>'🚗','walk'=>'🚶',default=>'🛵' } ?> <?= e((string) ($d['vehicle_plate'] ?? '')) ?></td>
            <td class="px-3 py-2 text-center">
                <span class="text-[10px] px-2 py-0.5 rounded font-semibold" style="background: <?= $color ?>22; color: <?= $color ?>;"><?= $emoji ?> <?= e($lbl) ?></span>
            </td>
            <td class="px-3 py-2 text-right font-bold"><?= (int) ($d['active_deliveries'] ?? 0) ?></td>
            <td class="px-3 py-2 text-right"><?= (int) $d['total_deliveries'] ?></td>
            <td class="px-3 py-2 text-right">
                <?php if ((float) $d['rating_avg'] > 0): ?>
                ⭐ <?= number_format((float) $d['rating_avg'], 1) ?>
                <?php else: ?>
                <span style="color: var(--color-text-tertiary);">—</span>
                <?php endif; ?>
            </td>
            <td class="px-3 py-2 text-right">
                <a href="<?= url('/drivers/' . $d['id']) ?>" class="text-[11px] px-2 py-1 rounded" style="background: var(--color-bg-subtle); color: var(--color-text-primary);">Ver →</a>
            </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php \App\Core\View::stop(); ?>
