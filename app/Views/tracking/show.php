<?php
/** @var array $delivery */
/** @var array $statuses */
\App\Core\View::extend('layouts.driver');
\App\Core\View::start('content');

[$lbl, $color, $emoji] = $statuses[$delivery['status']] ?? [$delivery['status'], '#94A3B8', '•'];
$progressPct = match ($delivery['status']) {
    'pending' => 5,
    'assigned' => 20,
    'accepted' => 35,
    'picked_up' => 55,
    'arriving' => 80,
    'delivered' => 100,
    default => 0,
};
?>
<div class="min-h-screen flex flex-col">
    <!-- Header con info del local -->
    <header class="px-4 py-4 flex items-center gap-3 sticky top-0 z-30 backdrop-blur" style="background: rgba(6,11,22,.85); border-bottom: 1px solid var(--color-border-subtle);">
        <?php if (!empty($delivery['tenant_logo'])): ?>
        <img src="<?= e((string) $delivery['tenant_logo']) ?>" class="w-10 h-10 rounded-xl object-cover">
        <?php else: ?>
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl" style="background: var(--color-bg-subtle);">🍽</div>
        <?php endif; ?>
        <div class="flex-1 min-w-0">
            <div class="text-xs" style="color: var(--color-text-tertiary);">Tu pedido en</div>
            <div class="font-bold truncate"><?= e((string) ($delivery['tenant_name'] ?? 'Restaurante')) ?></div>
        </div>
        <a href="tel:<?= e((string) ($delivery['tenant_phone'] ?? '')) ?>" class="tap px-3 py-2 rounded-xl text-xs font-bold" style="background: var(--color-bg-subtle);">📞</a>
    </header>

    <!-- Hero estado -->
    <div class="px-4 pt-6">
        <div class="surface p-5 text-center">
            <div class="text-6xl mb-2"><?= $emoji ?></div>
            <div class="text-xs uppercase tracking-wider font-bold" style="color: <?= $color ?>;">Pedido <?= e((string) ($delivery['order_code'] ?? '')) ?></div>
            <div class="text-2xl font-bold mt-1"><?= e($lbl) ?></div>

            <!-- Progress bar -->
            <div class="mt-4 w-full h-2 rounded-full overflow-hidden" style="background: var(--color-bg-subtle);">
                <div id="progressBar" class="h-full transition-all duration-500" style="width: <?= $progressPct ?>%; background: linear-gradient(90deg,#10B981,#06B6D4,#A855F7,#EC4899);"></div>
            </div>
            <div class="grid grid-cols-5 gap-1 mt-2 text-[9px]" style="color: var(--color-text-tertiary);">
                <span>Confirmado</span><span>Asignado</span><span>En camino</span><span>Llegando</span><span>Entregado</span>
            </div>
        </div>
    </div>

    <!-- Driver -->
    <?php if (!empty($delivery['driver_name'])): ?>
    <div class="px-4 mt-4">
        <div class="surface p-4 flex items-center gap-3">
            <div class="w-12 h-12 rounded-full flex items-center justify-center font-bold text-lg" style="background: linear-gradient(135deg,#10B981,#06B6D4); color: white;">
                <?= e(strtoupper(mb_substr((string) $delivery['driver_name'], 0, 1))) ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-[10px] uppercase tracking-wider font-bold" style="color: var(--color-text-tertiary);">Tu repartidor</div>
                <div class="font-bold"><?= e((string) $delivery['driver_name']) ?></div>
                <div class="text-xs" style="color: var(--color-text-tertiary);">
                    <?= match($delivery['vehicle_type']) { 'bike'=>'🚲 Bicicleta','motorcycle'=>'🛵 Moto','car'=>'🚗 Auto','walk'=>'🚶 A pie',default=>'🛵' } ?>
                </div>
            </div>
            <?php if (!empty($delivery['driver_phone'])): ?>
            <a href="tel:<?= e((string) $delivery['driver_phone']) ?>" class="tap px-4 py-2 rounded-xl text-white font-bold text-sm" style="background: linear-gradient(135deg,#10B981,#06B6D4);">📞 Llamar</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Mapa -->
    <div class="px-4 mt-4 flex-1">
        <div class="surface p-2">
            <div id="map" style="height: 360px; border-radius: 12px; overflow: hidden;"></div>
            <div id="mapStatus" class="text-[10px] text-center py-1" style="color: var(--color-text-tertiary);">cargando mapa…</div>
        </div>
    </div>

    <!-- Total -->
    <div class="px-4 mt-4">
        <div class="surface p-4 flex items-center justify-between">
            <div>
                <div class="text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">Total del pedido</div>
                <div class="text-xl font-bold"><?= e((string) ($delivery['currency'] ?? 'USD')) ?> <?= number_format((float) ($delivery['order_total'] ?? 0), 2) ?></div>
            </div>
            <?php if ((float) $delivery['cash_to_collect'] > 0 && $delivery['status'] !== 'delivered'): ?>
            <div class="text-right">
                <div class="text-[10px] uppercase tracking-wider" style="color:#FCD34D;">Pago contraentrega</div>
                <div class="text-lg font-bold" style="color:#FCD34D;">💵 Ten listo $<?= number_format((float) $delivery['cash_to_collect'], 2) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Rating al cliente cuando entregado -->
    <?php if ($delivery['status'] === 'delivered' && empty($delivery['customer_rating'])): ?>
    <div class="px-4 mt-4 mb-6">
        <div class="surface p-4">
            <div class="font-bold mb-2 text-center">Como te fue? Califica al repartidor</div>
            <div class="flex justify-center gap-2 mb-3" id="ratingStars">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                <button onclick="setRating(<?= $i ?>)" data-star="<?= $i ?>" class="tap text-3xl opacity-40">⭐</button>
                <?php endfor; ?>
            </div>
            <textarea id="feedback" rows="2" placeholder="Cuentanos como te fue (opcional)..." class="w-full px-3 py-2 rounded-xl text-sm" style="background: var(--color-bg-subtle); color: var(--color-text-primary); border:1px solid var(--color-border-subtle);"></textarea>
            <button id="submitRating" onclick="submitRating()" disabled class="tap mt-3 w-full px-4 py-3 rounded-xl text-white font-bold opacity-50" style="background: linear-gradient(135deg,#10B981,#06B6D4);">Enviar calificacion</button>
        </div>
    </div>
    <?php endif; ?>

    <footer class="mt-6 mb-4 text-center text-[10px]" style="color: var(--color-text-tertiary);">
        Powered by <?= e((string) config('app.name', 'Pulse')) ?> · No compartas este link
    </footer>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin></script>
<script>
const TOKEN = <?= json_encode((string) $delivery['tracking_token']) ?>;
const map = L.map('map').setView([18.4861, -69.9312], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);

const dropoffIcon = L.divIcon({ html: '<div style="font-size:32px;line-height:1;">📍</div>', className: '', iconSize: [32,32], iconAnchor: [16,32] });
const pickupIcon = L.divIcon({ html: '<div style="font-size:32px;line-height:1;">🍽</div>', className: '', iconSize: [32,32], iconAnchor: [16,32] });
const driverIcon = L.divIcon({ html: '<div style="font-size:36px;line-height:1;filter:drop-shadow(0 0 8px rgba(16,185,129,.6));">🛵</div>', className: '', iconSize: [36,36], iconAnchor: [18,36] });

let driverMarker = null, trailLayer = null;
let mapInitialized = false;

async function refresh() {
    try {
        const res = await fetch('<?= url('/d/') ?>' + TOKEN + '/feed');
        const data = await res.json();
        if (!data.success) { document.getElementById('mapStatus').textContent = 'sin datos'; return; }

        const points = [];
        if (data.pickup) { L.marker([data.pickup.lat, data.pickup.lng], { icon: pickupIcon }).addTo(map); points.push([data.pickup.lat, data.pickup.lng]); }
        if (data.dropoff) { L.marker([data.dropoff.lat, data.dropoff.lng], { icon: dropoffIcon }).addTo(map); points.push([data.dropoff.lat, data.dropoff.lng]); }
        if (data.driver_position) {
            const dp = data.driver_position;
            if (driverMarker) driverMarker.setLatLng([dp.lat, dp.lng]);
            else driverMarker = L.marker([dp.lat, dp.lng], { icon: driverIcon }).addTo(map);
            points.push([dp.lat, dp.lng]);
            document.getElementById('mapStatus').textContent = 'driver actualizado ' + new Date(dp.at).toLocaleTimeString();
        } else {
            document.getElementById('mapStatus').textContent = 'esperando ubicacion del repartidor…';
        }
        if (data.trail && data.trail.length) {
            if (trailLayer) trailLayer.remove();
            trailLayer = L.polyline(data.trail.map(p => [p.lat, p.lng]).reverse(), { color: '#06B6D4', weight: 4, opacity: .6 }).addTo(map);
        }
        if (!mapInitialized && points.length) {
            map.fitBounds(points, { padding: [30,30], maxZoom: 16 });
            mapInitialized = true;
        }
        // Si cambio el estado, refresh duro para actualizar la UI
        const currentStatus = <?= json_encode((string) $delivery['status']) ?>;
        if (data.status !== currentStatus) location.reload();
    } catch (e) { document.getElementById('mapStatus').textContent = 'error: ' + e.message; }
}
refresh();
setInterval(refresh, 6000);

// Rating
let pickedRating = 0;
function setRating(r) {
    pickedRating = r;
    document.querySelectorAll('#ratingStars button').forEach(b => {
        b.style.opacity = parseInt(b.dataset.star,10) <= r ? '1' : '0.3';
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
