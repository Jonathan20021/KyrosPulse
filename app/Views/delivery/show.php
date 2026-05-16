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
                    <div class="text-[10px] uppercase tracking-wider font-semibold mb-1" style="color: var(--color-text-tertiary);">Direccion de entrega</div>
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

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin></script>
<script>
const map = L.map('map').setView([18.4861, -69.9312], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
let driverMarker = null;
let trailLayer = null;

async function refreshMap() {
    try {
        const res = await fetch('<?= url('/d/' . $delivery['tracking_token'] . '/feed') ?>', { headers: {'Accept':'application/json'} });
        const data = await res.json();
        if (!data.success) { document.getElementById('mapStatus').textContent = 'sin datos'; return; }
        const points = [];
        if (data.pickup) {
            const p = L.marker([data.pickup.lat, data.pickup.lng], { title: 'Local' }).addTo(map);
            points.push([data.pickup.lat, data.pickup.lng]);
        }
        if (data.dropoff) {
            const p = L.marker([data.dropoff.lat, data.dropoff.lng], { title: 'Cliente' }).addTo(map);
            points.push([data.dropoff.lat, data.dropoff.lng]);
        }
        if (data.driver_position) {
            const dp = data.driver_position;
            if (driverMarker) { driverMarker.setLatLng([dp.lat, dp.lng]); }
            else { driverMarker = L.marker([dp.lat, dp.lng], { title: 'Driver' }).addTo(map); }
            points.push([dp.lat, dp.lng]);
            document.getElementById('mapStatus').textContent = 'driver: ' + new Date(dp.at).toLocaleTimeString();
        } else {
            document.getElementById('mapStatus').textContent = 'esperando GPS del driver…';
        }
        if (data.trail && data.trail.length) {
            if (trailLayer) trailLayer.remove();
            trailLayer = L.polyline(data.trail.map(p => [p.lat, p.lng]).reverse(), { color: '#06B6D4', weight: 3, opacity: .7 }).addTo(map);
        }
        if (points.length) map.fitBounds(points, { padding: [30,30], maxZoom: 16 });
    } catch (e) { document.getElementById('mapStatus').textContent = 'error: ' + e.message; }
}
refreshMap();
setInterval(refreshMap, 8000);
</script>

<?php \App\Core\View::stop(); ?>
