<?php
/**
 * Dashboard ejecutivo unificado.
 *
 * @var array $snapshot   data devuelta por ExecutiveDashboardService::snapshot()
 * @var array $tenant
 * @var string $currency
 */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

$k = $snapshot['kpis']        ?? [];
$c = $snapshot['commercial']  ?? [];
$ai = $snapshot['ai']         ?? [];
$au = $snapshot['automation'] ?? [];
$se = $snapshot['security']   ?? [];
$tr = $snapshot['trends']     ?? [];
$ac = $snapshot['activity']   ?? [];

/** Helper sparkline SVG: $values numericos, devuelve <svg> minimalista. */
function exec_sparkline(array $values, string $color = '#06B6D4', int $w = 100, int $h = 28): string {
    if (empty($values)) return '';
    $max = max($values); $min = min($values);
    $range = $max - $min;
    if ($range < 0.0001) $range = 1;
    $n = count($values);
    $stepX = $n > 1 ? $w / ($n - 1) : 0;
    $points = [];
    foreach ($values as $i => $v) {
        $x = round($i * $stepX, 1);
        $y = round($h - (($v - $min) / $range) * ($h - 4) - 2, 1);
        $points[] = "$x,$y";
    }
    $path = 'M ' . implode(' L ', $points);
    $last = end($values);
    $lastX = round(($n - 1) * $stepX, 1);
    $lastY = round($h - (($last - $min) / $range) * ($h - 4) - 2, 1);
    $area = $path . " L $lastX,$h L 0,$h Z";
    return '<svg viewBox="0 0 ' . $w . ' ' . $h . '" width="100%" height="' . $h . '" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">'
        . '<path d="' . $area . '" fill="' . $color . '" fill-opacity="0.12"/>'
        . '<path d="' . $path . '" fill="none" stroke="' . $color . '" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>'
        . '<circle cx="' . $lastX . '" cy="' . $lastY . '" r="2.5" fill="' . $color . '"/>'
        . '</svg>';
}

/** Pct change vs ayer ("+12%" / "-5%" / "—"). */
function exec_delta(float $now, float $prev): string {
    if ($prev == 0.0) {
        if ($now > 0) return '<span style="color:#0B7C56;font-weight:600">+nuevo</span>';
        return '<span style="color:var(--color-text-tertiary)">—</span>';
    }
    $pct = (($now - $prev) / $prev) * 100;
    $sign = $pct > 0 ? '+' : '';
    $color = $pct > 0 ? '#0B7C56' : ($pct < 0 ? '#BE123C' : 'var(--color-text-tertiary)');
    return '<span style="color:' . $color . ';font-weight:600">' . $sign . round($pct, 0) . '%</span>';
}

function exec_format_money(float $n, string $cur): string {
    return $cur . ' ' . number_format($n, 2);
}
?>
<?php \App\Core\View::include('components.page_header', [
    'title'    => 'Dashboard ejecutivo',
    'subtitle' => 'Vista unificada de tu negocio + plataforma. Datos en vivo con cache de 60s. Generado ' . date('H:i', strtotime((string) ($snapshot['generated_at'] ?? 'now'))) . '.',
]); ?>

<?php \App\Core\View::include('components.onboarding_banner'); ?>

<div class="mb-4 flex items-center gap-2">
    <a href="<?= url('/dashboard?view=legacy') ?>" class="text-xs px-3 py-1.5 rounded-lg border" style="border-color: var(--color-border); color: var(--color-text-secondary);">Ver dashboard clasico</a>
</div>

<!-- KPIs principales -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
    <?php
    $kpiCards = [
        [
            'label' => 'Ordenes hoy',
            'value' => number_format((int) ($k['orders_today'] ?? 0)),
            'delta' => exec_delta((float) ($k['orders_today'] ?? 0), (float) ($k['orders_yesterday'] ?? 0)),
            'icon'  => '🛒',
            'color' => '#10B981',
            'spark' => $tr['orders'] ?? [],
        ],
        [
            'label' => 'Ingresos hoy',
            'value' => exec_format_money((float) ($k['revenue_today'] ?? 0), $currency),
            'delta' => exec_delta((float) ($k['revenue_today'] ?? 0), (float) ($k['revenue_yesterday'] ?? 0)),
            'icon'  => '💵',
            'color' => '#0EA572',
            'spark' => $tr['revenue'] ?? [],
        ],
        [
            'label' => 'Agente IA runs hoy',
            'value' => number_format((int) ($k['agent_runs_today'] ?? 0)),
            'delta' => '',
            'icon'  => '🧠',
            'color' => '#8B5CF6',
            'spark' => $tr['runs'] ?? [],
        ],
        [
            'label' => 'Costo IA hoy',
            'value' => '$' . number_format((float) ($k['ai_cost_today'] ?? 0), 4),
            'delta' => '',
            'icon'  => '💎',
            'color' => '#06B6D4',
            'spark' => $tr['cost'] ?? [],
        ],
        [
            'label' => 'Conversaciones abiertas',
            'value' => number_format((int) ($k['conv_open'] ?? 0)),
            'delta' => '',
            'icon'  => '💬',
            'color' => '#F59E0B',
            'spark' => [],
        ],
        [
            'label' => 'Workflows activos',
            'value' => number_format((int) ($k['workflows_active'] ?? 0)),
            'delta' => '',
            'icon'  => '🪄',
            'color' => '#EC4899',
            'spark' => [],
        ],
    ];
    foreach ($kpiCards as $card): ?>
    <div class="surface p-3.5 flex flex-col">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] uppercase tracking-wider font-semibold leading-tight" style="color: var(--color-text-tertiary);"><?= e((string) $card['label']) ?></span>
            <span class="text-base"><?= $card['icon'] ?></span>
        </div>
        <div class="text-xl font-bold leading-tight" style="color: var(--color-text-primary);"><?= $card['value'] ?></div>
        <?php if ($card['delta'] !== ''): ?>
        <div class="text-[10px] mt-0.5"><?= $card['delta'] ?> <span style="color: var(--color-text-tertiary);">vs ayer</span></div>
        <?php endif; ?>
        <?php if (!empty($card['spark'])): ?>
        <div class="mt-1.5 -mx-1"><?= exec_sparkline($card['spark'], $card['color'], 100, 24) ?></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- 3 columnas: IA / Automatizacion / Seguridad -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">

    <!-- IA -->
    <div class="surface p-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold flex items-center gap-1.5" style="color: var(--color-text-primary);"><span>🧠</span> Inteligencia artificial</h3>
            <a href="<?= url('/ai/usage') ?>" class="text-[11px]" style="color: var(--color-text-secondary);">Detalle →</a>
        </div>
        <div class="grid grid-cols-2 gap-2 mb-3">
            <div class="p-2.5 rounded-lg" style="background: var(--color-bg-secondary);">
                <div class="text-[10px] uppercase font-semibold" style="color: var(--color-text-tertiary);">Runs 7d</div>
                <div class="text-lg font-bold" style="color: var(--color-text-primary);"><?= number_format((int) ($ai['runs_7d'] ?? 0)) ?></div>
            </div>
            <div class="p-2.5 rounded-lg" style="background: var(--color-bg-secondary);">
                <div class="text-[10px] uppercase font-semibold" style="color: var(--color-text-tertiary);">Tasa exito</div>
                <div class="text-lg font-bold" style="color: <?= ($ai['success_rate'] ?? 0) >= 95 ? '#0B7C56' : ($ai['success_rate'] >= 80 ? '#B45309' : '#BE123C') ?>;"><?= number_format((float) ($ai['success_rate'] ?? 0), 1) ?>%</div>
            </div>
            <div class="p-2.5 rounded-lg" style="background: var(--color-bg-secondary);">
                <div class="text-[10px] uppercase font-semibold" style="color: var(--color-text-tertiary);">Costo 7d</div>
                <div class="text-lg font-bold" style="color: var(--color-text-primary);">$<?= number_format((float) ($ai['cost_7d'] ?? 0), 4) ?></div>
            </div>
            <div class="p-2.5 rounded-lg" style="background: var(--color-bg-secondary);">
                <div class="text-[10px] uppercase font-semibold" style="color: var(--color-text-tertiary);">Latencia avg</div>
                <div class="text-lg font-bold" style="color: var(--color-text-primary);"><?= number_format((int) ($ai['avg_latency'] ?? 0)) ?>ms</div>
            </div>
        </div>
        <?php if (!empty($ai['top_agents'])): ?>
        <div class="text-[10px] uppercase tracking-wider font-semibold mb-1.5" style="color: var(--color-text-tertiary);">Top agentes (7d)</div>
        <ul class="space-y-1">
            <?php foreach ($ai['top_agents'] as $a): ?>
            <li class="flex items-center justify-between text-xs">
                <span class="truncate" style="color: var(--color-text-primary);"><?= e((string) ($a['name'] ?? '—')) ?></span>
                <span class="font-mono ml-2 flex-shrink-0" style="color: var(--color-text-secondary);"><?= number_format((int) $a['runs']) ?> · $<?= number_format((float) $a['cost'], 4) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

    <!-- Automatizacion -->
    <div class="surface p-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold flex items-center gap-1.5" style="color: var(--color-text-primary);"><span>🪄</span> Automatizacion</h3>
            <a href="<?= url('/workflows') ?>" class="text-[11px]" style="color: var(--color-text-secondary);">Workflows →</a>
        </div>
        <div class="grid grid-cols-2 gap-2 mb-3">
            <div class="p-2.5 rounded-lg" style="background: var(--color-bg-secondary);">
                <div class="text-[10px] uppercase font-semibold" style="color: var(--color-text-tertiary);">Workflows activos</div>
                <div class="text-lg font-bold" style="color: var(--color-text-primary);"><?= (int) ($au['workflows_active'] ?? 0) ?> <span class="text-xs font-normal" style="color: var(--color-text-tertiary);">/ <?= ((int) ($au['workflows_active'] ?? 0)) + ((int) ($au['workflows_paused'] ?? 0)) ?></span></div>
            </div>
            <div class="p-2.5 rounded-lg" style="background: var(--color-bg-secondary);">
                <div class="text-[10px] uppercase font-semibold" style="color: var(--color-text-tertiary);">Runs 7d</div>
                <div class="text-lg font-bold" style="color: var(--color-text-primary);"><?= number_format((int) ($au['workflow_runs_7d'] ?? 0)) ?></div>
            </div>
            <div class="p-2.5 rounded-lg" style="background: var(--color-bg-secondary);">
                <div class="text-[10px] uppercase font-semibold" style="color: var(--color-text-tertiary);">Esperando</div>
                <div class="text-lg font-bold" style="color: <?= ($au['workflow_waiting'] ?? 0) > 0 ? '#B45309' : 'var(--color-text-primary)' ?>;"><?= number_format((int) ($au['workflow_waiting'] ?? 0)) ?></div>
            </div>
            <div class="p-2.5 rounded-lg" style="background: var(--color-bg-secondary);">
                <div class="text-[10px] uppercase font-semibold" style="color: var(--color-text-tertiary);">Fallados 7d</div>
                <div class="text-lg font-bold" style="color: <?= ($au['workflow_failed_7d'] ?? 0) > 0 ? '#BE123C' : 'var(--color-text-primary)' ?>;"><?= number_format((int) ($au['workflow_failed_7d'] ?? 0)) ?></div>
            </div>
        </div>
        <div class="text-[10px] uppercase tracking-wider font-semibold mb-1.5" style="color: var(--color-text-tertiary);">Webhooks salientes 7d</div>
        <div class="flex items-center gap-3 text-xs">
            <span style="color:#0B7C56;">✓ <?= number_format((int) (($au['webhook_deliveries_7d'] ?? 0) - ($au['webhook_failed_7d'] ?? 0))) ?> ok</span>
            <span style="color: <?= ($au['webhook_failed_7d'] ?? 0) > 0 ? '#BE123C' : 'var(--color-text-tertiary)' ?>;">⚠ <?= (int) ($au['webhook_failed_7d'] ?? 0) ?> fallos</span>
            <span style="color: <?= ($au['webhook_pending'] ?? 0) > 0 ? '#B45309' : 'var(--color-text-tertiary)' ?>;">⏳ <?= (int) ($au['webhook_pending'] ?? 0) ?> pendientes</span>
        </div>
        <div class="text-[10px] mt-3" style="color: var(--color-text-tertiary);">API calls 7d: <strong><?= number_format((int) ($au['api_calls_7d'] ?? 0)) ?></strong></div>
    </div>

    <!-- Seguridad -->
    <div class="surface p-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold flex items-center gap-1.5" style="color: var(--color-text-primary);"><span>🔐</span> Seguridad</h3>
            <a href="<?= url('/settings/security') ?>" class="text-[11px]" style="color: var(--color-text-secondary);">Configurar →</a>
        </div>
        <div class="grid grid-cols-2 gap-2 mb-3">
            <div class="p-2.5 rounded-lg" style="background: var(--color-bg-secondary);">
                <div class="text-[10px] uppercase font-semibold" style="color: var(--color-text-tertiary);">Cobertura 2FA</div>
                <div class="text-lg font-bold" style="color: <?= ($se['twofa_coverage'] ?? 0) >= 80 ? '#0B7C56' : (($se['twofa_coverage'] ?? 0) >= 50 ? '#B45309' : '#BE123C') ?>;"><?= (int) ($se['twofa_coverage'] ?? 0) ?>%</div>
                <div class="text-[10px]" style="color: var(--color-text-tertiary);"><?= (int) ($se['users_with_2fa'] ?? 0) ?> de <?= (int) ($se['tenant_users'] ?? 0) ?> usuarios</div>
            </div>
            <div class="p-2.5 rounded-lg" style="background: var(--color-bg-secondary);">
                <div class="text-[10px] uppercase font-semibold" style="color: var(--color-text-tertiary);">Sesiones 24h</div>
                <div class="text-lg font-bold" style="color: var(--color-text-primary);"><?= (int) ($se['active_sessions'] ?? 0) ?></div>
            </div>
            <div class="p-2.5 rounded-lg" style="background: var(--color-bg-secondary);">
                <div class="text-[10px] uppercase font-semibold" style="color: var(--color-text-tertiary);">Logins fallidos 24h</div>
                <div class="text-lg font-bold" style="color: <?= ($se['logins_failed_24h'] ?? 0) > 5 ? '#BE123C' : 'var(--color-text-primary)' ?>;"><?= (int) ($se['logins_failed_24h'] ?? 0) ?></div>
            </div>
            <div class="p-2.5 rounded-lg" style="background: var(--color-bg-secondary);">
                <div class="text-[10px] uppercase font-semibold" style="color: var(--color-text-tertiary);">API keys activas</div>
                <div class="text-lg font-bold" style="color: var(--color-text-primary);"><?= (int) ($se['active_api_keys'] ?? 0) ?></div>
            </div>
        </div>
        <?php if (($se['critical_7d'] ?? 0) > 0 || ($se['warning_7d'] ?? 0) > 0): ?>
        <div class="flex items-center gap-3 text-xs">
            <?php if (($se['critical_7d'] ?? 0) > 0): ?>
            <span style="color:#BE123C;font-weight:600;">🚨 <?= (int) $se['critical_7d'] ?> eventos criticos 7d</span>
            <?php endif; ?>
            <?php if (($se['warning_7d'] ?? 0) > 0): ?>
            <span style="color:#B45309;">⚠ <?= (int) $se['warning_7d'] ?> warnings 7d</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Comercial detail + Activity feed -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    <!-- Comercial -->
    <div class="lg:col-span-1 surface p-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold flex items-center gap-1.5" style="color: var(--color-text-primary);"><span>📊</span> Comercial (30d)</h3>
            <a href="<?= url('/orders') ?>" class="text-[11px]" style="color: var(--color-text-secondary);">Ver ordenes →</a>
        </div>

        <div class="grid grid-cols-2 gap-2 mb-3">
            <div class="p-2.5 rounded-lg" style="background: var(--color-bg-secondary);">
                <div class="text-[10px] uppercase font-semibold" style="color: var(--color-text-tertiary);">Ticket promedio</div>
                <div class="text-lg font-bold" style="color: var(--color-text-primary);"><?= exec_format_money((float) ($c['avg_order_30d'] ?? 0), $currency) ?></div>
            </div>
            <div class="p-2.5 rounded-lg" style="background: var(--color-bg-secondary);">
                <div class="text-[10px] uppercase font-semibold" style="color: var(--color-text-tertiary);">Contactos nuevos</div>
                <div class="text-lg font-bold" style="color: var(--color-text-primary);"><?= number_format((int) ($c['contacts_30d'] ?? 0)) ?></div>
            </div>
        </div>

        <?php if (($c['tickets_critical'] ?? 0) > 0): ?>
        <div class="p-2.5 rounded-lg text-xs mb-3" style="background: rgba(244,63,94,.08); color:#BE123C;">
            <strong>🚨 <?= (int) $c['tickets_critical'] ?> ticket(s) critico(s)</strong> sin resolver
        </div>
        <?php endif; ?>

        <?php if (!empty($c['orders_by_status'])): ?>
        <div class="text-[10px] uppercase tracking-wider font-semibold mb-1.5" style="color: var(--color-text-tertiary);">Ordenes por estado</div>
        <ul class="space-y-1">
            <?php
            $statusColors = [
                'new' => '#06B6D4', 'confirmed' => '#3B82F6', 'preparing' => '#F59E0B',
                'ready' => '#A855F7', 'out_for_delivery' => '#EC4899', 'delivered' => '#22C55E',
                'cancelled' => '#EF4444',
            ];
            foreach ($c['orders_by_status'] as $row):
                $st = (string) $row['status'];
                $col = $statusColors[$st] ?? '#64748B';
            ?>
            <li class="flex items-center justify-between text-xs">
                <span class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full" style="background: <?= $col ?>;"></span>
                    <span style="color: var(--color-text-primary);"><?= e($st) ?></span>
                </span>
                <span class="font-mono" style="color: var(--color-text-secondary);">
                    <?= number_format((int) $row['n']) ?> · <?= exec_format_money((float) $row['revenue'], $currency) ?>
                </span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

    <!-- Activity feed -->
    <div class="lg:col-span-2 surface overflow-hidden">
        <div class="px-4 py-3 border-b flex items-center justify-between" style="border-color: var(--color-border);">
            <h3 class="font-bold flex items-center gap-1.5" style="color: var(--color-text-primary);"><span>⚡</span> Actividad reciente</h3>
            <span class="text-[10px]" style="color: var(--color-text-tertiary);">cross-source · ult <?= count($ac) ?></span>
        </div>
        <?php if (empty($ac)): ?>
        <div class="p-6 text-center text-sm" style="color: var(--color-text-secondary);">Sin actividad reciente.</div>
        <?php else: ?>
        <ul class="divide-y" style="border-color: var(--color-border);">
            <?php foreach ($ac as $item):
                $sev = (string) ($item['severity'] ?? 'info');
                $bg = match ($sev) {
                    'critical' => 'rgba(244,63,94,.08)',
                    'warning'  => 'rgba(245,158,11,.05)',
                    default    => 'transparent',
                };
            ?>
            <li class="px-4 py-2.5 flex items-start gap-3" style="background: <?= $bg ?>;">
                <span class="text-base flex-shrink-0"><?= e((string) $item['icon']) ?></span>
                <div class="flex-1 min-w-0">
                    <div class="text-xs truncate" style="color: var(--color-text-primary);"><?= e((string) $item['title']) ?></div>
                    <?php if (!empty($item['subtitle'])): ?>
                    <div class="text-[10px]" style="color: var(--color-text-tertiary);"><?= e((string) $item['subtitle']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="text-[10px] whitespace-nowrap flex-shrink-0" style="color: var(--color-text-tertiary);">
                    <?php
                        $ts = strtotime((string) $item['time']);
                        $diff = time() - $ts;
                        if ($diff < 60)         echo $diff . 's';
                        elseif ($diff < 3600)   echo round($diff / 60) . 'm';
                        elseif ($diff < 86400)  echo round($diff / 3600) . 'h';
                        else                    echo round($diff / 86400) . 'd';
                    ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

<div class="text-center mt-4">
    <span class="text-[10px]" style="color: var(--color-text-tertiary);">Dashboard ejecutivo · cache 60s · <a href="<?= url('/dashboard?refresh=1') ?>" style="text-decoration: underline;">refrescar ahora</a></span>
</div>
<?php \App\Core\View::end(); ?>
