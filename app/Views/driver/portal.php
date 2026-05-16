<?php
/** @var array $driver */
/** @var array $active */
/** @var array $history */
/** @var array|null $shift */
/** @var array $stats */
/** @var array $statuses */
/** @var array $flow */
\App\Core\View::extend('layouts.driver');
\App\Core\View::start('content');
?>
<!-- Header sticky con saludo + estado -->
<header class="sticky top-0 z-30 px-4 py-3 backdrop-blur" style="background: rgba(6,11,22,.85); border-bottom: 1px solid var(--color-border-subtle);">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold" style="background: linear-gradient(135deg,#10B981,#06B6D4); color: white;"><?= e(strtoupper(mb_substr((string) $driver['name'], 0, 1))) ?></div>
        <div class="flex-1 min-w-0">
            <div class="text-sm font-bold truncate"><?= e((string) $driver['name']) ?></div>
            <div class="flex items-center gap-1.5 text-[11px]">
                <span class="w-2 h-2 rounded-full" style="background: <?= ($driver['status']??'offline') === 'online' ? '#22C55E' : (($driver['status']??'')==='on_delivery' ? '#F59E0B' : '#94A3B8') ?>;"></span>
                <span style="color: var(--color-text-tertiary);">
                    <?= match($driver['status']) { 'online'=>'Disponible', 'on_delivery'=>'En ruta', 'suspended'=>'Suspendido', default=>'Offline' } ?>
                </span>
            </div>
        </div>
        <?php if ($shift): ?>
        <form method="POST" action="<?= url('/driver/shift/close') ?>" class="m-0" onsubmit="return confirm('Cerrar turno?')">
            <?= csrf_field() ?>
            <input type="hidden" name="lat" id="closeLat"><input type="hidden" name="lng" id="closeLng">
            <button class="tap px-3 py-2 rounded-xl text-xs font-bold" style="background: rgba(239,68,68,.15); color:#F87171;">Cerrar turno</button>
        </form>
        <?php else: ?>
        <form method="POST" action="<?= url('/driver/shift/open') ?>" class="m-0">
            <?= csrf_field() ?>
            <input type="hidden" name="lat" id="openLat"><input type="hidden" name="lng" id="openLng">
            <button class="tap px-3 py-2 rounded-xl text-xs font-bold text-white" style="background: linear-gradient(135deg,#10B981,#06B6D4);">Abrir turno</button>
        </form>
        <?php endif; ?>
    </div>
    <a href="<?= url('/driver/logout') ?>" class="block text-center text-[11px] mt-2" style="color: var(--color-text-tertiary);">Salir</a>
</header>

<!-- KPIs del driver -->
<div class="px-4 pt-4 grid grid-cols-2 gap-2">
    <div class="surface p-3">
        <div class="text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">Entregas hoy</div>
        <div class="text-2xl font-bold"><?= (int) $stats['today_deliveries'] ?></div>
    </div>
    <div class="surface p-3">
        <div class="text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">Esta semana</div>
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

<!-- Entregas activas -->
<div class="px-4 mt-5">
    <h2 class="text-sm font-bold mb-2 flex items-center gap-2">
        <span>🛵 Mis entregas</span>
        <span class="text-[10px] px-2 py-0.5 rounded-full font-bold" style="background: var(--color-primary); color: white;"><?= count($active) ?></span>
    </h2>
    <?php if (empty($active)): ?>
    <div class="surface p-6 text-center">
        <div class="text-4xl mb-2">😎</div>
        <div class="font-semibold mb-1">Sin entregas asignadas</div>
        <div class="text-xs" style="color: var(--color-text-tertiary);">Cuando el local te asigne una entrega, aparecera aqui.</div>
    </div>
    <?php else: foreach ($active as $d):
        [$lbl, $color] = $statuses[$d['status']] ?? [$d['status'], '#94A3B8'];
        $allowed = $flow[$d['status']] ?? [];
    ?>
    <div class="surface p-4 mb-3" data-delivery-id="<?= (int) $d['id'] ?>">
        <div class="flex items-start justify-between mb-2">
            <div>
                <div class="font-mono text-[11px]" style="color: <?= $color ?>;">#<?= e((string) $d['order_code']) ?></div>
                <div class="font-bold text-base mt-0.5"><?= e((string) ($d['customer_name'] ?? 'Cliente')) ?></div>
            </div>
            <span class="text-[10px] px-2 py-0.5 rounded font-bold" style="background: <?= $color ?>22; color: <?= $color ?>;"><?= e($lbl) ?></span>
        </div>

        <?php if (!empty($d['order_address'])): ?>
        <a target="_blank" href="https://www.google.com/maps/dir/?api=1&destination=<?= urlencode((string) $d['order_address']) ?>" class="tap block text-sm py-2 px-3 rounded-xl mb-2" style="background: var(--color-bg-subtle);">
            📍 <?= e((string) $d['order_address']) ?>
        </a>
        <?php endif; ?>

        <div class="grid grid-cols-2 gap-2 text-xs">
            <div class="p-2 rounded-lg" style="background: rgba(16,185,129,.1);">
                <div class="text-[10px] uppercase font-bold" style="color:#34D399;">A cobrar</div>
                <div class="text-lg font-bold" style="color:#34D399;">
                    <?php if ((float) $d['cash_to_collect'] > 0): ?>$<?= number_format((float) $d['cash_to_collect'], 2) ?><?php else: ?>—<?php endif; ?>
                </div>
                <div class="text-[10px]" style="color: var(--color-text-tertiary);">
                    <?= ($d['order_payment_method']??'') === 'cash' ? '💵 Efectivo' : '💳 ' . e((string) ($d['order_payment_method'] ?? 'Pago digital')) ?>
                </div>
            </div>
            <div class="p-2 rounded-lg" style="background: rgba(6,182,212,.1);">
                <div class="text-[10px] uppercase font-bold" style="color:#22D3EE;">Tu comision</div>
                <div class="text-lg font-bold" style="color:#22D3EE;">$<?= number_format((float) $d['driver_commission'], 2) ?></div>
                <div class="text-[10px]" style="color: var(--color-text-tertiary);">Total: <?= number_format((float) $d['order_total'], 2) ?></div>
            </div>
        </div>

        <?php if (!empty($d['customer_phone'])): ?>
        <a href="tel:<?= e((string) $d['customer_phone']) ?>" class="tap block text-center text-sm py-2 px-3 rounded-xl mt-2" style="background: var(--color-bg-subtle); color: var(--color-primary);">📞 Llamar al cliente</a>
        <?php endif; ?>

        <?php if (!empty($d['delivery_notes'])): ?>
        <div class="text-xs mt-2 p-2 rounded-lg" style="background: rgba(251,191,36,.1); color:#FCD34D;">📝 <?= e((string) $d['delivery_notes']) ?></div>
        <?php endif; ?>

        <?php if ($allowed): ?>
        <div class="grid grid-cols-1 gap-2 mt-3">
            <?php foreach ($allowed as $next):
                [$nLbl, $nColor] = $statuses[$next] ?? [$next, '#94A3B8'];
                if (in_array($next, ['failed','cancelled'], true)) continue;
            ?>
            <?php if ($next === 'delivered' && (float) $d['cash_to_collect'] > 0): ?>
            <button onclick="confirmCashAndDeliver(<?= (int) $d['id'] ?>, <?= (float) $d['cash_to_collect'] ?>)" class="tap py-3 rounded-xl font-bold text-white text-base" style="background: <?= $nColor ?>;">→ <?= e($nLbl) ?> (cobrar $<?= number_format((float) $d['cash_to_collect'], 2) ?>)</button>
            <?php else: ?>
            <button onclick="transitionDelivery(<?= (int) $d['id'] ?>, '<?= $next ?>')" class="tap py-3 rounded-xl font-bold text-white text-base" style="background: <?= $nColor ?>;">→ <?= e($nLbl) ?></button>
            <?php endif; ?>
            <?php endforeach; ?>
            <button onclick="reportFailure(<?= (int) $d['id'] ?>)" class="tap py-2 rounded-xl text-xs" style="background: var(--color-bg-subtle); color:#F87171;">Reportar problema</button>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; endif; ?>
</div>

<!-- Historial -->
<?php if ($history): ?>
<div class="px-4 mt-6 pb-12">
    <h2 class="text-sm font-bold mb-2">📋 Historial reciente</h2>
    <?php foreach (array_slice($history, 0, 8) as $h):
        [$lbl, $color] = $statuses[$h['status']] ?? [$h['status'], '#94A3B8'];
    ?>
    <div class="surface p-3 mb-2 flex items-center gap-2">
        <span class="text-lg"><?= ($statuses[$h['status']][2] ?? '•') ?></span>
        <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold truncate"><?= e((string) ($h['customer_name'] ?? 'Cliente')) ?></div>
            <div class="text-[11px]" style="color: var(--color-text-tertiary);"><?= e((string) ($h['order_code'] ?? '')) ?> · <?= e(date('d/m H:i', strtotime((string) $h['created_at']))) ?></div>
        </div>
        <span class="text-[10px] px-2 py-0.5 rounded" style="background: <?= $color ?>22; color: <?= $color ?>;"><?= e($lbl) ?></span>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

// Posicion del driver: ping cada 15s mientras hay turno abierto
let watchId = null;
let lastSentAt = 0;

function startGeolocation() {
    if (!navigator.geolocation) return;
    watchId = navigator.geolocation.watchPosition(
        async (pos) => {
            // Capturar lat/lng en forms de open/close
            const ol = document.getElementById('openLat');  if (ol) ol.value = pos.coords.latitude;
            const og = document.getElementById('openLng');  if (og) og.value = pos.coords.longitude;
            const cl = document.getElementById('closeLat'); if (cl) cl.value = pos.coords.latitude;
            const cg = document.getElementById('closeLng'); if (cg) cg.value = pos.coords.longitude;

            // Throttle: max 1 ping cada 15s
            const now = Date.now();
            if (now - lastSentAt < 15000) return;
            lastSentAt = now;

            // Postear ping al servidor; si hay una entrega activa, asocio el ping a ella
            const activeId = document.querySelector('[data-delivery-id]')?.dataset.deliveryId;
            try {
                await fetch('<?= url('/driver/ping') ?>', {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json','X-CSRF-Token':csrfToken,'X-Requested-With':'XMLHttpRequest' },
                    body: JSON.stringify({
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude,
                        accuracy: pos.coords.accuracy,
                        speed: pos.coords.speed,
                        heading: pos.coords.heading,
                        delivery_id: activeId ? parseInt(activeId, 10) : null,
                        _csrf: csrfToken,
                    }),
                });
            } catch (e) {}
        },
        () => {},
        { enableHighAccuracy: true, maximumAge: 5000, timeout: 10000 }
    );
}
startGeolocation();

async function transitionDelivery(id, status, extra) {
    try {
        const res = await fetch('<?= url('/driver/delivery/') ?>' + id + '/status', {
            method: 'POST',
            headers: { 'Content-Type':'application/json','X-CSRF-Token':csrfToken,'X-Requested-With':'XMLHttpRequest' },
            body: JSON.stringify(Object.assign({ status, _csrf: csrfToken }, extra || {})),
        });
        const data = await res.json();
        if (data.success) location.reload();
        else alert('Error: ' + (data.message || ''));
    } catch (e) { alert(e.message); }
}

function confirmCashAndDeliver(id, expected) {
    const collected = prompt('Cuanto efectivo te dio el cliente?', expected.toFixed(2));
    if (collected === null) return;
    const n = parseFloat(collected);
    if (isNaN(n) || n < 0) { alert('Monto invalido.'); return; }
    transitionDelivery(id, 'delivered', { cash_collected: n });
}

function reportFailure(id) {
    const reason = prompt('Que paso? (cliente ausente, direccion mal, etc.)');
    if (!reason) return;
    transitionDelivery(id, 'failed', { reason });
}

// Refresh suave cada 20s para captar nuevas entregas asignadas
setInterval(async () => {
    if (document.hidden) return;
    try {
        const res = await fetch('<?= url('/driver/feed') ?>', { headers: {'Accept':'application/json'} });
        const data = await res.json();
        if (!data.success) return;
        const currentActive = document.querySelectorAll('[data-delivery-id]').length;
        if (data.active.length !== currentActive) location.reload();
    } catch (e) {}
}, 20000);
</script>

<?php \App\Core\View::stop(); ?>
