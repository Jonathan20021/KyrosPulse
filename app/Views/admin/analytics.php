<?php
/**
 * @var array $data  snapshot devuelto por AdminAnalyticsService
 */
\App\Core\View::extend('layouts.admin');
\App\Core\View::start('content');

$k  = $data['kpis']          ?? [];
$pb = $data['plan_breakdown']?? [];
$gr = $data['growth']        ?? ['labels' => [], 'series' => []];
$top= $data['top_tenants']   ?? [];
$ar = $data['at_risk']       ?? [];

// sparkline helper (reusamos firma del dashboard ejecutivo, inline aqui)
function adm_sparkline(array $values, string $color = '#06B6D4', int $w = 280, int $h = 64): string {
    if (empty($values)) return '';
    $max = max($values); $min = min($values);
    $range = $max - $min;
    if ($range < 0.0001) $range = 1;
    $n = count($values);
    $stepX = $n > 1 ? $w / ($n - 1) : 0;
    $points = [];
    foreach ($values as $i => $v) {
        $x = round($i * $stepX, 1);
        $y = round($h - (($v - $min) / $range) * ($h - 10) - 5, 1);
        $points[] = "$x,$y";
    }
    $path = 'M ' . implode(' L ', $points);
    $last = end($values);
    $lastX = round(($n - 1) * $stepX, 1);
    $lastY = round($h - (($last - $min) / $range) * ($h - 10) - 5, 1);
    $area = $path . " L $lastX,$h L 0,$h Z";
    return '<svg viewBox="0 0 ' . $w . ' ' . $h . '" width="100%" height="' . $h . '" preserveAspectRatio="none">'
        . '<path d="' . $area . '" fill="' . $color . '" fill-opacity="0.15"/>'
        . '<path d="' . $path . '" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
        . '<circle cx="' . $lastX . '" cy="' . $lastY . '" r="3" fill="' . $color . '"/>'
        . '</svg>';
}
?>
<?php \App\Core\View::include('components.page_header', [
    'title'    => 'Analytics globales',
    'subtitle' => 'Vista cross-tenant del SaaS. MRR, crecimiento, top usuarios, tenants en riesgo. Cache 5 min.',
]); ?>

<div class="mb-4 flex items-center gap-2">
    <a href="<?= url('/admin/analytics?refresh=1') ?>" class="text-xs px-3 py-1.5 rounded-lg border text-white" style="border-color: rgba(255,255,255,.15);">↻ Refrescar ahora</a>
    <span class="text-xs text-slate-400">Generado <?= e(date('H:i', strtotime((string) ($data['generated_at'] ?? 'now')))) ?></span>
</div>

<!-- KPIs principales -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
    <?php foreach ([
        ['🏢', 'Tenants totales',  number_format((int) ($k['total_tenants'] ?? 0)),    '#06B6D4'],
        ['✅', 'Activos',           number_format((int) ($k['active_tenants'] ?? 0)),   '#10B981'],
        ['🆓', 'En trial',          number_format((int) ($k['trial_tenants'] ?? 0)),    '#F59E0B'],
        ['💰', 'MRR estimado',      '$' . number_format((float) ($k['mrr_usd'] ?? 0), 2), '#8B5CF6'],
    ] as [$em, $lbl, $val, $col]): ?>
    <div class="glass p-4">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] uppercase tracking-wider font-semibold text-slate-400"><?= e($lbl) ?></span>
            <span class="text-xl"><?= $em ?></span>
        </div>
        <div class="text-2xl font-bold text-white"><?= $val ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Secondary KPIs -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
    <?php foreach ([
        ['🆕', 'Nuevos este mes',  number_format((int) ($k['new_this_month'] ?? 0))],
        ['⛔', 'Suspendidos',       number_format((int) ($k['suspended_tenants'] ?? 0))],
        ['🛒', 'Ordenes 30d',       number_format((int) ($k['orders_30d'] ?? 0))],
        ['💎', 'Costo IA 30d',      '$' . number_format((float) ($k['ai_cost_30d'] ?? 0), 4)],
    ] as [$em, $lbl, $val]): ?>
    <div class="glass p-3.5">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] uppercase tracking-wider font-semibold text-slate-400"><?= e($lbl) ?></span>
            <span class="text-base"><?= $em ?></span>
        </div>
        <div class="text-lg font-bold text-white"><?= $val ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">
    <!-- Crecimiento 12m -->
    <div class="lg:col-span-2 glass p-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold text-white">📈 Tenants nuevos (12 meses)</h3>
            <span class="text-xs text-slate-400">total <?= number_format(array_sum($gr['series'] ?? [])) ?></span>
        </div>
        <?php if (!empty($gr['series'])): ?>
        <div class="mb-2"><?= adm_sparkline($gr['series'], '#10B981', 600, 100) ?></div>
        <div class="flex items-center justify-between text-[10px] text-slate-500">
            <?php foreach (array_slice($gr['labels'], 0, 12) as $lbl): ?>
            <span><?= e($lbl) ?></span>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-sm text-slate-400">Sin datos.</p>
        <?php endif; ?>
    </div>

    <!-- Plan breakdown -->
    <div class="glass p-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold text-white">📊 Por plan</h3>
            <a href="<?= url('/admin/plans') ?>" class="text-[11px] text-slate-400">Planes →</a>
        </div>
        <?php if (empty($pb)): ?>
        <p class="text-sm text-slate-400">Sin planes configurados.</p>
        <?php else:
            $maxN = max(array_map(fn($p) => (int) $p['tenants_count'], $pb)) ?: 1;
        ?>
        <ul class="space-y-2">
            <?php foreach ($pb as $p):
                $n = (int) $p['tenants_count'];
                $w = (int) round(($n / $maxN) * 100);
                $q = (int) $p['api_quota_monthly'];
            ?>
            <li>
                <div class="flex items-center justify-between text-sm mb-1">
                    <span class="font-semibold text-white"><?= e((string) $p['name']) ?></span>
                    <span class="text-slate-400 text-xs">$<?= number_format((float) $p['price_monthly'], 0) ?>/mo · <?= $n ?> tenants</span>
                </div>
                <div class="h-1.5 rounded-full overflow-hidden bg-white/5">
                    <div class="h-full rounded-full" style="width: <?= $w ?>%; background: linear-gradient(90deg,#8B5CF6,#06B6D4);"></div>
                </div>
                <div class="text-[10px] text-slate-500 mt-0.5">cuota API: <?= $q === -1 ? 'ilimitado' : number_format($q) ?>/mes</div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

<!-- Top tenants -->
<div class="glass overflow-hidden mb-5">
    <div class="px-4 py-3 border-b border-white/10 flex items-center justify-between">
        <h3 class="font-bold text-white">🔥 Top tenants por uso (30d)</h3>
        <span class="text-xs text-slate-400">ordenado por actividad ponderada</span>
    </div>
    <?php if (empty($top)): ?>
    <div class="p-6 text-center text-sm text-slate-400">Sin actividad reciente en ningun tenant.</div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-[10px] uppercase tracking-wider text-slate-400 bg-white/5">
                    <th class="text-left px-4 py-2">Tenant</th>
                    <th class="text-left px-4 py-2">Plan</th>
                    <th class="text-right px-4 py-2">API 30d</th>
                    <th class="text-right px-4 py-2">Agent runs</th>
                    <th class="text-right px-4 py-2">Costo IA</th>
                    <th class="text-right px-4 py-2">Ordenes</th>
                    <th class="text-right px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top as $t): ?>
                <tr class="border-t border-white/5">
                    <td class="px-4 py-2.5">
                        <div class="font-semibold text-white"><?= e((string) $t['name']) ?></div>
                        <div class="text-[10px] text-slate-500">#<?= (int) $t['id'] ?> · <?= e((string) $t['status']) ?></div>
                    </td>
                    <td class="px-4 py-2.5 text-xs text-slate-300"><?= e((string) ($t['plan_name'] ?? '—')) ?></td>
                    <td class="px-4 py-2.5 text-right font-mono text-slate-300"><?= number_format((int) $t['api_calls_30d']) ?></td>
                    <td class="px-4 py-2.5 text-right font-mono text-slate-300"><?= number_format((int) $t['agent_runs_30d']) ?></td>
                    <td class="px-4 py-2.5 text-right font-mono text-slate-300">$<?= number_format((float) $t['ai_cost_30d'], 4) ?></td>
                    <td class="px-4 py-2.5 text-right font-mono text-slate-300"><?= number_format((int) $t['orders_30d']) ?></td>
                    <td class="px-4 py-2.5 text-right">
                        <a href="<?= url('/admin/tenants/' . (int) $t['id'] . '/analytics') ?>" class="text-xs px-2.5 py-1 rounded font-semibold bg-white/10 text-white hover:bg-white/15">Detalle →</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Tenants at risk -->
<div class="glass overflow-hidden">
    <div class="px-4 py-3 border-b border-white/10 flex items-center justify-between">
        <h3 class="font-bold text-white">⚠ Tenants en riesgo de churn</h3>
        <span class="text-xs text-slate-400">No actividad 21d o drop &gt;70% en runs IA</span>
    </div>
    <?php if (empty($ar)): ?>
    <div class="p-6 text-center text-sm text-slate-400">🎉 Sin tenants en riesgo. Todos activos.</div>
    <?php else: ?>
    <ul class="divide-y divide-white/5">
        <?php foreach ($ar as $t):
            $lastMsg = (string) ($t['last_msg_at'] ?? '');
            $daysSince = $lastMsg !== '' && strtotime($lastMsg) > 0
                ? (int) floor((time() - strtotime($lastMsg)) / 86400)
                : 999;
            $runsDrop = ((int) $t['runs_prev_30d']) > 0
                ? (int) round((1 - ((int) $t['runs_30d'] / (int) $t['runs_prev_30d'])) * 100)
                : 0;
        ?>
        <li class="px-4 py-3 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl flex-shrink-0 flex items-center justify-center text-base" style="background: rgba(244,63,94,.12); color:#FCA5A5;">⚠</div>
            <div class="flex-1 min-w-0">
                <div class="font-semibold text-white text-sm"><?= e((string) $t['name']) ?></div>
                <div class="text-[11px] text-slate-400">
                    <?= e((string) ($t['plan_name'] ?? '—')) ?> · #<?= (int) $t['id'] ?>
                    · sin mensajes hace <strong style="color:#FCA5A5;"><?= $daysSince >= 999 ? 'siempre' : $daysSince . 'd' ?></strong>
                    <?php if ($runsDrop > 70 && ((int) $t['runs_prev_30d']) > 0): ?>
                    · drop runs IA <strong style="color:#FCA5A5;"><?= $runsDrop ?>%</strong>
                    <?php endif; ?>
                </div>
            </div>
            <a href="<?= url('/admin/tenants/' . (int) $t['id'] . '/analytics') ?>" class="text-xs px-3 py-1.5 rounded font-semibold bg-white/10 text-white hover:bg-white/15">Investigar →</a>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
<?php \App\Core\View::end(); ?>
