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
            const currentNew = parseInt(document.querySelector('[data-column="new"] .badge')?.textContent || '0', 10);
            if (data.counts.new > currentNew) location.reload();
        }
    } catch (e) {}
}, 15000);

// Pop-up de orden recien creada (mismo componente que en inbox)
let lastOrderCheck = new Date().toISOString();
const knownOrderIds = new Set();

async function checkNewOrders() {
    try {
        const res = await fetch('<?= url('/orders/recent?since=') ?>' + encodeURIComponent(lastOrderCheck), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (!data.success) return;
        lastOrderCheck = data.now || lastOrderCheck;
        for (const o of (data.orders || [])) {
            if (knownOrderIds.has(o.id)) continue;
            knownOrderIds.add(o.id);
            showOrderToast(o);
        }
    } catch (e) {}
}

function showOrderToast(o) {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        osc.frequency.setValueAtTime(880, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(1320, ctx.currentTime + 0.15);
        gain.gain.setValueAtTime(0.15, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
        osc.start(); osc.stop(ctx.currentTime + 0.4);
    } catch (e) {}

    const wrapper = document.getElementById('orderToastContainer') || (() => {
        const el = document.createElement('div');
        el.id = 'orderToastContainer';
        el.style.cssText = 'position:fixed; top:20px; right:20px; z-index:10000; display:flex; flex-direction:column; gap:12px; pointer-events:none;';
        document.body.appendChild(el);
        return el;
    })();

    const total = parseFloat(o.total).toFixed(2);
    const cur = o.currency || 'DOP';
    const tipo = o.delivery_type === 'pickup' ? '🛍 Pickup' : (o.delivery_type === 'dine_in' ? '🍴 Mesa' : '🛵 Delivery');
    const aiBadge = o.is_ai_generated == 1 ? '<span style="padding:1px 6px;border-radius:4px;background:rgba(124,58,237,.2);color:#A78BFA;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">🤖 IA</span>' : '';

    const toast = document.createElement('a');
    toast.href = '<?= url('/orders/') ?>' + o.id;
    toast.style.cssText = `pointer-events:auto;display:block;min-width:320px;max-width:380px;background:linear-gradient(135deg,rgba(16,185,129,.12),rgba(124,58,237,.12));border:1px solid rgba(16,185,129,.4);backdrop-filter:blur(20px);border-radius:16px;padding:14px 16px;box-shadow:0 10px 40px rgba(0,0,0,.4),0 0 30px rgba(16,185,129,.15);color:white;text-decoration:none;transform:translateX(120%);transition:transform .35s cubic-bezier(.2,.9,.3,1.4);cursor:pointer;`;
    toast.innerHTML = `<div style="display:flex;align-items:start;gap:12px;"><div style="font-size:32px;flex-shrink:0;filter:drop-shadow(0 0 8px rgba(16,185,129,.5));">🎉</div><div style="flex:1;min-width:0;"><div style="display:flex;align-items:center;gap:6px;margin-bottom:2px;flex-wrap:wrap;"><span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#10B981;">Nueva orden</span>${aiBadge}</div><div style="font-weight:700;font-size:15px;margin-bottom:1px;">#${o.code}</div><div style="font-size:12px;color:rgba(255,255,255,.7);margin-bottom:8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${o.customer_name||'Cliente'} · ${tipo}</div><div style="display:flex;align-items:center;justify-content:space-between;gap:8px;"><div style="font-size:18px;font-weight:800;background:linear-gradient(135deg,#10B981,#06B6D4);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;">${cur} ${total}</div><span style="font-size:11px;font-weight:600;padding:4px 10px;border-radius:8px;background:rgba(255,255,255,.08);">Ver →</span></div></div><button onclick="event.preventDefault();event.stopPropagation();this.parentElement.parentElement.remove()" style="background:none;border:none;color:rgba(255,255,255,.5);cursor:pointer;padding:0;font-size:18px;line-height:1;">×</button></div>`;
    wrapper.appendChild(toast);
    requestAnimationFrame(() => { toast.style.transform = 'translateX(0)'; });
    setTimeout(() => { toast.style.transform = 'translateX(120%)'; setTimeout(() => toast.remove(), 400); }, 12000);
}

setInterval(() => { if (!document.hidden) checkNewOrders(); }, 5000);
checkNewOrders();
</script>

<?php \App\Core\View::stop(); ?>
