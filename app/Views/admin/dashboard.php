<?php
\App\Core\View::extend('layouts.admin');
\App\Core\View::start('content');
$_currentPageLabel = 'Dashboard';
?>

<div class="mb-6 flex items-center justify-between flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-white mb-1">Resumen del SaaS</h1>
        <p class="text-sm text-slate-400">Vision 360 grados de empresas, ingresos y actividad.</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <a href="<?= url('/admin/branding') ?>" class="px-3 py-2 rounded-lg text-sm text-slate-300 hover:text-white bg-white/5 hover:bg-white/10 transition">Branding</a>
        <a href="<?= url('/admin/changelog') ?>" class="px-3 py-2 rounded-lg text-sm text-slate-300 hover:text-white bg-white/5 hover:bg-white/10 transition">Changelog</a>
        <a href="<?= url('/admin/tenants') ?>" class="px-3 py-2 rounded-lg text-sm font-semibold text-white shadow-lg shadow-violet-500/30" style="background:linear-gradient(135deg,#7C3AED,#06B6D4);">Empresas</a>
    </div>
</div>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm whitespace-pre-line" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#34D399;"><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(244,63,94,.08); border-color: rgba(244,63,94,.3); color:#FB7185;"><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<!-- Demo seeder -->
<div class="mb-6 rounded-2xl p-4 border flex items-center gap-3 flex-wrap" style="background: linear-gradient(135deg, rgba(245,158,11,.08), rgba(124,58,237,.08)); border-color: rgba(245,158,11,.3);">
    <div class="text-3xl">🥩</div>
    <div class="flex-1 min-w-[260px]">
        <div class="font-bold text-white text-sm mb-0.5">Demo: BBQ MeatHouse</div>
        <p class="text-xs text-slate-400">Carga el menu completo (168 items, 22 categorias, 10 zonas de delivery) en el tenant <code class="font-mono text-amber-300">kyros-demo</code>. Idempotente: borra el menu previo.</p>
    </div>
    <form action="<?= url('/admin/seed/bbq-meathouse') ?>" method="POST" onsubmit="return confirm('Esto reemplaza el menu actual del tenant demo. Continuar?')">
        <?= csrf_field() ?>
        <button type="submit" class="px-4 py-2 rounded-xl text-white font-semibold shadow-lg" style="background: linear-gradient(135deg,#F59E0B,#7C3AED);">
            Cargar / Recargar demo
        </button>
    </form>
</div>

<!-- KPIs -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    <?php
    $kpis = [
        ['MRR mensual',     '$' . number_format((float) $stats['mrr'], 2), 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'from-emerald-500/20 to-emerald-500/0', '#10B981'],
        ['Empresas activas', $stats['tenants_active'],  'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5', 'from-violet-500/20 to-violet-500/0',  '#7C3AED'],
        ['En trial',         $stats['tenants_trial'],   'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',                                       'from-amber-500/20 to-amber-500/0',    '#F59E0B'],
        ['Suspendidas',      $stats['tenants_suspended'],'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z', 'from-rose-500/20 to-rose-500/0',     '#F43F5E'],
        ['Mensajes 24h',     number_format($stats['messages_24h']),  'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z', 'from-cyan-500/20 to-cyan-500/0', '#06B6D4'],
        ['Mensajes 30d',     number_format($stats['messages_30d']),  'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',  'from-cyan-500/20 to-cyan-500/0', '#06B6D4'],
        ['Altas 7d',         $stats['new_signups_7d'],  'M13 10V3L4 14h7v7l9-11h-7z', 'from-violet-500/20 to-violet-500/0', '#A78BFA'],
        ['Churn 30d',        $stats['churn_30d'],       'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6', 'from-rose-500/20 to-rose-500/0', '#F43F5E'],
    ];
    foreach ($kpis as $i => [$label, $value, $iconPath, $bg, $color]):
    ?>
    <div class="admin-card rounded-2xl p-4 relative overflow-hidden group" style="animation-delay: <?= $i * 0.05 ?>s;">
        <div class="absolute -top-10 -right-10 w-32 h-32 rounded-full opacity-30 group-hover:opacity-50 transition bg-gradient-to-br <?= $bg ?>"></div>
        <div class="relative">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold"><?= e($label) ?></span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" style="color: <?= $color ?>;"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $iconPath ?>"/></svg>
            </div>
            <div class="text-2xl font-bold" style="color: <?= $color ?>;"><?= e((string) $value) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Chart + by plan -->
<div class="grid lg:grid-cols-3 gap-3 mb-6">
    <div class="admin-card rounded-2xl p-5 lg:col-span-2">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold text-white">Volumen de mensajes (14 dias)</h3>
            <span class="text-xs text-slate-500">Todos los tenants</span>
        </div>
        <div style="height: 200px;"><canvas id="msgChart"></canvas></div>
    </div>
    <div class="admin-card rounded-2xl p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold text-white">Por plan</h3>
            <a href="<?= url('/admin/plans') ?>" class="text-xs text-cyan-400 hover:underline">Editar</a>
        </div>
        <div class="space-y-2.5">
            <?php
            $maxTotal = max(1, ...array_map(fn($p) => (int)$p['total'], $byPlan ?: [['total'=>1]]));
            foreach ($byPlan as $p):
                $total = (int) $p['total'];
                $pct = round($total / $maxTotal * 100);
            ?>
            <div>
                <div class="flex items-center justify-between text-xs mb-1">
                    <span class="font-semibold text-white"><?= e($p['name']) ?></span>
                    <span class="text-slate-400"><?= $total ?> · $<?= number_format((float) $p['price_monthly']) ?>/mes</span>
                </div>
                <div class="h-1.5 rounded-full bg-white/5 overflow-hidden">
                    <div class="h-full rounded-full" style="width: <?= $pct ?>%; background: linear-gradient(90deg,#7C3AED,#06B6D4);"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Tenants recientes + top consumo -->
<div class="grid lg:grid-cols-2 gap-3 mb-6">
    <div class="admin-card rounded-2xl p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold text-white">Empresas recientes</h3>
            <a href="<?= url('/admin/tenants') ?>" class="text-xs text-cyan-400 hover:underline">Ver todas →</a>
        </div>
        <div class="space-y-2">
            <?php foreach ($recentTenants as $t):
                $statusClr = match ($t['status'] ?? '') { 'active'=>'#10B981','trial'=>'#F59E0B','suspended'=>'#F43F5E', default=>'#64748B' };
            ?>
            <div class="flex items-center justify-between gap-2 p-3 rounded-lg bg-white/[0.03] hover:bg-white/[0.06] transition">
                <div class="min-w-0">
                    <div class="font-semibold text-white text-sm truncate"><?= e($t['name']) ?></div>
                    <div class="text-[11px] text-slate-500 truncate"><?= e($t['email']) ?></div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <span class="text-[10px] uppercase tracking-wider font-bold px-2 py-0.5 rounded" style="background: <?= $statusClr ?>22; color: <?= $statusClr ?>;"><?= e($t['status']) ?></span>
                    <span class="text-[10px] text-slate-500 font-mono"><?= e((string) ($t['plan_name'] ?? '—')) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="admin-card rounded-2xl p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold text-white">Top consumo (30d)</h3>
            <span class="text-xs text-slate-500">Por mensajes</span>
        </div>
        <div class="space-y-2">
            <?php
            $maxMsg = max(1, ...array_map(fn($t) => (int)$t['msg_count'], $topTenants ?: [['msg_count'=>1]]));
            foreach ($topTenants as $t):
                $count = (int) $t['msg_count'];
                $pct = $count > 0 ? max(2, round($count / $maxMsg * 100)) : 0;
            ?>
            <div class="p-2.5 rounded-lg bg-white/[0.03]">
                <div class="flex items-center justify-between text-xs mb-1.5">
                    <span class="font-semibold text-white truncate"><?= e($t['name']) ?></span>
                    <span class="text-slate-400 flex-shrink-0 font-mono"><?= number_format($count) ?></span>
                </div>
                <div class="h-1 rounded-full bg-white/5 overflow-hidden">
                    <div class="h-full rounded-full" style="width: <?= $pct ?>%; background: linear-gradient(90deg,#06B6D4,#7C3AED);"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Errores recientes -->
<?php if (!empty($errors)): ?>
<div class="admin-card rounded-2xl p-5 border-l-4 border-rose-500/60">
    <div class="flex items-center justify-between mb-3">
        <h3 class="font-bold text-white flex items-center gap-2">⚠ Errores Wasapi recientes</h3>
        <a href="<?= url('/admin/logs') ?>" class="text-xs text-cyan-400 hover:underline">Ver logs →</a>
    </div>
    <div class="space-y-2">
        <?php foreach (array_slice($errors, 0, 5) as $err): ?>
        <div class="p-3 rounded-lg bg-rose-500/5 border border-rose-500/15">
            <div class="flex items-center justify-between text-xs mb-1">
                <span class="font-mono text-rose-300"><?= e((string) ($err['endpoint'] ?? 'sin endpoint')) ?></span>
                <span class="text-slate-500"><?= time_ago((string) $err['created_at']) ?></span>
            </div>
            <div class="text-xs text-slate-400 truncate"><?= e((string) ($err['error_message'] ?? '—')) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    const ctx = document.getElementById('msgChart');
    if (!ctx || !window.Chart) return;
    const labels = <?= json_encode($messagesChart['labels']) ?>;
    const data   = <?= json_encode($messagesChart['data']) ?>;
    const grad = ctx.getContext('2d').createLinearGradient(0,0,0,200);
    grad.addColorStop(0, 'rgba(124,58,237,0.45)');
    grad.addColorStop(1, 'rgba(124,58,237,0.0)');
    new Chart(ctx, {
        type: 'line',
        data: { labels, datasets: [{
            label: 'Mensajes', data, borderColor: '#7C3AED', backgroundColor: grad,
            tension: 0.4, fill: true, pointRadius: 0, pointHoverRadius: 4, borderWidth: 2,
        }] },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#64748B', font: { size: 10 } } },
                y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748B', font: { size: 10 } }, beginAtZero: true }
            }
        }
    });
})();
</script>

<?php \App\Core\View::stop(); ?>
