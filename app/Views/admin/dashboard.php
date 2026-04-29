<?php
\App\Core\View::extend('layouts.admin');
\App\Core\View::start('content');

$arr = (float) ($stats['mrr'] ?? 0) * 12;
$activeRate = $stats['tenants_total'] > 0 ? (int) round(($stats['tenants_active'] / $stats['tenants_total']) * 100) : 0;
?>

<!-- Header premium -->
<div class="mb-6 flex flex-wrap items-end justify-between gap-4 animate-fade-in">
    <div>
        <div class="flex items-center gap-2 mb-1.5">
            <span class="live-dot"></span>
            <span class="text-[11px] font-semibold uppercase tracking-[0.12em]" style="color: var(--color-text-tertiary);">Plataforma · Super Admin</span>
        </div>
        <h1 class="text-[28px] lg:text-[32px] font-bold tracking-tight" style="color: var(--color-text-primary); letter-spacing: -0.025em;">
            Panel <span class="gradient-text-aurora">Global</span>
        </h1>
        <p class="text-sm mt-1.5" style="color: var(--color-text-tertiary);">Estadisticas y monitoreo de toda la plataforma Kyros Pulse.</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= url('/admin/tenants') ?>" class="btn btn-secondary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            Empresas
        </a>
        <a href="<?= url('/admin/plans') ?>" class="btn btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 8h6m-5 0a3 3 0 110 6H9l3 3m-3-6h6m6 1a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Planes
        </a>
    </div>
</div>

<!-- KPI Hero -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4 stagger-in">
    <?php
    $kpis = [
        [
            'label'  => 'MRR estimado',
            'value'  => '$' . number_format($stats['mrr'], 0),
            'change' => '+' . $stats['new_signups_7d'] . ' nuevos · 7d',
            'tone'   => 'emerald', 'color' => '#10B981',
            'icon'   => 'M9 8h6m-5 0a3 3 0 110 6H9l3 3m-3-6h6m6 1a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        [
            'label'  => 'Empresas',
            'value'  => number_format($stats['tenants_total']),
            'change' => $stats['tenants_active'] . ' activas · ' . $activeRate . '%',
            'tone'   => 'violet', 'color' => '#7C3AED',
            'icon'   => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
        ],
        [
            'label'  => 'Mensajes 24h',
            'value'  => number_format($stats['messages_24h']),
            'change' => number_format($stats['messages_30d']) . ' en 30d',
            'tone'   => 'cyan', 'color' => '#06B6D4',
            'icon'   => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
        ],
        [
            'label'  => 'Usuarios totales',
            'value'  => number_format($stats['users_total']),
            'change' => number_format($stats['contacts_total']) . ' contactos',
            'tone'   => 'amber', 'color' => '#F59E0B',
            'icon'   => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
        ],
    ];
    foreach ($kpis as $k): ?>
    <div class="kpi-card" data-tone="<?= $k['tone'] ?>">
        <div class="flex items-start justify-between mb-3 relative">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: <?= $k['color'] ?>15; color: <?= $k['color'] ?>;">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $k['icon'] ?>"/></svg>
            </div>
        </div>
        <div class="text-[11px] uppercase font-semibold tracking-[0.1em] mb-1.5" style="color: var(--color-text-tertiary);"><?= e($k['label']) ?></div>
        <div class="text-[26px] font-bold tracking-tight tabular-nums" style="color: var(--color-text-primary); letter-spacing: -0.02em;"><?= e($k['value']) ?></div>
        <div class="text-[11px] mt-1.5" style="color: var(--color-text-tertiary);"><?= e($k['change']) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Status overview -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <?php
    $status = [
        ['Activas',     $stats['tenants_active'],    '#10B981', 'Pagando'],
        ['En trial',    $stats['tenants_trial'],     '#06B6D4', 'Periodo gratis'],
        ['Suspendidas', $stats['tenants_suspended'], '#F43F5E', 'Pago vencido'],
        ['Churn 30d',   $stats['churn_30d'],         '#94A3B8', 'Cancelaciones'],
    ];
    foreach ($status as $s): ?>
    <div class="surface p-4 hover-lift">
        <div class="flex items-center gap-2 mb-1.5">
            <span class="w-2 h-2 rounded-full" style="background: <?= $s[2] ?>; box-shadow: 0 0 8px <?= $s[2] ?>;"></span>
            <div class="text-[10px] uppercase font-semibold tracking-wider" style="color: var(--color-text-tertiary);"><?= e($s[0]) ?></div>
        </div>
        <div class="text-2xl font-bold tabular-nums" style="color: var(--color-text-primary);"><?= number_format((int) $s[1]) ?></div>
        <div class="text-[10px] mt-0.5" style="color: var(--color-text-muted);"><?= e($s[3]) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Chart + Plan distribution -->
<div class="grid lg:grid-cols-3 gap-4 mb-4">
    <div class="lg:col-span-2 surface p-6 relative overflow-hidden">
        <div class="absolute -top-20 -right-20 w-60 h-60 rounded-full opacity-30" style="background: radial-gradient(circle, rgba(124,58,237,0.4), transparent 60%); filter: blur(40px);"></div>
        <div class="flex items-center justify-between mb-5 relative">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <h3 class="font-bold text-[15px]" style="color: var(--color-text-primary);">Volumen global de mensajes</h3>
                    <span class="badge badge-primary">Live</span>
                </div>
                <p class="text-xs" style="color: var(--color-text-tertiary);">Ultimos 14 dias · todas las empresas</p>
            </div>
        </div>
        <div class="h-72 relative">
            <canvas id="adminChart"></canvas>
        </div>
    </div>

    <div class="surface p-6">
        <h3 class="font-bold text-[15px] mb-4" style="color: var(--color-text-primary);">Distribucion por plan</h3>
        <?php
        $totalAll = max(1, array_sum(array_column($byPlan, 'total')));
        $colors = ['#7C3AED', '#06B6D4', '#10B981', '#F59E0B', '#F43F5E'];
        ?>
        <div class="space-y-3">
            <?php foreach ($byPlan as $i => $p):
                $pct = ((int) $p['total']) / $totalAll * 100;
                $color = $colors[$i % count($colors)];
                $revenue = (float) $p['price_monthly'] * (int) $p['total'];
            ?>
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="w-2 h-2 rounded-full flex-shrink-0" style="background: <?= $color ?>;"></span>
                        <span class="text-sm font-semibold truncate" style="color: var(--color-text-primary);"><?= e($p['name']) ?></span>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <span class="font-mono text-sm font-bold" style="color: var(--color-text-primary);"><?= (int) $p['total'] ?></span>
                        <span class="text-[10px]" style="color: var(--color-text-tertiary);">$<?= number_format($revenue, 0) ?>/mo</span>
                    </div>
                </div>
                <div class="bar-track">
                    <div class="bar-fill" style="width: <?= $pct ?>%; background: linear-gradient(90deg, <?= $color ?>, <?= $color ?>aa);"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-5 pt-5 border-t" style="border-color: var(--color-border-subtle);">
            <div class="flex items-center justify-between text-sm">
                <span style="color: var(--color-text-tertiary);">ARR estimado</span>
                <span class="font-bold tabular-nums" style="color: var(--color-emerald);">$<?= number_format($arr, 0) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Recent tenants + Top tenants -->
<div class="grid lg:grid-cols-2 gap-4 mb-4">
    <div class="surface">
        <div class="p-5 border-b" style="border-color: var(--color-border-subtle);">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-[15px]" style="color: var(--color-text-primary);">Empresas recientes</h3>
                <a href="<?= url('/admin/tenants') ?>" class="btn btn-ghost btn-sm">Ver todas →</a>
            </div>
        </div>
        <div>
            <?php if (empty($recentTenants)): ?>
            <div class="p-12 text-center text-sm" style="color: var(--color-text-tertiary);">Sin empresas registradas.</div>
            <?php else: foreach ($recentTenants as $t):
                $sCls = match ($t['status']) { 'active' => 'badge-emerald', 'trial' => 'badge-cyan', 'suspended' => 'badge-rose', default => 'badge-slate' };
                $sLbl = match ($t['status']) { 'active' => 'Activa', 'trial' => 'Trial', 'suspended' => 'Suspendida', 'cancelled' => 'Cancelada', default => $t['status'] };
            ?>
            <div class="flex items-center justify-between gap-3 px-5 py-3 border-b last:border-b-0 hover:bg-[color:var(--color-bg-subtle)] transition" style="border-color: var(--color-border-subtle);">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <div class="avatar avatar-md flex-shrink-0"><?= e(strtoupper(mb_substr((string) $t['name'], 0, 2))) ?></div>
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-sm truncate" style="color: var(--color-text-primary);"><?= e($t['name']) ?></div>
                        <div class="text-xs truncate" style="color: var(--color-text-tertiary);">
                            <?= e((string) $t['email']) ?> · <?= e((string) ($t['plan_name'] ?? 'Sin plan')) ?>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <span class="text-[10px] font-mono" style="color: var(--color-text-muted);"><?= time_ago((string) $t['created_at']) ?></span>
                    <span class="badge <?= $sCls ?>"><?= e($sLbl) ?></span>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <div class="surface">
        <div class="p-5 border-b" style="border-color: var(--color-border-subtle);">
            <h3 class="font-bold text-[15px]" style="color: var(--color-text-primary);">Top tenants por uso</h3>
            <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">Mensajes en los ultimos 30 dias</p>
        </div>
        <?php if (empty($topTenants)):
            // skip
        else:
            $maxMsg = max(array_map(fn($t) => (int) $t['msg_count'], $topTenants)) ?: 1;
        ?>
        <div class="p-3 space-y-2">
            <?php foreach ($topTenants as $i => $t):
                $pct = (int) round(((int) $t['msg_count'] / $maxMsg) * 100);
                $rank = $i + 1;
            ?>
            <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-[color:var(--color-bg-subtle)] transition">
                <div class="text-xs font-mono w-5 text-center font-bold" style="color: var(--color-text-tertiary);">#<?= $rank ?></div>
                <div class="avatar avatar-sm flex-shrink-0"><?= e(strtoupper(mb_substr((string) $t['name'], 0, 2))) ?></div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-semibold truncate" style="color: var(--color-text-primary);"><?= e($t['name']) ?></span>
                        <span class="font-mono text-xs font-bold tabular-nums flex-shrink-0" style="color: var(--color-text-primary);"><?= number_format((int) $t['msg_count']) ?></span>
                    </div>
                    <div class="bar-track" style="height: 4px;">
                        <div class="bar-fill" style="width: <?= $pct ?>%;"></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Errors -->
<?php if (!empty($errors)): ?>
<div class="surface" style="border-color: rgba(244,63,94,0.2);">
    <div class="p-5 border-b flex items-center justify-between" style="border-color: var(--color-border-subtle);">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(244,63,94,0.12); color: #FB7185;">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <h3 class="font-bold text-[15px]" style="color: var(--color-text-primary);">Errores recientes en Wasapi</h3>
        </div>
        <a href="<?= url('/admin/logs') ?>" class="btn btn-ghost btn-sm">Ver logs →</a>
    </div>
    <div class="p-3 space-y-1">
        <?php foreach ($errors as $err): ?>
        <div class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg" style="background: rgba(244,63,94,0.04);">
            <div class="flex items-center gap-2 min-w-0 flex-1">
                <span class="w-1 h-1 rounded-full flex-shrink-0" style="background: #FB7185;"></span>
                <span class="truncate text-xs" style="color: var(--color-text-secondary);"><?= e((string) ($err['error_message'] ?? 'Error desconocido')) ?></span>
            </div>
            <span class="flex-shrink-0 text-[10px] font-mono" style="color: var(--color-text-muted);"><?= time_ago((string) $err['created_at']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('adminChart');
    if (!ctx) return;
    const isDark = document.documentElement.classList.contains('dark');
    const gridColor = isDark ? 'rgba(255,255,255,0.04)' : 'rgba(15,23,42,0.05)';
    const tickColor = isDark ? '#64748B' : '#94A3B8';

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($messagesChart['labels']) ?>,
            datasets: [{
                label: 'Mensajes',
                data: <?= json_encode($messagesChart['data']) ?>,
                borderColor: '#7C3AED',
                backgroundColor: ctx => {
                    const grad = ctx.chart.ctx.createLinearGradient(0, 0, 0, 280);
                    grad.addColorStop(0, 'rgba(124,58,237,0.4)');
                    grad.addColorStop(1, 'rgba(124,58,237,0)');
                    return grad;
                },
                fill: true,
                tension: 0.42,
                pointRadius: 0,
                pointHoverRadius: 6,
                borderWidth: 2.5,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: isDark ? '#0F1530' : '#fff',
                    titleColor: isDark ? '#F8FAFC' : '#0F172A',
                    bodyColor: isDark ? '#CBD5E1' : '#475569',
                    borderColor: isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.06)',
                    borderWidth: 1, padding: 12, cornerRadius: 10
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: tickColor, font: { size: 11 } }, border: { display: false } },
                y: { grid: { color: gridColor, drawBorder: false }, ticks: { color: tickColor, font: { size: 11 } }, beginAtZero: true, border: { display: false } }
            }
        }
    });
});
</script>

<?php \App\Core\View::stop(); ?>
