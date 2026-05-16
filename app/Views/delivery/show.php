<?php
/** @var array $delivery */
/** @var array|null $order */
/** @var array $drivers */
/** @var array $statuses */
/** @var array $flow */
/** @var array $trail */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

[$lbl, $color, $emoji] = $statuses[$delivery['status']] ?? [$delivery['status'], '#94A3B8', '•'];
$allowed = $flow[$delivery['status']] ?? [];
?>
<?php \App\Core\View::include('components.page_header', [
    'title'    => 'Delivery #' . ($delivery['order_code'] ?? $delivery['id']),
    'subtitle' => 'Detalle, asignacion y tracking en vivo del repartidor.',
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#34D399;"><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flash = flash('error')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(239,68,68,.08); border-color: rgba(239,68,68,.3); color:#F87171;"><?= e((string) $flash) ?></div>
<?php endif; ?>

<div class="grid lg:grid-cols-3 gap-4">
    <!-- Columna izquierda: detalle -->
    <div class="lg:col-span-2 space-y-4">
        <div class="surface p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl" style="background: <?= $color ?>22; color: <?= $color ?>;"><?= $emoji ?></div>
                    <div>
                        <div class="text-xs uppercase tracking-wider" style="color: var(--color-text-tertiary);">Estado</div>
                        <div class="text-xl font-bold" style="color: <?= $color ?>;"><?= e($lbl) ?></div>
                    </div>
                </div>
                <a target="_blank" href="<?= url('/d/' . $delivery['tracking_token']) ?>" class="px-3 py-2 rounded-xl text-sm font-semibold" style="background: var(--color-primary); color: white;">🗺 Tracking publico</a>
            </div>

            <div class="grid md:grid-cols-2 gap-3 mt-4">
                <div>
                    <div class="text-[10px] uppercase tracking-wider font-semibold mb-1" style="color: var(--color-text-tertiary);">Cliente</div>
                    <div class="font-semibold" style="color: var(--color-text-primary);"><?= e((string) ($delivery['customer_name'] ?? 'Cliente')) ?></div>
                    <?php if (!empty($delivery['customer_phone'])): ?>
                    <a href="tel:<?= e((string) $delivery['customer_phone']) ?>" class="text-sm" style="color: var(--color-primary);"><?= e((string) $delivery['customer_phone']) ?></a>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="text-[10px] uppercase tracking-wider font-semibold mb-1 flex items-center gap-1.5" style="color: var(--color-text-tertiary);">
                        <span>Direccion de entrega</span>
                        <?php
                        // El order tiene delivery_location_source; lo cargamos via Order para no
                        // alterar el JOIN del Delivery::findById.
                        $orderSrc = isset($order) ? (string) ($order['delivery_location_source'] ?? '') : '';
                        if ($orderSrc !== ''):
                            [$srcLabel, $srcColor, $srcIcon] = match ($orderSrc) {
                                'gps'      => ['GPS preciso', '#22C55E', '📡'],
                                'map_pin'  => ['Pin en mapa',  '#22C55E', '📍'],
                                'geocoded' => ['Geocoded',     '#F59E0B', '~'],
                                default    => ['Manual',       '#94A3B8', '✎'],
                            };
                        ?>
                        <span class="text-[9px] px-1.5 py-0.5 rounded font-bold tracking-wider" style="background: <?= $srcColor ?>22; color: <?= $srcColor ?>;"><?= $srcIcon ?> <?= e($srcLabel) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="text-sm" style="color: var(--color-text-primary);"><?= e((string) ($delivery['order_address'] ?? $delivery['dropoff_address'] ?? '—')) ?></div>
                </div>
                <div>
                    <div class="text-[10px] uppercase tracking-wider font-semibold mb-1" style="color: var(--color-text-tertiary);">Total orden</div>
                    <div class="font-bold text-lg" style="color: var(--color-text-primary);"><?= e((string) ($delivery['currency'] ?? 'USD')) ?> <?= number_format((float) ($delivery['order_total'] ?? 0), 2) ?></div>
                </div>
                <div>
                    <div class="text-[10px] uppercase tracking-wider font-semibold mb-1" style="color: var(--color-text-tertiary);">Pago</div>
                    <div class="text-sm font-semibold" style="color: var(--color-text-primary);">
                        <?= e((string) ($delivery['order_payment_method'] ?? '—')) ?> ·
                        <span style="color: <?= ($delivery['order_payment_status']??'') === 'paid' ? '#34D399' : '#F59E0B' ?>"><?= e((string) ($delivery['order_payment_status'] ?? '—')) ?></span>
                    </div>
                </div>
                <?php if ((float) $delivery['cash_to_collect'] > 0): ?>
                <div class="md:col-span-2 p-3 rounded-xl" style="background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.3);">
                    <div class="text-[10px] uppercase tracking-wider font-bold" style="color:#34D399;">A cobrar en efectivo</div>
                    <div class="text-2xl font-bold" style="color:#34D399;">$<?= number_format((float) $delivery['cash_to_collect'], 2) ?></div>
                </div>
                <?php endif; ?>
                <div>
                    <div class="text-[10px] uppercase tracking-wider font-semibold mb-1" style="color: var(--color-text-tertiary);">Comision al driver</div>
                    <div class="font-semibold" style="color: var(--color-text-primary);">$<?= number_format((float) $delivery['driver_commission'], 2) ?></div>
                </div>
                <div>
                    <div class="text-[10px] uppercase tracking-wider font-semibold mb-1" style="color: var(--color-text-tertiary);">Fee delivery</div>
                    <div class="font-semibold" style="color: var(--color-text-primary);">$<?= number_format((float) $delivery['delivery_fee'], 2) ?></div>
                </div>
            </div>

            <?php if ($allowed): ?>
            <div class="mt-4 pt-4 border-t" style="border-color: var(--color-border-subtle);">
                <div class="text-[10px] uppercase tracking-wider font-semibold mb-2" style="color: var(--color-text-tertiary);">Cambiar estado manualmente</div>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($allowed as $next):
                        [$nLbl, $nColor] = $statuses[$next] ?? [$next, '#94A3B8'];
                    ?>
                    <form method="POST" action="<?= url('/delivery/' . $delivery['id'] . '/status') ?>" style="margin:0;" onsubmit="return confirm('Cambiar a <?= e($nLbl) ?>?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="status" value="<?= $next ?>">
                        <button class="text-xs px-3 py-1.5 rounded-lg font-semibold" style="background: <?= $nColor ?>22; color: <?= $nColor ?>;">→ <?= e($nLbl) ?></button>
                    </form>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Mapa simple (mostrara pickup, dropoff, driver actual y trail) -->
        <div class="surface p-3">
            <div class="flex items-center justify-between mb-2">
                <h3 class="font-bold text-sm" style="color: var(--color-text-primary);">Mapa en vivo</h3>
                <span id="mapStatus" class="text-[10px]" style="color: var(--color-text-tertiary);">cargando…</span>
            </div>
            <div id="map" style="height: 380px; border-radius: 12px; overflow: hidden;"></div>
        </div>
    </div>

    <!-- Columna derecha: driver -->
    <div class="space-y-4">
        <div class="surface p-5">
            <h3 class="font-bold text-sm mb-3" style="color: var(--color-text-primary);">Repartidor</h3>
            <?php if (!empty($delivery['driver_name'])): ?>
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-full flex items-center justify-center font-bold text-lg" style="background: linear-gradient(135deg,#10B981,#06B6D4); color: white;"><?= e(strtoupper(mb_substr((string) $delivery['driver_name'], 0, 1))) ?></div>
                <div>
                    <div class="font-bold" style="color: var(--color-text-primary);"><?= e((string) $delivery['driver_name']) ?></div>
                    <?php if (!empty($delivery['driver_phone'])): ?>
                    <a href="tel:<?= e((string) $delivery['driver_phone']) ?>" class="text-sm" style="color: var(--color-primary);">📞 <?= e((string) $delivery['driver_phone']) ?></a>
                    <?php endif; ?>
                    <?php if (!empty($delivery['vehicle_type'])): ?>
                    <div class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">
                        <?= match($delivery['vehicle_type']) { 'bike'=>'🚲 Bicicleta','motorcycle'=>'🛵 Moto','car'=>'🚗 Auto','walk'=>'🚶 A pie',default=>'🛵' } ?>
                        <?= !empty($delivery['vehicle_plate']) ? '· ' . e((string) $delivery['vehicle_plate']) : '' ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="text-sm mb-3" style="color: var(--color-text-tertiary);">Sin repartidor asignado.</div>
            <div class="space-y-2">
                <form method="POST" action="<?= url('/delivery/' . $delivery['id'] . '/auto-assign') ?>" style="margin:0;">
                    <?= csrf_field() ?>
                    <button class="w-full px-3 py-2 rounded-xl text-white font-semibold" style="background: linear-gradient(135deg,#10B981,#06B6D4);">🤖 Auto-asignar mejor driver</button>
                </form>
                <form method="POST" action="<?= url('/delivery/' . $delivery['id'] . '/assign') ?>" style="margin:0;" class="flex gap-2">
                    <?= csrf_field() ?>
                    <select name="driver_id" required class="flex-1 px-2 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);">
                        <option value="">Elegir manualmente…</option>
                        <?php foreach ($drivers as $drv): ?>
                        <option value="<?= (int) $drv['id'] ?>"><?= e((string) $drv['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="px-3 py-2 rounded-xl font-semibold" style="background: var(--color-bg-subtle); color: var(--color-text-primary);">→</button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <div class="surface p-5">
            <h3 class="font-bold text-sm mb-3" style="color: var(--color-text-primary);">Linea de tiempo</h3>
            <ul class="space-y-2 text-xs">
                <?php foreach ([
                    'Asignado'   => $delivery['assigned_at'] ?? null,
                    'Aceptado'   => $delivery['accepted_at'] ?? null,
                    'Recogido'   => $delivery['picked_up_at'] ?? null,
                    'Llegando'   => $delivery['arriving_at'] ?? null,
                    'Entregado'  => $delivery['delivered_at'] ?? null,
                ] as $lbl=>$ts): ?>
                <li class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full" style="background: <?= $ts ? '#22C55E' : 'var(--color-border-subtle)' ?>;"></span>
                    <span style="color: var(--color-text-secondary);"><?= $lbl ?></span>
                    <span class="ml-auto" style="color: var(--color-text-tertiary);"><?= $ts ? e(date('H:i', strtotime((string) $ts))) : '—' ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<style>
    #map { background: #0F172A; }
    .leaflet-tile-pane { filter: brightness(.95) contrast(1.05); }
    .leaflet-control-attribution { background: rgba(15,23,42,.7) !important; color:#64748B !important; font-size: 9px !important; }
    .driver-icon-admin { background:#10B981; border:3px solid white; border-radius:50%; width:38px; height:38px; display:flex;align-items:center;justify-content:center; font-size:18px; box-shadow: 0 4px 16px rgba(16,185,129,.55), 0 0 0 8px rgba(16,185,129,.2); }
    .pin-icon-admin { font-size: 32px; line-height: 1; filter: drop-shadow(0 4px 8px rgba(0,0,0,.5)); }
    .route-line-admin { animation: dashFlowAdmin 30s linear infinite; }
    @keyframes dashFlowAdmin { to { stroke-dashoffset: -1000; } }
</style>
<script>
const map = L.map('map').setView([18.4861, -69.9312], 13);
L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { maxZoom: 19, subdomains: 'abcd', attribution: '© OpenStreetMap, © CartoDB' }).addTo(map);
setTimeout(() => map.invalidateSize(), 100);
window.addEventListener('resize', () => map.invalidateSize());

const mkIcon = (html, size, anchor) => L.divIcon({ html, className:'', iconSize:[size,size], iconAnchor: anchor || [size/2,size/2] });
const ICON_PICKUP  = mkIcon('<div class="pin-icon-admin">🍽️</div>', 32, [16, 32]);
const ICON_DROPOFF = mkIcon('<div class="pin-icon-admin">📍</div>', 32, [16, 32]);
const ICON_DRIVER  = mkIcon('<div class="driver-icon-admin">🛵</div>', 38);

let driverMarker = null, trailLayer = null, routeLine = null, pickupMarker = null, dropoffMarker = null;
let fitDone = false, lastRouteSig = '', lastRouteAt = 0;

async function refreshMap() {
    try {
        const res = await fetch('<?= url('/d/' . $delivery['tracking_token'] . '/feed') ?>', { headers: {'Accept':'application/json'}, cache: 'no-store' });
        const data = await res.json();
        if (!data.success) { document.getElementById('mapStatus').textContent = 'sin datos'; return; }
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
            const dp = data.driver_position;
            if (driverMarker) driverMarker.setLatLng([dp.lat, dp.lng]);
            else driverMarker = L.marker([dp.lat, dp.lng], { icon: ICON_DRIVER, zIndexOffset: 1000 }).addTo(map);
            points.push([dp.lat, dp.lng]);
            document.getElementById('mapStatus').textContent = 'driver: ' + new Date(dp.at).toLocaleTimeString();
        } else {
            document.getElementById('mapStatus').textContent = 'esperando GPS del driver…';
        }
        if (data.trail && data.trail.length > 1) {
            const ll = data.trail.map(p => [p.lat, p.lng]).reverse();
            if (trailLayer) trailLayer.setLatLngs(ll);
            else trailLayer = L.polyline(ll, { color: '#22D3EE', weight: 3, opacity: .35, dashArray: '4 8' }).addTo(map);
        }
        // OSRM route
        await maybeUpdateRoute(data);
        if (!fitDone && points.length > 1) {
            map.fitBounds(points, { padding: [40,40], maxZoom: 16 });
            fitDone = true;
        }
    } catch (e) { document.getElementById('mapStatus').textContent = 'error: ' + e.message; }
}

async function maybeUpdateRoute(data) {
    let from, to;
    if (data.driver_position && data.dropoff && ['picked_up','arriving'].includes(data.status)) {
        from = data.driver_position; to = data.dropoff;
    } else if (data.pickup && data.dropoff) {
        from = data.pickup; to = data.dropoff;
    } else return;

    const sig = `${from.lat.toFixed(4)},${from.lng.toFixed(4)}|${to.lat.toFixed(4)},${to.lng.toFixed(4)}`;
    if (sig === lastRouteSig) return;
    if (Date.now() - lastRouteAt < 20000) return;
    lastRouteAt = Date.now(); lastRouteSig = sig;
    try {
        const url = `https://router.project-osrm.org/route/v1/driving/${from.lng},${from.lat};${to.lng},${to.lat}?overview=full&geometries=geojson`;
        const r = await fetch(url);
        const j = await r.json();
        if (!j.routes || !j.routes[0]) return;
        const coords = j.routes[0].geometry.coordinates.map(c => [c[1], c[0]]);
        if (routeLine) routeLine.setLatLngs(coords);
        else routeLine = L.polyline(coords, { color: '#10B981', weight: 4, opacity: .85, dashArray: '12 6', className: 'route-line-admin' }).addTo(map);
    } catch (e) {}
}

refreshMap();
setInterval(refreshMap, 6000);
</script>

<?php \App\Core\View::stop(); ?>
