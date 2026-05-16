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

<!-- Banner GPS: aviso del lock screen / background -->
<?php if (!empty($active)): ?>
<div id="gpsBanner" class="mx-4 mt-3 p-3 rounded-2xl flex items-start gap-2 hidden" style="background: linear-gradient(135deg, rgba(251,191,36,.15), rgba(251,191,36,.05)); border: 1px solid rgba(251,191,36,.3); color:#FCD34D;">
    <span class="text-lg leading-none">⚠️</span>
    <div class="flex-1 text-xs">
        <div class="font-bold">Mantén la pantalla encendida</div>
        <div style="color: rgba(252,211,77,.8);">Si bloqueas el telefono o sales de esta pestana, el GPS deja de transmitir. El cliente vera el marker congelado.</div>
    </div>
    <button onclick="document.getElementById('gpsBanner').style.display='none'" class="tap text-xs px-2 py-0.5 rounded" style="background: rgba(252,211,77,.15);">OK</button>
</div>
<?php endif; ?>

<!-- Entregas activas -->
<div class="px-4 mt-5">
    <h2 class="text-sm font-bold mb-2 flex items-center gap-2">
        <span>🛵 Mis entregas</span>
        <span class="text-[10px] px-2 py-0.5 rounded-full font-bold" style="background: var(--color-primary); color: white;"><?= count($active) ?></span>
        <span id="trackingBadge" class="ml-auto inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full hidden" style="background: rgba(16,185,129,.15); color:#34D399;">
            <span class="pulse-dot" style="width:6px;height:6px;"></span>
            TRANSMITIENDO
        </span>
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
const HAS_ACTIVE_DELIVERIES = <?= !empty($active) ? 'true' : 'false' ?>;

// Posicion del driver: ping cada 4s (con delivery activa) o 20s (idle)
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

            // Throttle adaptativo
            const activeId = document.querySelector('[data-delivery-id]')?.dataset.deliveryId;
            const minInterval = activeId ? 4000 : 20000;
            const now = Date.now();
            if (now - lastSentAt < minInterval) return;
            lastSentAt = now;

            // Postear ping al servidor
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
                showTrackingBadge(true);
            } catch (e) {}
        },
        (err) => {
            showTrackingBadge(false);
        },
        { enableHighAccuracy: true, maximumAge: 5000, timeout: 10000 }
    );
}
startGeolocation();

// =====================================================================
// Mantener la pantalla encendida (Wake Lock API) mientras hay entregas
// activas. Sin esto, cuando el driver baja el celular y se apaga la
// pantalla, iOS/Android suspenden el navegador y el GPS deja de
// transmitir, dejando al cliente con el marker congelado.
//
// Esta API NO es magia: no funciona si el driver bloquea manualmente el
// telefono o si cambia a otra app. Solo evita el auto-lock por
// inactividad mientras la app esta en primer plano.
// =====================================================================
let wakeLock = null;

async function acquireWakeLock() {
    if (!('wakeLock' in navigator)) return;
    if (wakeLock) return;
    try {
        wakeLock = await navigator.wakeLock.request('screen');
        wakeLock.addEventListener('release', () => { wakeLock = null; });
    } catch (e) { /* permission denied or unsupported */ }
}

async function releaseWakeLock() {
    if (wakeLock) {
        try { await wakeLock.release(); } catch (e) {}
        wakeLock = null;
    }
}

// Re-adquirir cuando la pestana vuelve a estar visible
document.addEventListener('visibilitychange', async () => {
    if (document.visibilityState === 'visible') {
        // Volvio a foreground -> mostrar banner si estuvo oculto y avisar
        if (HAS_ACTIVE_DELIVERIES) {
            await acquireWakeLock();
            if (gpsLostSince) {
                const sec = Math.round((Date.now() - gpsLostSince) / 1000);
                if (sec >= 5) showReturnAlert(sec);
                gpsLostSince = 0;
            }
            // Reanudar geolocalizacion
            if (!watchId && navigator.geolocation) startGeolocation();
        }
    } else {
        // Se fue al background -> marcar el momento para avisar al regresar
        if (HAS_ACTIVE_DELIVERIES) gpsLostSince = Date.now();
    }
});

if (HAS_ACTIVE_DELIVERIES) {
    acquireWakeLock();
    const banner = document.getElementById('gpsBanner');
    if (banner) banner.classList.remove('hidden');
}

// =====================================================================
// Aviso visual cuando el driver regresa despues de haber estado en
// background. El tracking del cliente estuvo congelado ese tiempo.
// =====================================================================
let gpsLostSince = 0;

function showReturnAlert(seconds) {
    const min = Math.floor(seconds / 60);
    const sec = seconds % 60;
    const human = min > 0 ? `${min}m ${sec}s` : `${sec}s`;
    const wrap = document.createElement('div');
    wrap.style.cssText = 'position:fixed; top:60px; left:50%; transform:translateX(-50%); z-index:10000; pointer-events:auto; max-width:90%;';
    wrap.innerHTML = `
        <div style="background: linear-gradient(135deg, rgba(239,68,68,.95), rgba(220,38,38,.95)); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,.15); color: white; padding: 14px 18px; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,.5); display:flex; align-items:start; gap:10px;">
            <span style="font-size: 22px; line-height:1;">⚠️</span>
            <div style="font-size: 13px;">
                <div style="font-weight: 800; margin-bottom: 2px;">GPS estuvo offline ${human}</div>
                <div style="opacity:.85; font-size: 11px;">El cliente vio el marker congelado durante ese tiempo. Trata de no bloquear la pantalla.</div>
            </div>
            <button onclick="this.parentElement.parentElement.remove()" style="background:none; border:none; color:rgba(255,255,255,.7); font-size:18px; cursor:pointer; line-height:1; padding:0; margin-left:4px;">×</button>
        </div>
    `;
    document.body.appendChild(wrap);
    setTimeout(() => wrap.remove(), 12000);
}

function showTrackingBadge(live) {
    const badge = document.getElementById('trackingBadge');
    if (!badge) return;
    if (live && HAS_ACTIVE_DELIVERIES) {
        badge.classList.remove('hidden');
        clearTimeout(badge._timer);
        // Si pasan 10s sin ping nuevo, lo apagamos
        badge._timer = setTimeout(() => badge.classList.add('hidden'), 10000);
    } else {
        badge.classList.add('hidden');
    }
}

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
