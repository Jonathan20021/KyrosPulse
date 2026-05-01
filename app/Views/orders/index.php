<?php
/** @var array $kanban */
/** @var array $stats */
/** @var array $statuses */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('components.page_header', [
    'title'    => 'Ordenes',
    'subtitle' => 'Kanban en tiempo real. Arrastra entre columnas para cambiar de estado o usa el menu de cada tarjeta.',
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#34D399;"><?= e((string) $flash) ?></div>
<?php endif; ?>

<!-- KPIs -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <?php foreach ([
        ['Hoy', $stats['today'], '#7C3AED', '📅'],
        ['Ingresos hoy', '$' . number_format((float) $stats['revenue_today'], 2), '#10B981', '💰'],
        ['Pendientes', $stats['pending'], '#F59E0B', '⏳'],
        ['Ticket promedio (30d)', '$' . number_format((float) $stats['avg_ticket'], 2), '#06B6D4', '🎯'],
    ] as [$lbl, $val, $col, $em]): ?>
    <div class="surface p-4">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] uppercase tracking-wider font-semibold" style="color: var(--color-text-tertiary);"><?= e($lbl) ?></span>
            <span class="text-xl"><?= $em ?></span>
        </div>
        <div class="text-2xl font-bold" style="color: var(--color-text-primary);"><?= e((string) $val) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="flex items-center gap-2 mb-4">
    <a href="<?= url('/orders/create') ?>" class="px-4 py-2 rounded-xl text-white font-semibold shadow-lg flex items-center gap-2" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Nueva orden manual
    </a>
    <a href="<?= url('/menu') ?>" class="px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary);">Editar menu</a>
    <a href="<?= url('/settings/restaurant') ?>" class="px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary);">Ajustes</a>
</div>

<!-- Kanban -->
<div class="overflow-x-auto pb-2">
    <div class="grid grid-flow-col auto-cols-[minmax(280px,1fr)] gap-3">
        <?php foreach (['new','confirmed','preparing','ready','out_for_delivery','delivered','cancelled'] as $st):
            [$lbl, $color, $emoji] = $statuses[$st] ?? [$st, '#94A3B8', '•'];
            $orders = $kanban[$st] ?? [];
        ?>
        <div class="surface p-3" data-column="<?= $st ?>">
            <div class="flex items-center gap-2 mb-3 pb-2 border-b" style="border-color: var(--color-border-subtle);">
                <span class="text-base"><?= $emoji ?></span>
                <h3 class="font-bold text-sm" style="color: <?= $color ?>;"><?= e($lbl) ?></h3>
                <span class="badge" style="background: <?= $color ?>22; color: <?= $color ?>;"><?= count($orders) ?></span>
            </div>

            <?php if (empty($orders)): ?>
            <div class="text-xs py-6 text-center" style="color: var(--color-text-tertiary);">Sin ordenes</div>
            <?php else: foreach ($orders as $o):
                $name = trim((($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '')) ?: ($o['customer_name'] ?? 'Cliente'));
                $minsAgo = floor((time() - strtotime((string) $o['created_at'])) / 60);
            ?>
            <a href="<?= url('/orders/' . $o['id']) ?>" class="block rounded-xl p-3 mb-2 border hover:border-violet-500/40 transition" style="background: var(--color-bg-elevated); border-color: var(--color-border-subtle);">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-[10px] font-mono font-bold" style="color: <?= $color ?>;">#<?= e((string) $o['code']) ?></span>
                    <span class="text-[10px]" style="color: var(--color-text-tertiary);"><?= $minsAgo < 60 ? $minsAgo . 'm' : floor($minsAgo / 60) . 'h' ?></span>
                </div>
                <div class="font-semibold text-sm mb-1 truncate" style="color: var(--color-text-primary);"><?= e($name) ?></div>
                <div class="flex items-center gap-1 text-[10px] mb-1.5">
                    <span class="px-1.5 py-0.5 rounded" style="background: var(--color-bg-subtle); color: var(--color-text-secondary);">
                        <?= match($o['delivery_type']) { 'pickup' => '🛍 Pickup', 'dine_in' => '🍴 Mesa', default => '🛵 Delivery' } ?>
                    </span>
                    <?php if (!empty($o['is_ai_generated'])): ?>
                    <span class="px-1.5 py-0.5 rounded" style="background: rgba(124,58,237,.15); color:#A78BFA;">🤖 IA</span>
                    <?php endif; ?>
                    <span class="ml-auto font-bold" style="color: var(--color-primary);"><?= e((string) $o['currency']) ?> <?= number_format((float) $o['total'], 2) ?></span>
                </div>
                <?php if (!empty($o['delivery_address']) && $o['delivery_type'] === 'delivery'): ?>
                <div class="text-[10px] truncate" style="color: var(--color-text-tertiary);">📍 <?= e((string) $o['delivery_address']) ?></div>
                <?php endif; ?>
                <?php if (!empty(\App\Models\Order::STATUS_FLOW[$st])): ?>
                <div class="flex items-center gap-1 mt-2 flex-wrap">
                    <?php foreach (\App\Models\Order::STATUS_FLOW[$st] as $next):
                        [$nLbl, $nColor] = $statuses[$next] ?? [$next, '#94A3B8'];
                    ?>
                    <button onclick="event.preventDefault(); event.stopPropagation(); transitionOrder(<?= (int) $o['id'] ?>, '<?= $next ?>', this)" class="text-[10px] px-2 py-0.5 rounded font-semibold" style="background: <?= $nColor ?>22; color: <?= $nColor ?>;">→ <?= e($nLbl) ?></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </a>
            <?php endforeach; endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
async function transitionOrder(id, status, btn) {
    btn.disabled = true;
    btn.style.opacity = '0.5';
    try {
        const res = await fetch('<?= url('/orders/') ?>' + id + '/status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: JSON.stringify({ status: status }),
        });
        const data = await res.json();
        if (data.success) location.reload();
        else { alert('Error: ' + (data.error || '')); btn.disabled = false; btn.style.opacity = ''; }
    } catch (e) { alert('Error: ' + e.message); }
}

// Auto-refresh KPIs cada 15s
setInterval(async () => {
    if (document.hidden) return;
    try {
        const res = await fetch('<?= url('/orders/live') ?>', { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        if (data.success) {
            // Si hay nuevas ordenes en la columna 'new', refresca
            const currentNew = parseInt(document.querySelector('[data-column="new"] .badge')?.textContent || '0', 10);
            if (data.counts.new > currentNew) location.reload();
        }
    } catch (e) {}
}, 15000);
</script>

<?php \App\Core\View::stop(); ?>
