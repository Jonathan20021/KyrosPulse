<?php
/** @var array $rows */
/** @var array $drivers */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('components.page_header', [
    'title'    => 'Liquidaciones · Delivery',
    'subtitle' => 'Genera y paga las comisiones de tus repartidores. Resta el efectivo que ya tienen en mano.',
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#34D399;"><?= e((string) $flash) ?></div>
<?php endif; ?>

<div class="surface p-4 mb-4">
    <form method="POST" action="<?= url('/delivery/payouts/generate') ?>" class="flex flex-wrap items-end gap-3">
        <?= csrf_field() ?>
        <div>
            <label class="text-[10px] uppercase tracking-wider font-semibold block mb-1" style="color: var(--color-text-tertiary);">Desde</label>
            <input type="date" name="from" value="<?= e(date('Y-m-d', strtotime('-7 days'))) ?>" required class="px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
        </div>
        <div>
            <label class="text-[10px] uppercase tracking-wider font-semibold block mb-1" style="color: var(--color-text-tertiary);">Hasta</label>
            <input type="date" name="to" value="<?= e(date('Y-m-d')) ?>" required class="px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
        </div>
        <div>
            <label class="text-[10px] uppercase tracking-wider font-semibold block mb-1" style="color: var(--color-text-tertiary);">Driver</label>
            <select name="driver_id" class="px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
                <option value="">Todos los drivers</option>
                <?php foreach ($drivers as $d): ?>
                <option value="<?= (int) $d['id'] ?>"><?= e((string) $d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="px-4 py-2 rounded-xl text-white font-semibold" style="background: linear-gradient(135deg,#10B981,#06B6D4);">Generar liquidaciones</button>
    </form>
</div>

<div class="surface overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr style="background: var(--color-bg-subtle);">
                <th class="px-3 py-2 text-left text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">Driver</th>
                <th class="px-3 py-2 text-left text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">Periodo</th>
                <th class="px-3 py-2 text-right text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">#</th>
                <th class="px-3 py-2 text-right text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">Cash en mano</th>
                <th class="px-3 py-2 text-right text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">Comision</th>
                <th class="px-3 py-2 text-right text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">Neto</th>
                <th class="px-3 py-2 text-center text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">Estado</th>
                <th class="px-3 py-2 text-right text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);"></th>
            </tr>
        </thead>
        <tbody class="divide-y" style="border-color: var(--color-border-subtle);">
        <?php if (empty($rows)): ?>
        <tr><td colspan="8">
        <?php \App\Core\View::include('components.empty_state', [
            'icon' => '💰',
            'title' => 'Sin liquidaciones generadas',
            'message' => 'Selecciona un periodo arriba y genera las liquidaciones para tus repartidores.',
        ]); ?>
        </td></tr>
        <?php else: foreach ($rows as $p):
            $net = (float) $p['net_amount'];
            $statusColor = $p['status'] === 'paid' ? '#22C55E' : ($p['status'] === 'cancelled' ? '#94A3B8' : '#F59E0B');
        ?>
        <tr>
            <td class="px-3 py-2">
                <div class="font-semibold" style="color: var(--color-text-primary);"><?= e((string) ($p['driver_name'] ?? 'Driver #' . $p['driver_id'])) ?></div>
                <div class="text-xs" style="color: var(--color-text-tertiary);"><?= e((string) ($p['driver_phone'] ?? '')) ?></div>
            </td>
            <td class="px-3 py-2 text-xs" style="color: var(--color-text-secondary);"><?= e((string) $p['period_from']) ?> → <?= e((string) $p['period_to']) ?></td>
            <td class="px-3 py-2 text-right font-semibold"><?= (int) $p['deliveries_count'] ?></td>
            <td class="px-3 py-2 text-right" style="color:#34D399;">$<?= number_format((float) $p['cash_collected'], 2) ?></td>
            <td class="px-3 py-2 text-right" style="color:#22D3EE;">$<?= number_format((float) $p['commission_owed'], 2) ?></td>
            <td class="px-3 py-2 text-right font-bold" style="color: <?= $net >= 0 ? '#34D399' : '#F87171' ?>;">
                <?= $net >= 0 ? '+' : '−' ?>$<?= number_format(abs($net), 2) ?>
            </td>
            <td class="px-3 py-2 text-center">
                <span class="text-[10px] px-2 py-0.5 rounded font-semibold" style="background: <?= $statusColor ?>22; color: <?= $statusColor ?>;"><?= e(strtoupper((string) $p['status'])) ?></span>
            </td>
            <td class="px-3 py-2 text-right">
                <?php if ($p['status'] === 'pending'): ?>
                <form method="POST" action="<?= url('/delivery/payouts/' . $p['id'] . '/pay') ?>" style="display:inline;" onsubmit="return confirm('Marcar como pagado?')">
                    <?= csrf_field() ?>
                    <button class="text-[11px] px-2 py-1 rounded font-bold" style="background:#22C55E; color: white;">Marcar pagado</button>
                </form>
                <form method="POST" action="<?= url('/delivery/payouts/' . $p['id'] . '/cancel') ?>" style="display:inline;">
                    <?= csrf_field() ?>
                    <button class="text-[11px] px-2 py-1 rounded" style="background: var(--color-bg-subtle); color: var(--color-text-tertiary);">Cancelar</button>
                </form>
                <?php elseif ($p['status'] === 'paid' && $p['paid_at']): ?>
                <span class="text-[10px]" style="color: var(--color-text-tertiary);">pagado <?= e(date('d/m H:i', strtotime((string) $p['paid_at']))) ?></span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php \App\Core\View::stop(); ?>
