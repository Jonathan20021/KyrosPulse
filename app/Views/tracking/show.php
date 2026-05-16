<?php
/** @var array $delivery */
/** @var array $statuses */
\App\Core\View::extend('layouts.driver');
\App\Core\View::start('content');

[$lbl, $color, $emoji] = $statuses[$delivery['status']] ?? [$delivery['status'], '#94A3B8', '•'];
$progressPct = match ($delivery['status']) {
    'pending'   => 8,
    'assigned'  => 22,
    'accepted'  => 40,
    'picked_up' => 60,
    'arriving'  => 85,
    'delivered' => 100,
    default     => 0,
};
$statusMsg = match ($delivery['status']) {
    'pending'   => 'Tu pedido fue recibido. Buscando repartidor...',
    'assigned'  => 'Tu repartidor fue asignado y va al restaurante.',
    'accepted'  => 'El restaurante esta preparando tu pedido.',
    'picked_up' => 'Tu repartidor ya tiene el pedido y va en camino.',
    'arriving'  => 'Tu repartidor esta llegando!',
    'delivered' => 'Pedido entregado. Buen provecho!',
    'failed'    => 'Hubo un problema con la entrega.',
    'cancelled' => 'Pedido cancelado.',
    default     => '',
};
?>
<style>
    /* Full-bleed mobile layout: map takes most of the screen */
    html, body { margin: 0; padding: 0; height: 100%; }
    .tracking-shell { display: flex; flex-direction: column; min-height: 100vh; min-height: 100dvh; }
    .map-wrap { position: relative; overflow: hidden; flex: 1 1 auto; min-height: 320px; }
    #map { position: absolute !important; top: 0; left: 0; right: 0; bottom: 0; width: 100% !important; height: 100% !important; background: #0F172A; }
    .map-wrap::before { content:''; position: absolute; top:0; left:0; right:0; height: 60px; pointer-events:none; z-index: 400; background: linear-gradient(180deg, rgba(6,11,22,.7), transparent); }
    .map-wrap::after { content:''; position: absolute; bottom:0; left:0; right:0; height: 80px; pointer-events:none; z-index: 400; background: linear-gradient(0deg, rgba(6,11,22,.8), transparent); }
    .bottom-sheet { background: linear-gradient(180deg, #0F172A 0%, #0B1325 100%); border-top: 1px solid rgba(255,255,255,.08); border-radius: 28px 28px 0 0; box-shadow: 0 -20px 60px rgba(0,0,0,.6); position: relative; z-index: 10; }
    .bottom-sheet::before { content:''; position: absolute; top: 8px; left: 50%; transform: translateX(-50%); width: 40px; height: 4px; border-radius: 2px; background: rgba(255,255,255,.15); }
    .step-dot { width: 10px; height: 10px; border-radius: 50%; background: rgba(255,255,255,.15); transition: all .4s; }
    .step-dot.done { background: linear-gradient(135deg,#10B981,#06B6D4); box-shadow: 0 0 12px rgba(16,185,129,.6); }
    .step-dot.current { background: #10B981; box-shadow: 0 0 0 4px rgba(16,185,129,.25), 0 0 16px rgba(16,185,129,.7); animation: stepPulse 1.5s ease-in-out infinite; }
    @keyframes stepPulse { 50% { box-shadow: 0 0 0 8px rgba(16,185,129,.1), 0 0 20px rgba(16,185,129,.9); } }
    .driver-icon { background: #10B981; border: 3px solid white; border-radius: 50%; width: 44px; height: 44px; display:flex; align-items:center; justify-content:center; font-size: 22px; box-shadow: 0 4px 20px rgba(16,185,129,.6), 0 0 0 8px rgba(16,185,129,.2); }
    .pin-icon { font-size: 36px; line-height: 1; filter: drop-shadow(0 4px 8px rgba(0,0,0,.5)); }
    .leaflet-routing-line { animation: dashFlow 30s linear infinite; }
    @keyframes dashFlow { to { stroke-dashoffset: -1000; } }
</style>

<div class="tracking-shell">
    <!-- Header sticky compacto -->
    <header class="px-4 py-3 flex items-center gap-3" style="background: rgba(6,11,22,.92); backdrop-filter: blur(20px); border-bottom: 1px solid var(--color-border-subtle); z-index: 20;">
        <?php if (!empty($delivery['tenant_logo'])): ?>
        <img src="<?= e((string) $delivery['tenant_logo']) ?>" class="w-9 h-9 rounded-xl object-cover">
        <?php else: ?>
        <div class="w-9 h-9 rounded-xl flex items-center justify-center text-xl" style="background: var(--color-bg-subtle);">🍽</div>
        <?php endif; ?>
        <div class="flex-1 min-w-0">
            <div class="text-[10px] font-bold uppercase tracking-wider flex items-center gap-2" style="color: var(--color-text-tertiary);">
                <span>Pedido en camino</span>
                <span id="liveChip" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full" style="background: rgba(16,185,129,.15); color:#34D399;">
                    <span class="pulse-dot" style="width:6px;height:6px;"></span>
                    <span class="live-label text-[9px] font-extrabold tracking-widest">EN VIVO</span>
                </span>
            </div>
            <div class="font-bold text-sm truncate"><?= e((string) ($delivery['tenant_name'] ?? 'Restaurante')) ?></div>
        </div>
        <?php if (!empty($delivery['tenant_phone'])): ?>
        <a href="tel:<?= e((string) $delivery['tenant_phone']) ?>" class="tap w-9 h-9 rounded-full flex items-center justify-center" style="background: var(--color-bg-subtle); color: var(--color-text-primary);">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
        </a>
        <?php endif; ?>
    </header>

    <!-- Mapa fullscreen -->
    <div class="map-wrap">
        <div id="map"></div>
        <!-- Floating ETA chip top-center -->
        <div id="etaChip" class="hidden absolute top-4 left-1/2 -translate-x-1/2 z-[500] px-4 py-2 rounded-full text-sm font-bold flex items-center gap-2" style="background: rgba(15,23,42,.95); backdrop-filter: blur(10px); border:1px solid rgba(255,255,255,.1); color: white; box-shadow: 0 4px 20px rgba(0,0,0,.5);">
            <span class="pulse-dot" style="width:8px;height:8px;"></span>
            <span id="etaText">Calculando ruta…</span>
        </div>
    </div>

    <!-- Bottom sheet con estado + driver + total -->
    <div class="bottom-sheet pt-4 pb-6 px-5">
        <!-- Estado hero -->
        <div class="text-center mb-3">
            <div class="text-5xl mb-1 leading-none"><?= $emoji ?></div>
            <div class="text-[10px] uppercase tracking-[.15em] font-bold" style="color: <?= $color ?>;">Pedido <?= e((string) ($delivery['order_code'] ?? '')) ?></div>
            <div class="text-2xl font-bold mt-0.5"><?= e($lbl) ?></div>
            <div class="text-xs mt-1" style="color: var(--color-text-tertiary);"><?= e($statusMsg) ?></div>
        </div>

        <!-- Pasos -->
        <div class="flex items-center justify-between max-w-xs mx-auto mb-5 px-2">
            <?php
            $steps = ['pending','assigned','picked_up','arriving','delivered'];
            $currentIdx = array_search($delivery['status'], $steps, true);
            foreach ($steps as $i => $s):
                $cls = 'step-dot';
                if ($currentIdx !== false && $i < $currentIdx) $cls .= ' done';
                elseif ($currentIdx !== false && $i === $currentIdx) $cls .= ' current';
            ?>
            <?php if ($i > 0): ?>
            <div class="flex-1 h-0.5 mx-1" style="background: <?= ($currentIdx !== false && $i <= $currentIdx) ? 'linear-gradient(90deg,#10B981,#06B6D4)' : 'rgba(255,255,255,.1)' ?>;"></div>
            <?php endif; ?>
            <div class="<?= $cls ?>"></div>
            <?php endforeach; ?>
        </div>

        <!-- Driver card (si esta asignado) -->
        <?php if (!empty($delivery['driver_name'])): ?>
        <div class="flex items-center gap-3 p-3 rounded-2xl mb-3" style="background: var(--color-bg-subtle);">
            <div class="w-11 h-11 rounded-full flex items-center justify-center font-bold text-lg flex-shrink-0" style="background: linear-gradient(135deg,#10B981,#06B6D4); color: white;">
                <?= e(strtoupper(mb_substr((string) $delivery['driver_name'], 0, 1))) ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-[10px] uppercase tracking-wider font-bold" style="color: var(--color-text-tertiary);">Tu repartidor</div>
                <div class="font-bold text-sm truncate"><?= e((string) $delivery['driver_name']) ?></div>
                <div class="text-[11px]" style="color: var(--color-text-tertiary);">
                    <?= match($delivery['vehicle_type']) { 'bike'=>'🚲 Bicicleta','motorcycle'=>'🛵 Moto','car'=>'🚗 Auto','walk'=>'🚶 A pie',default=>'🛵 Moto' } ?>
                </div>
            </div>
            <?php if (!empty($delivery['driver_phone'])): ?>
            <a href="tel:<?= e((string) $delivery['driver_phone']) ?>" class="tap px-4 py-2.5 rounded-xl text-white font-bold text-sm flex items-center gap-1.5" style="background: linear-gradient(135deg,#10B981,#06B6D4); box-shadow: 0 4px 16px rgba(16,185,129,.35);">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                Llamar
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Total + cash -->
        <div class="grid <?= ((float) $delivery['cash_to_collect'] > 0 && $delivery['status'] !== 'delivered') ? 'grid-cols-2' : 'grid-cols-1' ?> gap-2 mb-2">
            <div class="p-3 rounded-2xl" style="background: var(--color-bg-subtle);">
                <div class="text-[10px] uppercase tracking-wider font-bold" style="color: var(--color-text-tertiary);">Total</div>
                <div class="text-xl font-bold"><?= e((string) ($delivery['currency'] ?? 'USD')) ?> <?= number_format((float) ($delivery['order_total'] ?? 0), 2) ?></div>
            </div>
            <?php if ((float) $delivery['cash_to_collect'] > 0 && $delivery['status'] !== 'delivered'): ?>
            <div class="p-3 rounded-2xl" style="background: linear-gradient(135deg, rgba(251,191,36,.18), rgba(251,191,36,.08)); border:1px solid rgba(251,191,36,.3);">
                <div class="text-[10px] uppercase tracking-wider font-bold" style="color:#FCD34D;">💵 Ten listo</div>
                <div class="text-xl font-bold" style="color:#FCD34D;">$<?= number_format((float) $delivery['cash_to_collect'], 2) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Rating al final -->
        <?php if ($delivery['status'] === 'delivered' && empty($delivery['customer_rating'])): ?>
        <div class="mt-4 p-4 rounded-2xl" style="background: var(--color-bg-subtle);">
            <div class="font-bold text-sm mb-2 text-center">Como te fue? Califica al repartidor</div>
            <div class="flex justify-center gap-2 mb-3" id="ratingStars">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                <button onclick="setRating(<?= $i ?>)" data-star="<?= $i ?>" class="tap text-3xl opacity-30">⭐</button>
                <?php endfor; ?>
            </div>
            <textarea id="feedback" rows="2" placeholder="Comentario opcional..." class="w-full px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-elevated); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);"></textarea>
            <button id="submitRating" onclick="submitRating()" disabled class="tap mt-2 w-full py-3 rounded-xl text-white font-bold opacity-50" style="background: linear-gradient(135deg,#10B981,#06B6D4);">Enviar</button>
        </div>
        <?php endif; ?>

        <div class="text-center text-[10px] mt-4" style="color: var(--color-text-tertiary);">
            Powered by <?= e((string) config('app.name', 'Pulse')) ?> · No compartas este link
        </div>
    </div>
</div>

<script>
const TOKEN = <?= json_encode((string) $delivery['tracking_token']) ?>;
const CURRENT_STATUS = <?= json_encode((string) $delivery['status']) ?>;

// Leaflet se carga sincronicamente en el <head>, asi que ya esta disponible.
// Pero esperamos DOMContentLoaded para que #map exista en el DOM.
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initMap);
else initMap();

let map, driverMarker, pickupMarker, dropoffMarker, routeLine, trailLine;
let driverTarget = null;       // [lat,lng] objetivo del marker (interpolacion suave)
let driverCurrent = null;      // posicion actual visible del marker
let lastRouteRequest = 0;
let lastRouteSig = '';
let lastState = null;          // ultimo snapshot recibido por SSE
let trailPoints = [];          // trail acumulado en cliente
let evtSource = null;

function initMap() {
    if (window._mapInit) return;
    window._mapInit = true;

    map = L.map('map', {
        zoomControl: true,
        attributionControl: true,
    }).setView([18.4861, -69.9312], 13);

    // Dark tiles — CartoDB Dark Matter (gratis, sin API key)
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        maxZoom: 19,
        subdomains: 'abcd',
        attribution: '© OpenStreetMap, © CartoDB',
    }).addTo(map);

    setTimeout(() => map.invalidateSize(), 100);
    window.addEventListener('resize', () => map.invalidateSize());

    connectStream();

    // Loop de interpolacion suave del marker del driver
    requestAnimationFrame(animateDriver);
}

function makeIcon(html, size = 44, anchor = null) {
    return L.divIcon({
        html: html,
        className: 'custom-marker',
        iconSize: [size, size],
        iconAnchor: anchor || [size/2, size/2],
    });
}

const ICON_PICKUP  = makeIcon('<div class="pin-icon">🍽️</div>', 36, [18, 36]);
const ICON_DROPOFF = makeIcon('<div class="pin-icon">📍</div>', 36, [18, 36]);
const ICON_DRIVER  = makeIcon('<div class="driver-icon">🛵</div>', 44);

/**
 * Conexion en vivo via Server-Sent Events. El browser reconecta automaticamente
 * cuando el server cierra el stream (cada ~55s) — no hay que hacer polling.
 */
function connectStream() {
    if (evtSource) try { evtSource.close(); } catch (e) {}
    evtSource = new EventSource('<?= url('/d/') ?>' + TOKEN + '/stream');

    setLiveIndicator(true);

    evtSource.addEventListener('open', e => {
        const data = JSON.parse(e.data);
        applySnapshot(data);
    });
    evtSource.addEventListener('driver_position', e => {
        const p = JSON.parse(e.data);
        driverTarget = [p.lat, p.lng];
        trailPoints.push([p.lat, p.lng]);
        if (trailPoints.length > 100) trailPoints.shift();
        if (!driverMarker) {
            driverCurrent = driverTarget;
            driverMarker = L.marker(driverTarget, { icon: ICON_DRIVER, zIndexOffset: 1000 }).addTo(map);
        }
        if (trailLine) trailLine.setLatLngs(trailPoints);
        else trailLine = L.polyline(trailPoints, { color: '#22D3EE', weight: 3, opacity: .35, dashArray: '4 8' }).addTo(map);

        // Recalcular ruta con la nueva posicion del driver si corresponde
        if (lastState) maybeUpdateRoute({ ...lastState, driver_position: p });
        setLiveIndicator(true);
    });
    evtSource.addEventListener('status_change', e => {
        const p = JSON.parse(e.data);
        if (p.status !== CURRENT_STATUS) {
            // Cambio de estado importante (asignado, recogido, llegando, entregado)
            // -> recargar para refrescar bottom sheet + UI completa
            location.reload();
        }
    });
    evtSource.addEventListener('driver_assigned', e => {
        // Cuando se asigna o cambia driver, recargamos para refrescar la driver card
        location.reload();
    });
    evtSource.addEventListener('heartbeat', e => {
        setLiveIndicator(true);
    });
    evtSource.addEventListener('close', e => {
        // El server pidio reconectar — EventSource lo hace solo, pero por si acaso
        setTimeout(connectStream, 500);
    });
    evtSource.onerror = () => {
        setLiveIndicator(false);
        // EventSource reconecta automaticamente; si falla persistentemente,
        // forzamos reconexion en 5s.
        setTimeout(() => {
            if (evtSource && evtSource.readyState === EventSource.CLOSED) {
                connectStream();
            }
        }, 5000);
    };
}

function applySnapshot(data) {
    lastState = data;
    const points = [];
    if (data.pickup) {
        if (!pickupMarker) pickupMarker = L.marker([data.pickup.lat, data.pickup.lng], { icon: ICON_PICKUP }).addTo(map);
        else pickupMarker.setLatLng([data.pickup.lat, data.pickup.lng]);
        points.push([data.pickup.lat, data.pickup.lng]);
    }
    if (data.dropoff) {
        if (!dropoffMarker) dropoffMarker = L.marker([data.dropoff.lat, data.dropoff.lng], { icon: ICON_DROPOFF }).addTo(map);
        else dropoffMarker.setLatLng([data.dropoff.lat, data.dropoff.lng]);
        points.push([data.dropoff.lat, data.dropoff.lng]);
    }
    if (data.driver_position) {
        const dp = [data.driver_position.lat, data.driver_position.lng];
        driverTarget = dp;
        if (!driverMarker) {
            driverCurrent = dp;
            driverMarker = L.marker(dp, { icon: ICON_DRIVER, zIndexOffset: 1000 }).addTo(map);
        }
        points.push(dp);
    }
    if (data.trail && data.trail.length > 1) {
        trailPoints = data.trail.map(p => [p.lat, p.lng]).reverse();
        if (trailLine) trailLine.setLatLngs(trailPoints);
        else trailLine = L.polyline(trailPoints, { color: '#22D3EE', weight: 3, opacity: .35, dashArray: '4 8' }).addTo(map);
    }
    maybeUpdateRoute(data);
    if (!window._fitDone && points.length > 1) {
        map.fitBounds(points, { padding: [80, 80], maxZoom: 16 });
        window._fitDone = true;
    }
}

function setLiveIndicator(live) {
    const chip = document.getElementById('liveChip');
    if (!chip) return;
    chip.classList.toggle('opacity-50', !live);
    chip.querySelector('.live-label').textContent = live ? 'EN VIVO' : 'reconectando…';
}

async function maybeUpdateRoute(data) {
    let from, to;
    const status = data.status;
    if (data.driver_position && data.dropoff && ['picked_up','arriving'].includes(status)) {
        from = data.driver_position; to = data.dropoff;
    } else if (data.pickup && data.dropoff && ['pending','assigned','accepted'].includes(status)) {
        from = data.pickup; to = data.dropoff;
    } else {
        return;
    }
    const sig = `${from.lat.toFixed(4)},${from.lng.toFixed(4)}|${to.lat.toFixed(4)},${to.lng.toFixed(4)}`;
    if (sig === lastRouteSig) return;
    // Throttle: max 1 route req cada 20s para respetar OSRM publico
    if (Date.now() - lastRouteRequest < 20000) return;
    lastRouteRequest = Date.now();
    lastRouteSig = sig;
    try {
        const url = `https://router.project-osrm.org/route/v1/driving/${from.lng},${from.lat};${to.lng},${to.lat}?overview=full&geometries=geojson&alternatives=false&steps=false`;
        const res = await fetch(url);
        const json = await res.json();
        if (!json.routes || !json.routes[0]) return;
        const route = json.routes[0];
        const coords = route.geometry.coordinates.map(c => [c[1], c[0]]); // GeoJSON: [lng,lat] -> [lat,lng]
        if (routeLine) routeLine.setLatLngs(coords);
        else routeLine = L.polyline(coords, { color: '#10B981', weight: 5, opacity: .9, dashArray: '12 6', className: 'leaflet-routing-line' }).addTo(map);

        // ETA chip
        const etaMin = Math.max(1, Math.round(route.duration / 60));
        const distKm = (route.distance / 1000).toFixed(1);
        const chip = document.getElementById('etaChip');
        const txt = document.getElementById('etaText');
        if (chip && txt) {
            txt.textContent = `Llega en ~${etaMin} min · ${distKm} km`;
            chip.classList.remove('hidden');
        }
    } catch (e) { /* ignore */ }
}

// Interpolacion suave del marker del driver (10fps approx)
function animateDriver() {
    if (driverMarker && driverCurrent && driverTarget) {
        const dx = driverTarget[0] - driverCurrent[0];
        const dy = driverTarget[1] - driverCurrent[1];
        if (Math.abs(dx) > 1e-7 || Math.abs(dy) > 1e-7) {
            driverCurrent = [driverCurrent[0] + dx * 0.15, driverCurrent[1] + dy * 0.15];
            driverMarker.setLatLng(driverCurrent);
        }
    }
    requestAnimationFrame(animateDriver);
}

// Rating
let pickedRating = 0;
function setRating(r) {
    pickedRating = r;
    document.querySelectorAll('#ratingStars button').forEach(b => {
        b.style.opacity = parseInt(b.dataset.star, 10) <= r ? '1' : '0.3';
    });
    const btn = document.getElementById('submitRating');
    if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
}
async function submitRating() {
    if (!pickedRating) return;
    const feedback = document.getElementById('feedback').value;
    try {
        const res = await fetch('<?= url('/d/') ?>' + TOKEN + '/rate', {
            method: 'POST',
            headers: { 'Content-Type':'application/json' },
            body: JSON.stringify({ rating: pickedRating, feedback }),
        });
        const data = await res.json();
        if (data.success) { alert('Gracias por calificar!'); location.reload(); }
        else alert('Error: ' + (data.message || ''));
    } catch (e) { alert(e.message); }
}
</script>
<?php \App\Core\View::stop(); ?>
