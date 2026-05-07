<?php
/** @var array $summary */
/** @var array $kpis */
/** @var array $daily */
/** @var array $byFeature */
/** @var array $byModel */
/** @var array $roi */
/** @var array $recent */
/** @var string $periodStart */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

$usdBudget    = $summary['usd_budget'];
$usdUsed      = (float) $summary['usd_used'];
$usdRemaining = $summary['usd_remaining'];
$tokenQuota   = $summary['token_quota'];
$tokensUsed   = (int) $summary['tokens_used'];
$threshold    = (int) ($summary['threshold_pct'] ?? 80);
$pct          = (int) ($summary['pct'] ?? 0);
$alertedAt    = $summary['alerted_at'];

$pctColor = $pct >= 90 ? '#DC2A47' : ($pct >= ($threshold) ? '#F59E0B' : '#0EA572');

$totalCalls    = (int) ($kpis['calls'] ?? 0);
$totalTokens   = (int) ($kpis['tokens'] ?? 0);
$totalCost     = (float) ($kpis['cost_usd'] ?? 0);
$avgLatency    = (int) round((float) ($kpis['avg_latency_ms'] ?? 0));
$successes     = (int) ($kpis['successes'] ?? 0);
$failures      = (int) ($kpis['failures'] ?? 0);
$successRate   = $totalCalls > 0 ? round($successes / $totalCalls * 100, 1) : 0;

$ordersGenerated = (int) ($roi['orders_generated'] ?? 0);
$aiCostAttr      = (float) ($roi['ai_cost'] ?? 0);
$ordersTotal     = (float) ($roi['orders_total'] ?? 0);
$roiMultiple     = $aiCostAttr > 0 ? round($ordersTotal / $aiCostAttr, 1) : null;

$dailyJson = json_encode(array_map(fn($d) => [
    'day'      => (string) $d['day'],
    'cost_usd' => (float) $d['cost_usd'],
    'calls'    => (int)   $d['calls'],
    'tokens'   => (int)   $d['tokens'],
], $daily), JSON_UNESCAPED_UNICODE);
?>
<?php \App\Core\View::include('components.page_header', [
    'title'    => 'Uso de IA · Token Economy',
    'subtitle' => 'Rastreo de tokens, costo en USD, ROI por orden generada y control de presupuesto mensual.',
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#0B7C56;"><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(244,63,94,.08); border-color: rgba(244,63,94,.3); color:#BE123C;"><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<?php if ($usdBudget !== null && $alertedAt): ?>
<div class="mb-4 p-3 rounded-xl border text-sm flex items-center gap-3" style="background: rgba(245,158,11,.08); border-color: rgba(245,158,11,.3); color:#92400E;">
    <span class="text-xl">⚠️</span>
    <div>
        <strong>Alerta de presupuesto disparada</strong> · cruzaste el <?= $threshold ?>% el <?= e(date('d M Y H:i', strtotime((string) $alertedAt))) ?>.
        Se notifico a los destinos suscritos al evento <code>ai.budget_alert</code>.
    </div>
</div>
<?php endif; ?>

<!-- Top: Budget meter + KPIs -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">
    <!-- Budget meter (occupies 1/3) -->
    <div class="surface p-5">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] uppercase tracking-wider font-semibold" style="color: var(--color-text-tertiary);">Presupuesto del periodo</span>
            <span class="text-xs" style="color: var(--color-text-tertiary);">desde <?= e(date('d M', strtotime($periodStart))) ?></span>
        </div>
        <?php if ($usdBudget !== null && (float) $usdBudget > 0): ?>
            <div class="flex items-baseline gap-2 mb-2">
                <span class="text-3xl font-bold" style="color: var(--color-text-primary);">$<?= number_format($usdUsed, 2) ?></span>
                <span class="text-sm" style="color: var(--color-text-tertiary);">/ $<?= number_format((float) $usdBudget, 2) ?></span>
            </div>
            <div class="h-2 rounded-full overflow-hidden mb-2" style="background: rgba(0,0,0,0.06);">
                <div class="h-full transition-all" style="width: <?= $pct ?>%; background: <?= $pctColor ?>;"></div>
            </div>
            <div class="flex items-center justify-between text-xs">
                <span style="color: <?= $pctColor ?>; font-weight: 600;"><?= $pct ?>% usado</span>
                <span style="color: var(--color-text-tertiary);">restante: $<?= number_format((float) $usdRemaining, 2) ?></span>
            </div>
        <?php else: ?>
            <div class="text-3xl font-bold mb-1" style="color: var(--color-text-primary);">$<?= number_format($usdUsed, 2) ?></div>
            <div class="text-xs mb-3" style="color: var(--color-text-tertiary);">Sin tope mensual configurado · uso registrado pero sin pausa automatica</div>
        <?php endif; ?>

        <?php if ($tokenQuota !== null && $tokenQuota > 0): ?>
        <div class="mt-4 pt-3 border-t" style="border-color: var(--color-border-subtle);">
            <div class="text-[11px] uppercase tracking-wider font-semibold mb-1" style="color: var(--color-text-tertiary);">Tope legacy en tokens</div>
            <div class="text-sm" style="color: var(--color-text-primary);"><?= number_format($tokensUsed) ?> / <?= number_format((int) $tokenQuota) ?> tokens</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- KPIs (occupy 2/3 -> 4 boxes 2x2) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:col-span-2">
        <div class="surface p-4">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] uppercase tracking-wider font-semibold" style="color: var(--color-text-tertiary);">Llamadas IA</span>
                <span class="text-xl">⚡</span>
            </div>
            <div class="text-2xl font-bold" style="color: var(--color-text-primary);"><?= number_format($totalCalls) ?></div>
            <div class="text-xs mt-1" style="color: var(--color-text-tertiary);"><?= $successRate ?>% exito</div>
        </div>
        <div class="surface p-4">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] uppercase tracking-wider font-semibold" style="color: var(--color-text-tertiary);">Tokens del periodo</span>
                <span class="text-xl">🔢</span>
            </div>
            <div class="text-2xl font-bold" style="color: var(--color-text-primary);"><?= number_format($totalTokens) ?></div>
            <div class="text-xs mt-1" style="color: var(--color-text-tertiary);">input + output</div>
        </div>
        <div class="surface p-4">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] uppercase tracking-wider font-semibold" style="color: var(--color-text-tertiary);">Costo del periodo</span>
                <span class="text-xl">💸</span>
            </div>
            <div class="text-2xl font-bold" style="color: var(--color-text-primary);">$<?= number_format($totalCost, 2) ?></div>
            <div class="text-xs mt-1" style="color: var(--color-text-tertiary);">$<?= $totalCalls > 0 ? number_format($totalCost / $totalCalls, 4) : '0.00' ?> / call</div>
        </div>
        <div class="surface p-4">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] uppercase tracking-wider font-semibold" style="color: var(--color-text-tertiary);">Latencia promedio</span>
                <span class="text-xl">⏱</span>
            </div>
            <div class="text-2xl font-bold" style="color: var(--color-text-primary);"><?= number_format($avgLatency) ?><span class="text-sm font-normal" style="color: var(--color-text-tertiary);">ms</span></div>
            <div class="text-xs mt-1" style="color: <?= $failures > 0 ? '#DC2A47' : 'var(--color-text-tertiary)' ?>;"><?= $failures ?> error(es)</div>
        </div>
    </div>
</div>

<!-- ROI box -->
<?php if ($ordersGenerated > 0): ?>
<div class="mb-5 p-5 rounded-2xl text-white" style="background: linear-gradient(135deg, #0EA572, #0B7C56);">
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <div class="text-[11px] uppercase tracking-wider font-semibold opacity-80 mb-1">Retorno sobre inversion IA · este periodo</div>
            <div class="text-3xl font-bold mb-1">
                <?= $ordersGenerated ?> orden<?= $ordersGenerated === 1 ? '' : 'es' ?> generada<?= $ordersGenerated === 1 ? '' : 's' ?>
            </div>
            <div class="text-sm opacity-90">
                $<?= number_format($aiCostAttr, 4) ?> en IA → $<?= number_format($ordersTotal, 2) ?> en pedidos
            </div>
        </div>
        <?php if ($roiMultiple !== null): ?>
        <div class="text-right">
            <div class="text-[11px] uppercase tracking-wider font-semibold opacity-80">ROI</div>
            <div class="text-5xl font-bold"><?= $roiMultiple ?>×</div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Trend chart -->
<div class="surface p-5 mb-5">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-base font-semibold" style="color: var(--color-text-primary);">Costo diario · ultimos 30 dias</h3>
            <p class="text-xs" style="color: var(--color-text-tertiary);">Cada barra = un dia. Pasa el cursor para ver detalle.</p>
        </div>
    </div>
    <div id="ai-cost-chart" class="w-full" style="height: 180px; position: relative;">
        <!-- SVG bars renderizado por JS -->
    </div>
</div>

<!-- Tabs: por feature, por modelo, recientes -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5">
    <!-- Top features -->
    <div class="surface p-5">
        <h3 class="text-base font-semibold mb-3" style="color: var(--color-text-primary);">Costo por feature</h3>
        <?php if (empty($byFeature)): ?>
            <p class="text-sm" style="color: var(--color-text-tertiary);">Sin datos en este periodo todavia.</p>
        <?php else: ?>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left" style="color: var(--color-text-tertiary); font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em;">
                    <th class="pb-2">Feature</th>
                    <th class="pb-2 text-right">Calls</th>
                    <th class="pb-2 text-right">Tokens</th>
                    <th class="pb-2 text-right">Costo</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($byFeature as $f): ?>
                <tr style="border-top: 1px solid var(--color-border-subtle);">
                    <td class="py-2 font-mono text-xs" style="color: var(--color-text-primary);"><?= e((string) $f['feature_base']) ?></td>
                    <td class="py-2 text-right" style="color: var(--color-text-secondary);"><?= number_format((int) $f['calls']) ?></td>
                    <td class="py-2 text-right" style="color: var(--color-text-secondary);"><?= number_format((int) $f['tokens']) ?></td>
                    <td class="py-2 text-right font-mono font-semibold" style="color: var(--color-text-primary);">$<?= number_format((float) $f['cost_usd'], 4) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Top models -->
    <div class="surface p-5">
        <h3 class="text-base font-semibold mb-3" style="color: var(--color-text-primary);">Costo por modelo</h3>
        <?php if (empty($byModel)): ?>
            <p class="text-sm" style="color: var(--color-text-tertiary);">Sin datos en este periodo todavia.</p>
        <?php else: ?>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left" style="color: var(--color-text-tertiary); font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em;">
                    <th class="pb-2">Modelo</th>
                    <th class="pb-2 text-right">Calls</th>
                    <th class="pb-2 text-right">In/Out</th>
                    <th class="pb-2 text-right">Costo</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($byModel as $m): ?>
                <tr style="border-top: 1px solid var(--color-border-subtle);">
                    <td class="py-2 font-mono text-xs" style="color: var(--color-text-primary);"><?= e((string) $m['model']) ?></td>
                    <td class="py-2 text-right" style="color: var(--color-text-secondary);"><?= number_format((int) $m['calls']) ?></td>
                    <td class="py-2 text-right text-xs" style="color: var(--color-text-secondary);"><?= number_format((int) $m['tokens_in']) ?>/<?= number_format((int) $m['tokens_out']) ?></td>
                    <td class="py-2 text-right font-mono font-semibold" style="color: var(--color-text-primary);">$<?= number_format((float) $m['cost_usd'], 4) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Budget config -->
<div class="surface p-5 mb-5">
    <div class="flex items-start justify-between mb-3 flex-wrap gap-3">
        <div>
            <h3 class="text-base font-semibold" style="color: var(--color-text-primary);">Configuracion de presupuesto</h3>
            <p class="text-xs mt-1" style="color: var(--color-text-tertiary);">Limita el gasto mensual de IA. Si llegas al 100% el agente IA se pausa hasta el siguiente mes o hasta que aumentes el budget.</p>
        </div>
    </div>
    <form action="<?= e(url('/ai/usage/budget')) ?>" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
        <input type="hidden" name="_method" value="PUT">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <div>
            <label class="block text-xs font-semibold mb-1" style="color: var(--color-text-secondary);">Budget mensual (USD)</label>
            <input type="number" step="0.01" min="0" name="ai_budget_usd" value="<?= $usdBudget !== null ? e(number_format((float) $usdBudget, 2, '.', '')) : '' ?>" placeholder="ej. 50.00 (vacio = sin tope)" class="input w-full">
        </div>
        <div>
            <label class="block text-xs font-semibold mb-1" style="color: var(--color-text-secondary);">Umbral de alerta (%)</label>
            <input type="number" step="1" min="50" max="95" name="ai_alert_threshold_pct" value="<?= e((string) $threshold) ?>" class="input w-full">
            <p class="text-[11px] mt-1" style="color: var(--color-text-tertiary);">Te notificamos cuando el uso cruza este %.</p>
        </div>
        <div>
            <button type="submit" class="btn btn-primary w-full">Guardar</button>
        </div>
    </form>
    <p class="text-[11px] mt-3" style="color: var(--color-text-tertiary);">
        Las alertas se envian a los destinos en
        <a href="<?= e(url('/settings/notifications')) ?>" style="color: var(--color-primary); text-decoration: underline;">Configuracion → Notificaciones</a>
        suscritos al evento <code>ai.budget_alert</code>.
    </p>
</div>

<!-- Recent calls -->
<div class="surface p-5">
    <h3 class="text-base font-semibold mb-3" style="color: var(--color-text-primary);">Actividad reciente · ultimas 50 llamadas</h3>
    <?php if (empty($recent)): ?>
        <p class="text-sm" style="color: var(--color-text-tertiary);">Sin actividad todavia.</p>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left" style="color: var(--color-text-tertiary); font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em;">
                    <th class="pb-2">Cuando</th>
                    <th class="pb-2">Feature</th>
                    <th class="pb-2">Modelo</th>
                    <th class="pb-2 text-right">Tokens</th>
                    <th class="pb-2 text-right">Costo</th>
                    <th class="pb-2 text-right">Latencia</th>
                    <th class="pb-2">Orden</th>
                    <th class="pb-2 text-center">Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recent as $r): ?>
                <tr style="border-top: 1px solid var(--color-border-subtle);">
                    <td class="py-2 text-xs whitespace-nowrap" style="color: var(--color-text-tertiary);"><?= e(date('d M H:i:s', strtotime((string) $r['created_at']))) ?></td>
                    <td class="py-2 font-mono text-xs" style="color: var(--color-text-primary);"><?= e((string) $r['feature']) ?></td>
                    <td class="py-2 font-mono text-xs" style="color: var(--color-text-secondary);"><?= e((string) ($r['model'] ?? '')) ?></td>
                    <td class="py-2 text-right text-xs" style="color: var(--color-text-secondary);"><?= number_format((int) ($r['tokens_input'] ?? 0)) ?>/<?= number_format((int) ($r['tokens_output'] ?? 0)) ?></td>
                    <td class="py-2 text-right font-mono text-xs" style="color: var(--color-text-primary);">$<?= number_format((float) $r['cost_usd'], 6) ?></td>
                    <td class="py-2 text-right text-xs" style="color: var(--color-text-tertiary);"><?= $r['latency_ms'] !== null ? number_format((int) $r['latency_ms']) . 'ms' : '—' ?></td>
                    <td class="py-2 text-xs">
                        <?php if (!empty($r['order_code'])): ?>
                            <a href="<?= e(url('/orders/' . (int) $r['order_id'])) ?>" style="color: var(--color-primary); text-decoration: underline;">
                                <?= e((string) $r['order_code']) ?>
                            </a>
                            <span style="color: var(--color-text-tertiary);">($<?= number_format((float) $r['order_total'], 2) ?>)</span>
                        <?php else: ?>
                            <span style="color: var(--color-text-tertiary);">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="py-2 text-center">
                        <?php if (!empty($r['success'])): ?>
                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold" style="background: rgba(16,185,129,0.12); color:#0B7C56;">OK</span>
                        <?php else: ?>
                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold" style="background: rgba(220,42,71,0.12); color:#BE123C;" title="<?= e((string) ($r['error_message'] ?? '')) ?>">ERR</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
(function() {
    const data = <?= $dailyJson ?: '[]' ?>;
    const root = document.getElementById('ai-cost-chart');
    if (!root || !data.length) {
        if (root) root.innerHTML = '<div class="text-sm flex items-center justify-center h-full" style="color: var(--color-text-tertiary);">Sin datos en los ultimos 30 dias</div>';
        return;
    }
    // Construir map de 30 dias para no dejar huecos
    const days = [];
    const today = new Date();
    for (let i = 29; i >= 0; i--) {
        const d = new Date(today);
        d.setDate(d.getDate() - i);
        const key = d.toISOString().slice(0, 10);
        const found = data.find(x => x.day === key);
        days.push({ day: key, cost_usd: found ? found.cost_usd : 0, calls: found ? found.calls : 0 });
    }
    const maxCost = Math.max(...days.map(d => d.cost_usd), 0.01);
    const w = root.clientWidth || 600;
    const h = 180;
    const padding = { top: 10, right: 8, bottom: 24, left: 36 };
    const innerW = w - padding.left - padding.right;
    const innerH = h - padding.top - padding.bottom;
    const barW = innerW / days.length;

    let svg = '<svg viewBox="0 0 ' + w + ' ' + h + '" style="width:100%;height:100%;">';
    // Grid Y
    for (let i = 0; i <= 4; i++) {
        const y = padding.top + (innerH * i / 4);
        const val = (maxCost * (1 - i / 4));
        svg += '<line x1="' + padding.left + '" y1="' + y + '" x2="' + (w - padding.right) + '" y2="' + y + '" stroke="rgba(0,0,0,0.06)" stroke-width="1" />';
        svg += '<text x="' + (padding.left - 4) + '" y="' + (y + 3) + '" text-anchor="end" font-size="10" fill="var(--color-text-tertiary)">$' + val.toFixed(2) + '</text>';
    }
    days.forEach((d, i) => {
        const barH = maxCost > 0 ? (d.cost_usd / maxCost) * innerH : 0;
        const x = padding.left + i * barW + 1;
        const y = padding.top + innerH - barH;
        const color = d.cost_usd === 0 ? 'rgba(0,0,0,0.08)' : '#0EA572';
        svg += '<rect x="' + x + '" y="' + y + '" width="' + (barW - 2) + '" height="' + barH + '" fill="' + color + '" rx="2">';
        svg += '<title>' + d.day + ' — $' + d.cost_usd.toFixed(4) + ' (' + d.calls + ' calls)</title></rect>';
        // Etiqueta de dia cada 5
        if (i % 5 === 0 || i === days.length - 1) {
            const label = d.day.slice(5);
            svg += '<text x="' + (x + (barW - 2) / 2) + '" y="' + (h - 8) + '" text-anchor="middle" font-size="10" fill="var(--color-text-tertiary)">' + label + '</text>';
        }
    });
    svg += '</svg>';
    root.innerHTML = svg;
})();
</script>

<?php
\App\Core\View::stop();
