<?php
/** @var array $rows */
/** @var array $stats */
/** @var array $drivers */
/** @var array $statuses */
/** @var array $filters */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('components.page_header', [
    'title'    => 'Despacho · Delivery',
    'subtitle' => 'Centro de comando del delivery. Asigna repartidores, monitorea entregas en vivo y cobra en cash o digital.',
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#34D399;"><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flash = flash('error')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(239,68,68,.08); border-color: rgba(239,68,68,.3); color:#F87171;"><?= e((string) $flash) ?></div>
<?php endif; ?>

<!-- KPIs -->
<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 mb-4">
    <?php foreach ([
        ['Sin asignar',    $stats['pending'],          '#94A3B8', '⏳'],
        ['En ruta',        $stats['in_flight'],        '#F59E0B', '🛵'],
        ['Entregados hoy', $stats['delivered_today'],  '#22C55E', '✅'],
        ['Drivers online', $stats['drivers_online'],   '#10B981', '🟢'],
        ['Cash hoy',       '$' . number_format((float) $stats['cash_today'], 2), '#10B981', '💵'],
        ['Comisiones hoy', '$' . number_format((float) $stats['commission_today'], 2), '#06B6D4', '💸'],
        ['ETA promedio',   $stats['avg_eta_min'] . 'm', '#A855F7', '⏱'],
    ] as [$lbl, $val, $col, $em]): ?>
    <div class="surface p-3">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] uppercase tracking-wider font-semibold" style="color: var(--color-text-tertiary);"><?= e($lbl) ?></span>
            <span class="text-lg"><?= $em ?></span>
        </div>
        <div class="text-xl font-bold" style="color: var(--color-text-primary);"><?= e((string) $val) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="flex flex-wrap items-center gap-2 mb-4">
    <a href="<?= url('/drivers') ?>" class="px-4 py-2 rounded-xl text-white font-semibold shadow-lg flex items-center gap-2" style="background: linear-gradient(135deg,#10B981,#06B6D4);">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857"/></svg>
        Repartidores
    </a>
    <a href="<?= url('/delivery/payouts') ?>" class="px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary);">💰 Liquidaciones</a>
    <a href="<?= url('/settings/restaurant') ?>" class="px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary);">Ajustes</a>
    <div class="ml-auto flex items-center gap-2">
        <span class="text-xs" style="color: var(--color-text-tertiary);">Auto-refresh cada 10s</span>
        <span id="liveDot" class="w-2 h-2 rounded-full" style="background:#22C55E; box-shadow: 0 0 8px #22C55E;"></span>
    </div>
</div>

<!-- Tabla en vivo -->
<div class="surface overflow-hidden">
    <div class="px-4 py-3 border-b flex items-center justify-between" style="border-color: var(--color-border-subtle);">
        <h3 class="font-bold text-sm" style="color: var(--color-text-primary);">Entregas activas</h3>
        <div class="flex gap-1">
            <?php foreach (['active'=>'En curso','pending'=>'Sin asignar','delivered'=>'Entregadas','all'=>'Todas'] as $k=>$v): ?>
            <a href="<?= url('/delivery?status=' . $k) ?>" class="text-[11px] px-2 py-1 rounded <?= ($filters['status']??'') === $k ? 'font-bold' : '' ?>" style="background: <?= ($filters['status']??'') === $k ? 'var(--color-primary)' : 'var(--color-bg-subtle)' ?>; color: <?= ($filters['status']??'') === $k ? 'white' : 'var(--color-text-secondary)' ?>;"><?= $v ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div id="deliveryListContainer" class="divide-y" style="border-color: var(--color-border-subtle);">
        <?php if (empty($rows)): ?>
        <?php \App\Core\View::include('components.empty_state', [
            'icon' => '🛵',
            'title' => 'Sin entregas activas',
            'message' => 'Cuando una orden de delivery entre a flujo, aparecera aqui para asignar repartidor.',
        ]); ?>
        <?php else: foreach ($rows as $d):
            [$lbl, $color, $emoji] = $statuses[$d['status']] ?? [$d['status'], '#94A3B8', '•'];
            $minsAgo = floor((time() - strtotime((string) $d['created_at'])) / 60);
        ?>
        <div class="px-4 py-3 hover:bg-white/[0.02] transition" data-delivery-id="<?= (int) $d['id'] ?>">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center text-lg" style="background: <?= $color ?>22; color: <?= $color ?>;">
                    <?= $emoji ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                        <a href="<?= url('/delivery/' . $d['id']) ?>" class="font-mono text-xs font-bold" style="color: <?= $color ?>;">#<?= e((string) $d['order_code']) ?></a>
                        <span class="text-[10px] px-1.5 py-0.5 rounded font-semibold" style="background: <?= $color ?>22; color: <?= $color ?>;"><?= e($lbl) ?></span>
                        <span class="text-[10px]" style="color: var(--color-text-tertiary);"><?= $minsAgo < 60 ? $minsAgo . 'm' : floor($minsAgo / 60) . 'h' ?> atras</span>
                    </div>
                    <div class="text-sm font-semibold mb-0.5" style="color: var(--color-text-primary);"><?= e((string) ($d['customer_name'] ?? 'Cliente')) ?></div>
                    <?php if (!empty($d['order_address'])): ?>
                    <div class="text-xs truncate" style="color: var(--color-text-tertiary);">📍 <?= e((string) $d['order_address']) ?></div>
                    <?php endif; ?>
                    <div class="mt-1.5 flex items-center gap-2 flex-wrap text-[11px]">
                        <span class="px-1.5 py-0.5 rounded" style="background: var(--color-bg-subtle);">
                            <?= e((string) ($d['currency'] ?? 'USD')) ?> <?= number_format((float) ($d['order_total'] ?? 0), 2) ?>
                        </span>
                        <?php if ((float) $d['cash_to_collect'] > 0): ?>
                        <span class="px-1.5 py-0.5 rounded font-semibold" style="background: rgba(16,185,129,.15); color:#34D399;">💵 Cobrar $<?= number_format((float) $d['cash_to_collect'], 2) ?></span>
                        <?php endif; ?>
                        <?php if ((float) $d['driver_commission'] > 0): ?>
                        <span class="px-1.5 py-0.5 rounded" style="background: rgba(6,182,212,.15); color:#22D3EE;">💸 Comision $<?= number_format((float) $d['driver_commission'], 2) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($d['driver_name'])): ?>
                        <span class="px-1.5 py-0.5 rounded" style="background: rgba(168,85,247,.15); color:#C084FC;">🛵 <?= e((string) $d['driver_name']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex flex-col gap-1 items-end flex-shrink-0">
                    <?php if (empty($d['driver_id'])): ?>
                    <form method="POST" action="<?= url('/delivery/' . $d['id'] . '/auto-assign') ?>" style="margin:0;">
                        <?= csrf_field() ?>
                        <button class="text-[11px] px-2 py-1 rounded font-bold" style="background: var(--color-primary); color: white;">🤖 Auto-asignar</button>
                    </form>
                    <select onchange="if(this.value) assignDriver(<?= (int) $d['id'] ?>, this.value)" class="text-[11px] rounded px-2 py-1" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
                        <option value="">Asignar a…</option>
                        <?php foreach ($drivers as $drv): ?>
                        <option value="<?= (int) $drv['id'] ?>"><?= e((string) $drv['name']) ?> (<?= e((string) ($statuses[$drv['status']][0] ?? $drv['status'])) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <a href="<?= url('/delivery/' . $d['id']) ?>" class="text-[11px] px-2 py-1 rounded" style="background: var(--color-bg-subtle); color: var(--color-text-primary);">Ver detalle →</a>
                    <a target="_blank" href="<?= url('/d/' . $d['tracking_token']) ?>" class="text-[11px] px-2 py-1 rounded" style="background: var(--color-bg-subtle); color: var(--color-text-primary);">🗺 Tracking</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

async function assignDriver(deliveryId, driverId) {
    try {
        const res = await fetch('<?= url('/delivery/') ?>' + deliveryId + '/assign', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: JSON.stringify({ driver_id: parseInt(driverId, 10), _csrf: csrfToken }),
        });
        const data = await res.json();
        if (data.success) location.reload();
        else alert('Error: ' + (data.message || ''));
    } catch (e) { alert('Error: ' + e.message); }
}

// Auto-refresh
setInterval(async () => {
    if (document.hidden) return;
    try {
        const res = await fetch('<?= url('/delivery/live') ?>', { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        if (data.success) {
            // Si cambio el count de entregas, recargar (simple y robusto)
            const currentCount = document.querySelectorAll('[data-delivery-id]').length;
            if (data.deliveries.length !== currentCount) location.reload();
        }
    } catch (e) {}
}, 10000);
</script>

<?php \App\Core\View::stop(); ?>
