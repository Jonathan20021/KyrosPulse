<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>

<div class="mb-7 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl lg:text-[28px] font-bold tracking-tight" style="color: var(--color-text-primary); letter-spacing: -0.02em;">Reportes</h1>
        <p class="text-sm mt-1" style="color: var(--color-text-tertiary);">Metricas de los ultimos <?= (int) $days ?> dias.</p>
    </div>
    <div class="flex items-center gap-2">
        <form method="GET">
            <select name="days" onchange="this.form.submit()" class="select text-sm">
                <?php foreach ([7, 14, 30, 60, 90, 180] as $d): ?>
                <option value="<?= $d ?>" <?= $d === $days ? 'selected' : '' ?>>Ultimos <?= $d ?> dias</option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="<?= url('/reports/export?days=' . $days) ?>" class="btn btn-secondary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Exportar CSV
        </a>
    </div>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
    <div class="stat-card">
        <div class="text-[11px] uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Tickets totales</div>
        <div class="text-2xl font-bold tracking-tight" style="color: var(--color-text-primary);"><?= (int) ($ticketStats['total'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="text-[11px] uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Resueltos</div>
        <div class="text-2xl font-bold tracking-tight text-emerald-400"><?= (int) ($ticketStats['resolved'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="text-[11px] uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">SLA incumplido</div>
        <div class="text-2xl font-bold tracking-tight text-rose-400"><?= (int) ($ticketStats['sla_breached'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="text-[11px] uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Satisfaccion</div>
        <div class="text-2xl font-bold tracking-tight" style="color: var(--color-text-primary);"><?= number_format((float) ($ticketStats['avg_satisfaction'] ?? 0), 1) ?> <span class="text-sm" style="color: var(--color-text-muted);">/5</span></div>
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-4 mb-4">
    <div class="surface p-5">
        <h3 class="font-bold mb-4" style="color: var(--color-text-primary);">Volumen de mensajes</h3>
        <div class="h-72"><canvas id="chartMessages"></canvas></div>
    </div>
    <div class="surface p-5">
        <h3 class="font-bold mb-4" style="color: var(--color-text-primary);">Pipeline por etapa</h3>
        <div class="h-72"><canvas id="chartStages"></canvas></div>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-4 mb-4">
    <div class="surface p-5">
        <h3 class="font-bold mb-3" style="color: var(--color-text-primary);">Sentimiento</h3>
        <div class="h-56"><canvas id="chartSentiment"></canvas></div>
    </div>
    <div class="surface p-5">
        <h3 class="font-bold mb-3" style="color: var(--color-text-primary);">Por canal</h3>
        <div class="h-56"><canvas id="chartChannels"></canvas></div>
    </div>
    <div class="surface p-5">
        <h3 class="font-bold mb-3" style="color: var(--color-text-primary);">Por hora del dia</h3>
        <div class="h-56"><canvas id="chartHours"></canvas></div>
    </div>
</div>

<div class="surface">
    <div class="p-5 border-b" style="border-color: var(--color-border-subtle);">
        <h3 class="font-bold" style="color: var(--color-text-primary);">Rendimiento por agente</h3>
    </div>
    <?php if (empty($agents)): ?>
    <div class="p-12 text-center text-sm" style="color: var(--color-text-tertiary);">Sin datos en el periodo seleccionado.</div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="kt-table">
            <thead>
                <tr><th>Agente</th><th class="text-right">Conversaciones</th><th class="text-right">Mensajes</th><th class="text-right">Resp. promedio</th></tr>
            </thead>
            <tbody>
                <?php foreach ($agents as $a): ?>
                <tr>
                    <td>
                        <div class="flex items-center gap-2.5">
                            <div class="avatar avatar-md"><?= e(strtoupper(mb_substr((string) $a['first_name'], 0, 1) . mb_substr((string) $a['last_name'], 0, 1))) ?></div>
                            <span class="font-semibold" style="color: var(--color-text-primary);"><?= e($a['first_name'] . ' ' . $a['last_name']) ?></span>
                        </div>
                    </td>
                    <td class="text-right font-mono"><?= (int) $a['conversations'] ?></td>
                    <td class="text-right font-mono"><?= (int) $a['messages'] ?></td>
                    <td class="text-right text-xs"><?= !empty($a['avg_response_min']) ? round((float) $a['avg_response_min']) . ' min' : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
const isDark = document.documentElement.classList.contains('dark');
const grid = isDark ? 'rgba(255,255,255,0.04)' : 'rgba(15,23,42,0.05)';
const tick = isDark ? '#64748B' : '#94A3B8';
const tooltipBg = isDark ? '#0F1530' : '#fff';
const tooltipText = isDark ? '#F8FAFC' : '#0F172A';

const baseChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: true, labels: { color: tick, font: { size: 11 } } },
        tooltip: {
            backgroundColor: tooltipBg,
            titleColor: tooltipText,
            bodyColor: tooltipText,
            borderColor: isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)',
            borderWidth: 1, padding: 12, cornerRadius: 8, usePointStyle: true,
        }
    },
    scales: {
        x: { grid: { display: false }, ticks: { color: tick, font: { size: 11 } }, border: { display: false } },
        y: { grid: { color: grid, drawBorder: false }, ticks: { color: tick, font: { size: 11 } }, beginAtZero: true, border: { display: false } }
    }
};

new Chart(document.getElementById('chartMessages'), {
    type: 'line',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [
            { label: 'Recibidos', data: <?= json_encode($inbound) ?>, borderColor: '#7C3AED', backgroundColor: 'rgba(124,58,237,.15)', fill: true, tension: 0.4, borderWidth: 2.5, pointRadius: 0 },
            { label: 'Enviados',  data: <?= json_encode($outbound) ?>, borderColor: '#06B6D4', backgroundColor: 'rgba(6,182,212,.15)', fill: true, tension: 0.4, borderWidth: 2.5, pointRadius: 0 }
        ]
    },
    options: baseChartOptions
});

new Chart(document.getElementById('chartStages'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($byStage, 'name')) ?>,
        datasets: [{
            label: 'Leads',
            data: <?= json_encode(array_map('intval', array_column($byStage, 'total'))) ?>,
            backgroundColor: <?= json_encode(array_column($byStage, 'color')) ?>,
            borderRadius: 6,
        }]
    },
    options: { ...baseChartOptions, plugins: { ...baseChartOptions.plugins, legend: { display: false } } }
});

new Chart(document.getElementById('chartSentiment'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($sentiment, 'ai_sentiment')) ?>,
        datasets: [{ data: <?= json_encode(array_map('intval', array_column($sentiment, 'total'))) ?>, backgroundColor: ['#10B981', '#94A3B8', '#F43F5E'], borderWidth: 0 }]
    },
    options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { position: 'bottom', labels: { color: tick, font: { size: 11 } } } } }
});

new Chart(document.getElementById('chartChannels'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($channels, 'channel')) ?>,
        datasets: [{ data: <?= json_encode(array_map('intval', array_column($channels, 'total'))) ?>, backgroundColor: ['#10B981','#7C3AED','#06B6D4','#F59E0B','#F43F5E','#3B82F6','#A855F7'], borderWidth: 0 }]
    },
    options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { position: 'bottom', labels: { color: tick, font: { size: 11 } } } } }
});

new Chart(document.getElementById('chartHours'), {
    type: 'bar',
    data: {
        labels: Array.from({length: 24}, (_, i) => i + 'h'),
        datasets: [{ label: 'Mensajes', data: <?= json_encode($hours) ?>, backgroundColor: '#7C3AED', borderRadius: 4 }]
    },
    options: { ...baseChartOptions, plugins: { ...baseChartOptions.plugins, legend: { display: false } } }
});
</script>

<!-- ===========================================================
     SECCION RESTAURANTE — solo si tenant.is_restaurant
     =========================================================== -->
<?php if (!empty($restaurant)):
    $rs = $restaurant;
    $cur = $rs['currency'];
?>
<div class="my-8 pt-6 border-t" style="border-color: var(--color-border-subtle);">
    <div class="flex items-center gap-2 mb-4">
        <span class="text-2xl">🥩</span>
        <div>
            <div class="text-[10px] uppercase tracking-[0.15em] font-bold" style="color: #F59E0B;">Restaurante</div>
            <h2 class="text-lg font-bold" style="color: var(--color-text-primary);">Analytics de ordenes · <?= (int) $days ?> dias</h2>
        </div>
    </div>

    <!-- Big numbers -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 mb-5">
        <?php
        $kpisR = [
            ['Ordenes',         number_format((int) ($rs['summary']['total_orders'] ?? 0)),                                       '#7C3AED'],
            ['Revenue',         $cur . ' ' . number_format((float) ($rs['summary']['total_revenue'] ?? 0), 2),                    '#10B981'],
            ['Ticket promedio', $cur . ' ' . number_format((float) ($rs['summary']['avg_ticket'] ?? 0), 2),                       '#06B6D4'],
            ['Cerradas IA',     number_format((int) ($rs['summary']['ai_orders'] ?? 0)),                                          '#A855F7'],
            ['Completadas',     number_format((int) ($rs['summary']['completed'] ?? 0)),                                          '#22C55E'],
            ['Tasa conversion', ((float) ($rs['conv_rate'] ?? 0)) . '%',                                                          '#F59E0B'],
        ];
        foreach ($kpisR as [$lbl, $val, $col]): ?>
        <div class="surface p-3">
            <div class="text-[10px] uppercase tracking-wider mb-0.5" style="color: var(--color-text-tertiary);"><?= e($lbl) ?></div>
            <div class="text-lg font-bold" style="color: <?= $col ?>;"><?= e((string) $val) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="grid lg:grid-cols-3 gap-3">
        <!-- Revenue trend -->
        <div class="surface p-5 lg:col-span-2">
            <h3 class="font-bold text-sm mb-3" style="color: var(--color-text-primary);">Revenue por dia</h3>
            <div class="relative h-64">
                <canvas id="revChart"></canvas>
            </div>
        </div>

        <!-- Orders by type -->
        <div class="surface p-5">
            <h3 class="font-bold text-sm mb-3" style="color: var(--color-text-primary);">Por tipo de entrega</h3>
            <?php if (empty($rs['by_type'])): ?>
            <p class="text-xs" style="color: var(--color-text-tertiary);">Sin datos.</p>
            <?php else:
                $totalByType = array_sum(array_column($rs['by_type'], 'total')) ?: 1;
                foreach ($rs['by_type'] as $t):
                    $pct = round(((int) $t['total'] / $totalByType) * 100);
                    $emoji = match ($t['delivery_type']) { 'pickup' => '🛍', 'dine_in' => '🍴', default => '🛵' };
                    $lbl = match ($t['delivery_type']) { 'pickup' => 'Pickup', 'dine_in' => 'Mesa local', default => 'Delivery' };
                    $color = match ($t['delivery_type']) { 'pickup' => '#06B6D4', 'dine_in' => '#A855F7', default => '#10B981' };
            ?>
            <div class="mb-3">
                <div class="flex items-center justify-between text-xs mb-1">
                    <span class="font-semibold" style="color: var(--color-text-primary);"><?= $emoji ?> <?= e($lbl) ?></span>
                    <span style="color: var(--color-text-tertiary);"><?= (int) $t['total'] ?> · <?= $pct ?>%</span>
                </div>
                <div class="h-1.5 rounded-full overflow-hidden" style="background: var(--color-bg-subtle);">
                    <div class="h-full rounded-full" style="width: <?= $pct ?>%; background: <?= $color ?>;"></div>
                </div>
                <div class="text-[10px] mt-0.5" style="color: var(--color-text-tertiary);"><?= $cur ?> <?= number_format((float) $t['revenue'], 2) ?> · prom <?= $cur ?> <?= number_format((float) $t['avg'], 2) ?></div>
            </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Top items -->
        <div class="surface p-5 lg:col-span-2">
            <h3 class="font-bold text-sm mb-3" style="color: var(--color-text-primary);">Top platos</h3>
            <?php if (empty($rs['top_items'])): ?>
            <p class="text-xs" style="color: var(--color-text-tertiary);">Sin datos.</p>
            <?php else:
                $maxU = max(array_column($rs['top_items'], 'units')) ?: 1;
            ?>
            <div class="grid sm:grid-cols-2 gap-x-5 gap-y-2">
                <?php foreach ($rs['top_items'] as $i => $it):
                    $w = round(((int) $it['units'] / $maxU) * 100);
                ?>
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="truncate font-semibold flex-1" style="color: var(--color-text-primary);"><?= ($i+1) ?>. <?= e($it['name']) ?></span>
                        <span class="font-mono ml-2" style="color: var(--color-primary);"><?= (int) $it['units'] ?>×</span>
                    </div>
                    <div class="h-1 rounded-full overflow-hidden" style="background: var(--color-bg-subtle);">
                        <div class="h-full" style="width: <?= $w ?>%; background: linear-gradient(90deg,#F59E0B,#7C3AED);"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Status distribution -->
        <div class="surface p-5">
            <h3 class="font-bold text-sm mb-3" style="color: var(--color-text-primary);">Distribucion por estado</h3>
            <?php
            $statusColors = [
                'new' => '#06B6D4', 'confirmed' => '#3B82F6', 'preparing' => '#F59E0B',
                'ready' => '#A855F7', 'out_for_delivery' => '#EC4899', 'delivered' => '#22C55E',
                'cancelled' => '#EF4444',
            ];
            $statusLabels = [
                'new' => 'Nuevo', 'confirmed' => 'Confirmado', 'preparing' => 'En cocina',
                'ready' => 'Listo', 'out_for_delivery' => 'En camino', 'delivered' => 'Entregado',
                'cancelled' => 'Cancelado',
            ];
            $totalSt = array_sum(array_column($rs['status_dist'], 'total')) ?: 1;
            foreach ($rs['status_dist'] as $s):
                $pct = round(((int) $s['total'] / $totalSt) * 100);
                $col = $statusColors[$s['status']] ?? '#94A3B8';
                $lbl = $statusLabels[$s['status']] ?? $s['status'];
            ?>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-2 h-2 rounded-full flex-shrink-0" style="background: <?= $col ?>;"></span>
                <span class="text-xs flex-1 truncate" style="color: var(--color-text-secondary);"><?= e($lbl) ?></span>
                <span class="text-xs font-bold" style="color: var(--color-text-primary);"><?= (int) $s['total'] ?></span>
                <span class="text-[10px] font-mono w-10 text-right" style="color: var(--color-text-tertiary);"><?= $pct ?>%</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
new Chart(document.getElementById('revChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($rs['rev_labels']) ?>,
        datasets: [
            { label: 'Revenue (<?= e($cur) ?>)', data: <?= json_encode($rs['rev_totals']) ?>, borderColor: '#10B981', backgroundColor: 'rgba(16,185,129,.12)', tension: .35, fill: true, yAxisID: 'y' },
            { label: 'Ordenes',                  data: <?= json_encode($rs['rev_orders']) ?>, borderColor: '#7C3AED', backgroundColor: 'rgba(124,58,237,.06)', tension: .35, fill: false, yAxisID: 'y1' },
            { label: 'Ordenes IA',               data: <?= json_encode($rs['rev_ai']) ?>,     borderColor: '#F59E0B', backgroundColor: 'rgba(245,158,11,.06)', tension: .35, borderDash: [4,4], fill: false, yAxisID: 'y1' },
        ],
    },
    options: {
        ...baseChartOptions,
        scales: {
            ...baseChartOptions.scales,
            y:  { type: 'linear', position: 'left',  ticks: { color: 'rgba(148,163,184,.65)' }, grid: { color: 'rgba(148,163,184,.08)' } },
            y1: { type: 'linear', position: 'right', ticks: { color: 'rgba(148,163,184,.65)' }, grid: { drawOnChartArea: false } },
        },
    },
});
</script>
<?php endif; ?>

<?php \App\Core\View::stop(); ?>
