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

<?php \App\Core\View::stop(); ?>
